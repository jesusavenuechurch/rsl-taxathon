<?php

namespace App\Livewire\Records;

use App\Domain\Audit\AuditLog;
use App\Domain\Records\RecordRegistry;
use App\Models\SourceRecords\SourceRecord;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Component;

/** Any source row by its ID, exactly as received, with its versions and provenance. */
#[Layout('components.layouts.app')]
class RecordShow extends Component
{
    public string $recordId;

    #[Url(as: 'v')]
    public ?int $version = null;

    public function mount(string $recordId, RecordRegistry $records, AuditLog $audit): void
    {
        Gate::authorize('view_company_records');

        $this->recordId = strtoupper($recordId);
        $record = $records->find($this->recordId, $this->version);
        abort_if(! $record, 404);

        $audit->record('opened', subjectType: 'source_record', subjectRef: $record->record_id,
            payload: ['version' => $record->version], taxpayerTin: $record->tin());
    }

    public function render(RecordRegistry $records)
    {
        /** @var SourceRecord $record */
        $record = $records->find($this->recordId, $this->version);
        $record->load(['sourceImport', 'taxpayer']);

        return view('livewire.records.record-show', [
            'record' => $record,
            'channel' => $record::channel(),
            'versions' => $record->versions(),
        ])->title($record->record_id);
    }
}
