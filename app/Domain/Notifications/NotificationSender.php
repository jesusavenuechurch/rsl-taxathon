<?php

namespace App\Domain\Notifications;

use App\Domain\Audit\AuditLog;
use App\Models\Cases\ReviewCase;
use App\Models\NotificationDelivery;
use App\Models\Team;
use App\Models\User;
use App\Services\WhatsApp\WhatsAppCloudService;
use App\Support\Clock;
use App\Support\Roles;

/**
 * WhatsApp on each hand-off event (officer-desk.md section 7). Every
 * template is whitelisted to case type, priority, reference and team name —
 * never a company, TIN or amount (handoff rule 13). A number that is not
 * opted in is recorded as skipped, never silently dropped.
 */
class NotificationSender
{
    private const TEMPLATES = [
        'case_opened' => 'rsl_case_ready',
        'case_referred' => 'rsl_case_referred',
        'documents_received' => 'rsl_documents_received',
        'signoff_needed' => 'rsl_signoff_needed',
    ];

    /**
     * The approved templates' bodies, word for word as created in WhatsApp
     * Manager (Utility, button "Open case" -> https://<host>/cases/{{1}}).
     * The Notifications sent page shows exactly this text, so what the page
     * says was sent is what the phone shows.
     */
    public const BODIES = [
        'rsl_case_ready' => 'Hello, a new {{1}} has been added to your worklist with {{2}} priority. Please open RSL Case Review to see what needs your attention.',
        'rsl_case_referred' => 'Hello, case {{1}} has been referred to the {{2}} team and is waiting for an officer to pick it up. Please open RSL Case Review to take it on.',
        'rsl_documents_received' => 'Hello, the documents requested for case {{1}} have arrived and are ready for your review. Please open RSL Case Review to see them.',
        'rsl_signoff_needed' => 'Hello, a decision on case {{1}} is waiting for your second sign-off before it can be closed. Please open RSL Case Review to approve or return it.',
    ];

    public function __construct(private WhatsAppCloudService $whatsapp, private Clock $clock, private AuditLog $audit) {}

    public function caseOpened(ReviewCase $case): void
    {
        $this->sendTo($case->team->users, $case, 'case_opened', [$this->caseTypePhrase($case), $case->priority]);
    }

    public function caseReferred(ReviewCase $case, Team $to): void
    {
        $this->sendTo($to->users, $case, 'case_referred', [$case->reference, $to->name]);
    }

    public function documentsReceived(ReviewCase $case): void
    {
        $recipients = $case->assigned_user_id ? User::where('id', $case->assigned_user_id)->get() : $case->team->users;
        $this->sendTo($recipients, $case, 'documents_received', [$case->reference]);
    }

    public function signoffNeeded(ReviewCase $case): void
    {
        $this->sendTo(User::role(Roles::TEAM_LEAD)->get(), $case, 'signoff_needed', [$case->reference]);
    }

    /** @param  iterable<User>  $recipients */
    private function sendTo(iterable $recipients, ReviewCase $case, string $event, array $params): void
    {
        // A demo rebuild reopens every case at once: nothing goes to phones for that.
        if (config('rsl.notifications_muted')) {
            return;
        }

        foreach ($recipients as $user) {
            $this->send($user, $case, $event, $params);
        }
    }

    private function send(User $user, ReviewCase $case, string $event, array $params): NotificationDelivery
    {
        $template = self::TEMPLATES[$event];
        $text = self::render($template, $params);

        $row = NotificationDelivery::create([
            'event' => $event, 'template' => $template, 'recipient_id' => $user->id, 'case_reference' => $case->reference,
            'rendered_text' => $text, 'status' => NotificationDelivery::QUEUED,
            'created_at' => $this->clock->now(),
        ]);
        $row->update(['reference' => sprintf('NOTE-%04d', $row->id)]);

        if (! $user->whatsapp_opt_in || ! $user->whatsapp_number) {
            $row->update(['status' => NotificationDelivery::SKIPPED, 'status_detail' => 'Not opted in, or no WhatsApp number on file (demo number).']);

            return $row;
        }

        $result = $this->whatsapp->sendTemplate($user->whatsapp_number, $template, $params, buttonUrlSuffix: $case->reference,
            languageCode: config('services.whatsapp.template_language', 'en'));

        $row->update([
            'status' => $result['success'] ? NotificationDelivery::SENT : NotificationDelivery::FAILED,
            'status_detail' => $result['error'],
            'meta_message_id' => $result['message_id'],
            'sent_at' => $result['success'] ? $this->clock->now() : null,
        ]);

        $this->audit->record('notification_'.($result['success'] ? 'sent' : 'failed'), subjectType: 'notification_delivery', subjectRef: $row->reference,
            payload: ['event' => $event, 'case' => $case->reference, 'recipient' => $user->id]);

        return $row;
    }

    /** The template's body with its {{n}} parameters filled, exactly as WhatsApp renders it. */
    public static function render(string $template, array $params): string
    {
        return preg_replace_callback('/\\{\\{(\\d+)\\}\\}/', fn ($m) => (string) ($params[(int) $m[1] - 1] ?? ''), self::BODIES[$template] ?? implode(' ', $params));
    }

    private function caseTypePhrase(ReviewCase $case): string
    {
        return match ($case->type) {
            ReviewCase::REFUND_REVIEW => 'refund review case'.($case->laneLabel() ? ' ('.strtolower($case->laneLabel()).')' : ''),
            ReviewCase::RISK_REVIEW => 'risk review case',
            ReviewCase::INFORMATION_GAP => 'information gap case',
            ReviewCase::DATA_CHECK => 'data check case',
            default => 'case',
        };
    }
}
