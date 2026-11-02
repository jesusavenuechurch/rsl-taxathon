<?php

namespace App\Livewire\Records;

use App\Domain\Records\RecordRegistry;
use Livewire\Component;

class RecordLookup extends Component
{
    public string $recordId = '';

    public function open(RecordRegistry $records)
    {
        $id = strtoupper(trim($this->recordId));

        if ($id === '' || ! $records->find($id)) {
            $this->addError('recordId', "No record with ID \"{$this->recordId}\".");

            return null;
        }

        return $this->redirectRoute('records.show', ['recordId' => $id]);
    }

    public function render()
    {
        return view('livewire.records.record-lookup');
    }
}
