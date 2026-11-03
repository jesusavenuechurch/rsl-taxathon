<?php

namespace App\Livewire\Admin;

use App\Domain\Records\AlreadyImported;
use App\Domain\Records\Channels;
use App\Domain\Records\CsvImporter;
use App\Domain\Records\DemoIntake;
use App\Domain\Records\ImportFailed;
use App\Jobs\AnalyseTaxpayers;
use App\Models\SourceImport;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Validate;
use Livewire\Component;
use Livewire\WithFileUploads;

/** Where records enter the system without the command line. */
#[Layout('components.layouts.app')]
#[Title('Data intake')]
class DataIntake extends Component
{
    use WithFileUploads;

    public string $tin = '';

    public string $channel = '';

    #[Validate('required|file|mimes:csv,txt|max:10240')]
    public $file;

    /** @var array<string, mixed>|null */
    public ?array $result = null;

    public ?string $error = null;

    public function mount(): void
    {
        Gate::authorize('receive_data');
    }

    public function receiveCompany(DemoIntake $intake): void
    {
        Gate::authorize('receive_data');
        $this->validate(['tin' => 'required|in:'.implode(',', array_keys(DemoIntake::mockCompanies()))]);

        $done = $intake->receiveCompany($this->tin);
        $this->error = null;
        $this->result = [
            'title' => DemoIntake::mockCompanies()[$this->tin].': records received',
            'lines' => [
                $done['rows'] ? "{$done['rows']} new rows from ".implode(', ', $done['channels']).'.' : 'Nothing new: these records had already arrived.',
                $done['findings'] ? 'Analysis raised: '.implode('; ', $done['findings']).'.' : 'Analysis found nothing unusual; the filing review is saved and no case is opened.',
            ],
            'case' => $done['case'] ? ['reference' => $done['case']->reference, 'team' => $done['case']->team->name] : null,
        ];
    }

    public function receiveEverything(DemoIntake $intake): void
    {
        Gate::authorize('receive_data');
        $count = $intake->receiveEverything();
        $this->error = null;
        $this->result = ['title' => 'All mock records received', 'lines' => ["{$count} taxpayers analysed."], 'case' => null];
    }

    public function upload(CsvImporter $importer): void
    {
        Gate::authorize('receive_data');
        $this->validate(['channel' => 'required|in:'.implode(',', array_keys(Channels::all()))]);
        $this->validateOnly('file');

        try {
            $import = $importer->import(Channels::get($this->channel), $this->file->getRealPath(), filename: $this->file->getClientOriginalName());
            AnalyseTaxpayers::dispatchSync($this->touched($import), 'import', $import->id);
            $this->error = null;
            $this->result = [
                'title' => "{$import->channelDefinition()->label} received",
                'lines' => ["{$import->rows_total} rows: {$import->rows_new} new, {$import->rows_revised} revised, {$import->rows_unchanged} unchanged.", ...$import->warnings],
                'case' => null,
            ];
        } catch (AlreadyImported|ImportFailed $e) {
            $this->result = null;
            $this->error = $e->getMessage();
        }
        $this->reset('file');
    }

    /** Taxpayers with rows in this import. */
    private function touched(SourceImport $import): array
    {
        $channel = $import->channelDefinition();
        if (! $channel->tinAttribute) {
            return [];
        }

        return $channel->model::where('source_import_id', $import->id)->distinct()->pluck($channel->tinAttribute)->all();
    }

    public function render()
    {
        return view('livewire.admin.data-intake', [
            'companies' => DemoIntake::mockCompanies(),
            'channels' => Channels::all(),
            'imports' => SourceImport::latest('id')->limit(12)->get(),
        ]);
    }
}
