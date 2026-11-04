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

    public function __construct(private WhatsAppCloudService $whatsapp, private Clock $clock, private AuditLog $audit) {}

    public function caseOpened(ReviewCase $case): void
    {
        $this->sendTo($case->team->users, $case, 'case_opened', [$this->caseTypePhrase($case), ucfirst($case->priority)]);
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
        foreach ($recipients as $user) {
            $this->send($user, $case, $event, $params);
        }
    }

    private function send(User $user, ReviewCase $case, string $event, array $params): NotificationDelivery
    {
        $template = self::TEMPLATES[$event];
        $text = $this->render($template, $params);

        $row = NotificationDelivery::create([
            'event' => $event, 'template' => $template, 'recipient_id' => $user->id, 'case_reference' => $case->reference,
            'rendered_text' => $text, 'status' => NotificationDelivery::QUEUED,
        ]);
        $row->update(['reference' => sprintf('NOTE-%04d', $row->id)]);

        if (! $user->whatsapp_opt_in || ! $user->whatsapp_number) {
            $row->update(['status' => NotificationDelivery::SKIPPED, 'status_detail' => 'Not opted in, or no WhatsApp number on file (demo number).']);

            return $row;
        }

        $result = $this->whatsapp->sendTemplate($user->whatsapp_number, $template, $params, buttonUrlSuffix: $case->reference);

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

    private function render(string $template, array $params): string
    {
        return match ($template) {
            'rsl_case_ready' => "A {$params[0]} is ready for review ({$params[1]} priority).",
            'rsl_case_referred' => "Case {$params[0]} has been referred to {$params[1]}.",
            'rsl_documents_received' => "Documents have arrived for case {$params[0]}.",
            'rsl_signoff_needed' => "Case {$params[0]} needs your sign-off.",
            default => implode(' ', $params),
        };
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
