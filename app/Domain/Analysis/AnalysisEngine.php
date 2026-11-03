<?php

namespace App\Domain\Analysis;

use App\Domain\Analysis\Rules\PatternRule;
use App\Domain\Analysis\Wording\PyFormat;
use App\Domain\Analysis\Wording\TemplateRenderer;
use App\Domain\Analysis\Wording\WordingPolicy;
use App\Domain\Audit\AuditActor;
use App\Domain\Audit\AuditLog;
use App\Domain\Cases\CaseRouter;
use App\Domain\Memory\MemoryChecker;
use App\Domain\Records\Channels;
use App\Models\Analysis\AnalysisRun;
use App\Models\Analysis\FilingReview;
use App\Models\Analysis\Finding;
use App\Models\Analysis\FindingChecklistItem;
use App\Models\Analysis\PatternRule as PatternRuleModel;
use App\Models\Analysis\PatternRuleVersion;
use App\Models\Analysis\RuleCheck;
use App\Models\SourceImport;
use App\Models\SourceRecords\GroupEntity;
use App\Models\SourceRecords\Taxpayer;
use App\Support\Clock;
use App\Support\Settings;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Runs the pattern catalogue over taxpayers. For each one it saves a filing
 * review (with every rule's result, fired or not) and keeps findings in step
 * with the records: new findings are raised, unchanged ones are carried
 * forward, changed ones are superseded, and ones the records no longer
 * support are withdrawn. It never concludes anything.
 */
class AnalysisEngine
{
    public const NOTHING_FOUND = 'Revenue, expenses and profit are broadly consistent with previous years. No significant unusual changes were identified in the available records.';

    public function __construct(private Clock $clock, private Settings $settings, private AuditLog $audit, private MemoryChecker $memory, private CaseRouter $router) {}

    /** @param list<string>|null $tins null = every registered taxpayer */
    public function run(?array $tins = null, string $trigger = 'manual', ?SourceImport $import = null): AnalysisRun
    {
        $tins ??= Taxpayer::current()->orderBy('record_id')->pluck('tin')->all();
        $rules = $this->rules();

        $run = AnalysisRun::create([
            'trigger' => $trigger,
            'initiated_by' => auth()->id(),
            'source_import_id' => $import?->id,
            'rule_versions' => $rules->mapWithKeys(fn ($r) => [$r['model']->code => $r['version']->id])->all(),
            'status' => 'running',
            'started_at' => $this->clock->now(),
        ]);

        $context = AnalysisContext::fromRecords();
        $register = GroupEntity::current()->orderBy('record_id')->get()->keyBy('normalized_name');
        $params = $this->rates();

        foreach (array_values(array_unique($tins)) as $tin) {
            DB::transaction(fn () => $this->analyse(TaxpayerSnapshot::load($tin, $context, $register), $run, $rules, $params, $import));
        }

        $run->update(['status' => 'completed', 'taxpayers' => count(array_unique($tins)), 'finished_at' => $this->clock->now()]);

        return $run;
    }

    private function analyse(TaxpayerSnapshot $s, AnalysisRun $run, Collection $rules, array $rates, ?SourceImport $import): FilingReview
    {
        [$anchorId, $anchorLabel] = $this->anchor($s, $import);
        $now = $this->clock->now();

        $review = FilingReview::create([
            'analysis_run_id' => $run->id,
            'tin' => $s->tin,
            'anchor_record_id' => $anchorId,
            'anchor_label' => $anchorLabel,
            'summary' => '',
            'findings_count' => 0,
            'reviewed_at' => $now,
        ]);
        $review->update(['reference' => sprintf('FR-%04d', $review->id)]);

        $current = [];       // findings this review stands behind
        $evaluated = [];     // patterns actually run (a disabled rule leaves its findings alone)

        foreach ($rules as $code => ['model' => $rule, 'version' => $version, 'class' => $class]) {
            $outcome = $class->evaluate($s, new RuleParameters($version->parameters, $rates));
            $evaluated[] = $code;

            RuleCheck::create([
                'filing_review_id' => $review->id,
                'pattern_rule_version_id' => $version->id,
                'pattern_code' => $code,
                'result' => $outcome->result,
                'explanation' => $outcome->explanation,
                'metrics' => $outcome->metrics,
            ]);

            foreach ($outcome->findings as $draft) {
                $current[] = $this->record($s, $review, $rule, $version, $draft, $now);
            }
        }

        $this->withdrawStale($s->tin, $evaluated, collect($current)->pluck('id')->all(), $now);

        // Memory is consulted only after the rules have raised what they raise (rule 8).
        $memory = $this->memory->check($s, $review, $current);

        $review->update(['summary' => $this->summary($current), 'findings_count' => count($current)]);

        $case = $this->router->route($review);

        $this->audit->record('analysed', subjectType: 'filing_review', subjectRef: $review->reference, taxpayerTin: $s->tin,
            actor: AuditActor::current(), payload: [
                'run' => $run->id,
                'anchor' => $anchorId,
                'patterns' => collect($current)->map(fn (Finding $f) => "{$f->pattern_code}:{$f->reference}")->all(),
                'memory' => collect($memory)->map(fn ($m) => "{$m->decision->reference}:{$m->result}")->all(),
                'case' => $case?->reference,
            ]);

        return $review;
    }

    private function record(TaxpayerSnapshot $s, FilingReview $review, PatternRuleModel $rule, PatternRuleVersion $version, FindingDraft $draft, $now): Finding
    {
        $variant = $version->variant($draft->variant);
        $wording = TemplateRenderer::render($variant['template'], $draft->values);
        WordingPolicy::assertAcceptable($wording, $version->evidence_level);

        $exposure = PyFormat::roundHalfEven($draft->exposure);
        $evidence = collect($draft->evidence)->map(function (string $id) use ($s) {
            $row = $s->record($id) ?? throw new \LogicException("Rule cited {$id}, which is not in the snapshot.");

            return ['record_id' => $id, 'record_version' => $row->version, 'channel' => Channels::forRecordId($id)->key, 'row_hash' => $row->row_hash];
        });

        $fingerprint = "{$s->tin}|{$rule->code}|{$draft->scope}";
        $existing = Finding::open()->where('fingerprint', $fingerprint)->with('evidence')->first();

        if ($existing && $this->unchanged($existing, $version, $draft->variant, $wording, $exposure, $evidence)) {
            $existing->update(['last_review_id' => $review->id]);

            return $existing;
        }

        $finding = Finding::create([
            'tin' => $s->tin,
            'pattern_code' => $rule->code,
            'pattern_rule_version_id' => $version->id,
            'first_review_id' => $review->id,
            'last_review_id' => $review->id,
            'fingerprint' => $fingerprint,
            'variant' => $draft->variant,
            'kind' => $variant['kind'],
            'title' => $variant['title'],
            'evidence_level' => $version->evidence_level,
            'wording' => $wording,
            'metrics' => $draft->values,
            'exposure_estimate' => $exposure,
            'exposure_basis' => $exposure > 0 ? $version->exposure_basis : null,
            'status' => Finding::OPEN,
            'raised_by' => 'system_rule',
            'raised_at' => $now,
        ]);
        $finding->update(['reference' => sprintf('F-%03d', $finding->id)]);

        foreach ($evidence->values() as $i => $link) {
            $finding->evidence()->create([...$link, 'position' => $i + 1]);
        }
        foreach ([FindingChecklistItem::LAWFUL_EXPLANATION => $version->lawful_explanations, FindingChecklistItem::MUST_ESTABLISH => $version->must_establish] as $type => $items) {
            foreach ($items as $i => $text) {
                $finding->checklist()->create(['type' => $type, 'position' => $i + 1, 'text' => $text]);
            }
        }

        if ($existing) {
            $existing->update(['status' => Finding::SUPERSEDED, 'superseded_by_id' => $finding->id, 'closed_at' => $now]);
        }

        return $finding;
    }

    private function unchanged(Finding $f, PatternRuleVersion $version, string $variant, string $wording, int $exposure, Collection $evidence): bool
    {
        return $f->pattern_rule_version_id === $version->id
            && $f->variant === $variant
            && $f->wording === $wording
            && $f->exposure_estimate === $exposure
            && $f->evidence->map(fn ($e) => "{$e->record_id}@{$e->record_version}")->all()
                === $evidence->map(fn ($e) => "{$e['record_id']}@{$e['record_version']}")->values()->all();
    }

    /** Open findings of patterns that ran but did not raise them again: the records no longer support them. */
    private function withdrawStale(string $tin, array $evaluated, array $keep, $now): void
    {
        Finding::open()->where('tin', $tin)->where('raised_by', 'system_rule')
            ->whereIn('pattern_code', $evaluated)->whereNotIn('id', $keep)
            ->update(['status' => Finding::WITHDRAWN, 'closed_at' => $now]);
    }

    private function summary(array $findings): string
    {
        $findings = collect($findings);
        $review = $findings->where('kind', '!=', 'green_lane');
        $green = $findings->where('kind', 'green_lane');

        if ($findings->isEmpty()) {
            return self::NOTHING_FOUND;
        }
        if ($review->isEmpty()) {
            return 'Recurring VAT credits look lower risk in the checks performed. No other patterns were found.';
        }

        $list = $review->map(fn (Finding $f) => "{$f->pattern_code} {$f->title}")->implode('; ');

        return $review->count().' pattern(s) for an officer to review: '.$list.'.'
            .($green->isNotEmpty() ? ' Recurring VAT credits look lower risk.' : '');
    }

    /** The filing this review is about: the triggering import's latest row, else the latest annual return. */
    private function anchor(TaxpayerSnapshot $s, ?SourceImport $import): array
    {
        $candidates = $import
            ? collect([$s->cit, $s->vat, $s->wht, $s->lekuka, $s->customs, $s->paye, $s->refunds])->flatten()
                ->where('source_import_id', $import->id)
            : ($s->cit->isNotEmpty() ? $s->cit : $s->vat);

        $row = $candidates->sortBy('record_id')->last();
        if (! $row) {
            return [null, null];
        }

        $channel = $row::channel();
        $when = $row->tax_year ?? $row->period ?? $row->record_id;

        return [$row->record_id, $channel->key === 'cit' ? "{$when} income tax return" : "{$channel->label} {$when}"];
    }

    /** @return Collection<string, array{model: PatternRuleModel, version: PatternRuleVersion, class: PatternRule}> */
    private function rules(): Collection
    {
        $classes = RuleCatalogue::classes();

        return PatternRuleModel::query()->where('enabled', true)->with('currentVersion')->orderBy('code')->get()
            ->filter(fn ($r) => $r->currentVersion && isset($classes[$r->code]))
            ->mapWithKeys(fn ($r) => [$r->code => ['model' => $r, 'version' => $r->currentVersion, 'class' => app($classes[$r->code])]]);
    }

    private function rates(): array
    {
        return [
            'vat' => (float) $this->settings->get('tax.vat_rate', 0.15),
            'cit' => (float) $this->settings->get('tax.cit_rate', 0.25),
            'wht_standard' => (float) $this->settings->get('tax.wht_standard_rate', 0.25),
        ];
    }
}
