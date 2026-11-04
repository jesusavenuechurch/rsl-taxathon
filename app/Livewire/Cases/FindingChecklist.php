<?php

namespace App\Livewire\Cases;

use App\Domain\Audit\AuditLog;
use App\Models\Analysis\Finding;
use App\Models\Analysis\FindingChecklistItem;
use App\Support\Clock;
use Livewire\Component;

/**
 * A finding's lawful explanations and what the officer must establish, as
 * tickable items with a note each (officer-desk.md section 4). Never rewrites
 * the finding itself — only the checklist's own status and note.
 */
class FindingChecklist extends Component
{
    public Finding $finding;

    public bool $canAct = false;

    /** @var array<int, string> item id => draft note text */
    public array $notes = [];

    public function mount(Finding $finding, bool $canAct = false): void
    {
        $this->finding = $finding;
        $this->canAct = $canAct;
        foreach ($finding->checklist as $item) {
            $this->notes[$item->id] = (string) ($item->note ?? '');
        }
    }

    public function toggle(int $itemId, Clock $clock, AuditLog $audit): void
    {
        abort_unless($this->canAct, 403);
        $item = $this->item($itemId);

        $resolved = $item->status !== FindingChecklistItem::RESOLVED;
        $item->update([
            'status' => $resolved ? FindingChecklistItem::RESOLVED : FindingChecklistItem::OPEN,
            'resolved_by' => $resolved ? auth()->id() : null,
            'resolved_at' => $resolved ? $clock->now() : null,
        ]);

        $audit->record('checklist_item_resolved', subjectType: 'finding_checklist_item', subjectRef: (string) $item->id,
            taxpayerTin: $this->finding->tin, payload: ['finding' => $this->finding->reference, 'status' => $item->status]);
    }

    public function saveNote(int $itemId, AuditLog $audit): void
    {
        abort_unless($this->canAct, 403);
        $item = $this->item($itemId);
        $text = trim($this->notes[$itemId] ?? '');

        $item->update(['note' => $text === '' ? null : $text]);
        $audit->record('checklist_item_noted', subjectType: 'finding_checklist_item', subjectRef: (string) $item->id,
            taxpayerTin: $this->finding->tin, payload: ['finding' => $this->finding->reference]);
    }

    private function item(int $itemId): FindingChecklistItem
    {
        $item = $this->finding->checklist->firstWhere('id', $itemId);
        abort_if(! $item, 404);

        return $item;
    }

    public function render()
    {
        return view('livewire.cases.finding-checklist');
    }
}
