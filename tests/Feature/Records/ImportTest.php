<?php

use App\Domain\Records\AlreadyImported;
use App\Domain\Records\Channels;
use App\Domain\Records\ImportFailed;
use App\Domain\Records\RecordRegistry;
use App\Events\SourceRecordsImported;
use App\Models\AuditEvent;
use App\Models\SourceImport;
use App\Models\SourceRecords\CitReturn;
use App\Models\SourceRecords\GroupEntity;
use App\Models\SourceRecords\RefundClaim;
use App\Models\SourceRecords\VatReturn;
use App\Models\SourceRecords\WhtPayment;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Schema;

it('has a column for every field of every channel', function () {
    foreach (Channels::all() as $channel) {
        $table = (new $channel->model)->getTable();
        $columns = array_map(fn ($f) => $f->attribute, $channel->fields);

        expect(Schema::hasColumns($table, $columns))->toBeTrue("{$table} is missing a column");
    }
});

it('receives every row of every channel file', function () {
    importAll();

    foreach (Channels::all() as $channel) {
        $lines = count(file(RSL_DATA.'/'.$channel->file, FILE_SKIP_EMPTY_LINES)) - 1;
        expect($channel->model::current()->count())->toBe($lines, $channel->key);
    }
});

it('keeps every figure exactly as filed', function () {
    importAll();

    $cit = CitReturn::current()->where('record_id', 'CIT-00036')->sole();
    expect($cit->tin)->toBe('TN1000985-5')
        ->and($cit->tax_year)->toBe('2025/26')
        ->and($cit->related_party_interest)->toBe(3_000_000)
        ->and($cit->net_profit_before_tax)->toBe(-3_510_000)
        ->and($cit->related_party_loans_closing)->toBe(29_000_000)
        ->and($cit->financial_statements_attached)->toBeTrue()
        ->and($cit->filed_date->toDateString())->toBe('2026-09-12');

    $vat = VatReturn::current()->where('record_id', 'VAT-00145')->sole();
    expect($vat->input_vat_local)->toBe(519_380)
        ->and($vat->capture_channel)->toBe('Manual capture from paper return');
});

it('splits refund letters into their VAT periods', function () {
    importAll();

    expect(RefundClaim::current()->where('record_id', 'RF-0001')->sole()->periods->pluck('period')->all())
        ->toBe(['2025-04', '2025-05', '2025-06'])
        ->and(RefundClaim::current()->where('record_id', 'RF-0007')->sole()->periods->pluck('period')->all())
        ->toBe(['2026-10']);
});

it('lets withholding tax payees be matched to the group register by name', function () {
    importAll();

    $payment = WhtPayment::current()->where('record_id', 'WHT-00119')->sole();
    $entity = GroupEntity::current()->where('normalized_name', $payment->payee_normalized_name)->sole();

    expect($entity->record_id)->toBe('GRP-00009')
        ->and($entity->low_tax_jurisdiction)->toBeTrue()
        ->and($entity->dta_with_lesotho)->toBeFalse();
});

it('logs each file as received and announces the taxpayers it touched', function () {
    Event::fake([SourceRecordsImported::class]);

    importChannel('companies');
    importChannel('cit');

    $event = AuditEvent::where('action', 'received')->where('payload->channel', 'cit')->sole();
    expect($event->payload['rows'])->toBe(54)
        ->and($event->payload['sha256'])->toBe(hash_file('sha256', RSL_DATA.'/cit_returns.csv'));
    Event::assertDispatched(SourceRecordsImported::class, fn ($e) => $e->import->channel === 'cit' && count($e->tins) === 14);
});

it('skips a file it has already received', function () {
    importChannel('cit');

    expect(fn () => importChannel('cit'))->toThrow(AlreadyImported::class);
});

it('creates no versions when a forced re-import has no changes', function () {
    importChannel('cit');
    $import = importChannel('cit', force: true);

    expect($import->rows_unchanged)->toBe(54)
        ->and($import->rows_new + $import->rows_revised)->toBe(0)
        ->and(CitReturn::count())->toBe(54);
});

it('keeps the original when a corrected figure arrives', function () {
    Event::fake([SourceRecordsImported::class]);
    importAll();

    // Lebelo's mis-keyed input VAT, corrected by the data team.
    $import = importChannel('vat', editedCopy('vat_returns.csv',
        fn ($csv) => str_replace('VAT-00145,TN1000872-0,2026-03,2026-04-20,2026-04-20,977260,244310,0,146590,0,0,346250,519380',
            'VAT-00145,TN1000872-0,2026-03,2026-04-20,2026-04-20,977260,244310,0,146590,0,0,346250,51938', $csv)));

    expect($import->rows_revised)->toBe(1)->and($import->rows_unchanged)->toBe(265);

    $registry = app(RecordRegistry::class);
    expect($registry->find('VAT-00145')->input_vat_local)->toBe(51_938)
        ->and($registry->find('VAT-00145')->version)->toBe(2)
        ->and($registry->find('VAT-00145', 1)->input_vat_local)->toBe(519_380)
        ->and($registry->find('VAT-00145', 1)->isCurrent())->toBeFalse();

    Event::assertDispatched(SourceRecordsImported::class, fn ($e) => $e->import->is($import) && $e->tins === ['TN1000872-0']);
});

it('never edits or deletes a received row', function () {
    importChannel('cit');
    $row = CitReturn::first();

    expect(fn () => $row->update(['revenue' => 1]))->toThrow(LogicException::class)
        ->and(fn () => $row->delete())->toThrow(LogicException::class);
});

it('rejects a whole file when one row is invalid', function (string $file, string $from, string $to, string $message) {
    $channel = collect(Channels::all())->firstWhere('file', $file);

    expect(fn () => importChannel($channel->key, editedCopy($file, fn ($csv) => preg_replace('/'.preg_quote($from, '/').'/', $to, $csv, 1))))
        ->toThrow(ImportFailed::class, $message);

    expect($channel->model::count())->toBe(0)->and(SourceImport::count())->toBe(0);
})->with([
    'not a number' => ['cit_returns.csv', ',42000000,', ',42,000,000,', 'expected 23 values'],
    'bad amount' => ['cit_returns.csv', ',42000000,', ',42000000.50,', 'not a whole number'],
    'wrong prefix' => ['cit_returns.csv', 'CIT-00036', 'VAT-00036', 'does not belong'],
    'duplicate ID' => ['cit_returns.csv', 'CIT-00036', 'CIT-00035', 'more than once'],
    'bad period' => ['vat_returns.csv', ',2026-03,', ',2026-13,', 'not a period'],
    'bad yes/no' => ['companies.csv', ',Yes,', ',Maybe,', 'expected Yes or No'],
    'missing column' => ['paye_returns.csv', 'fbt_paid', 'fbt', 'Missing column(s): fbt_paid'],
    'value too long' => ['vat_returns.csv', 'Credit (refund due)', 'Credit (refund due) as per letter of 28 Nov', 'longer than 32 characters'],
]);

it('declares column sizes the mock data fits in', function () {
    foreach (Channels::all() as $channel) {
        $handle = fopen(RSL_DATA.'/'.$channel->file, 'r');
        $header = array_map(fn ($h) => preg_replace('/^\xEF\xBB\xBF/', '', $h), fgetcsv($handle, escape: ''));
        while (($row = fgetcsv($handle, escape: '')) !== false) {
            foreach ($channel->fields as $field) {
                $value = array_combine($header, $row)[$field->column];
                if ($field->limit() !== null) {
                    expect(mb_strlen($value))->toBeLessThanOrEqual($field->limit(), "{$channel->file} {$field->column}");
                }
            }
        }
        fclose($handle);
    }
});

it('warns about rows dated after today and unregistered TINs, but still receives them', function () {
    $vat = importChannel('vat'); // before companies: no TIN is registered yet

    expect($vat->warnings)->toHaveCount(2)
        ->and($vat->warnings[0])->toContain('266 row(s) for a TIN not registered')
        ->and($vat->warnings[1])->toContain('14 row(s) with filed_date after today (2026-11-02)')
        ->and(VatReturn::count())->toBe(266);
});

it('imports everything from the console and skips repeats', function () {
    $this->artisan('rsl:import --all')
        ->expectsOutputToContain('Income tax return (cit_returns.csv): 54 rows, 54 new')
        ->expectsOutputToContain('3 row(s) with letter_date after today')
        ->assertSuccessful();

    $this->artisan('rsl:import cit')->expectsOutputToContain('skipped')->assertSuccessful();
});
