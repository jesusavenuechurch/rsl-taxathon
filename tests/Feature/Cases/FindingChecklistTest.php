<?php

use App\Domain\Analysis\AnalysisEngine;
use App\Livewire\Cases\FindingChecklist;
use App\Models\AuditEvent;
use App\Models\Cases\ReviewCase;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Livewire\Livewire;

beforeEach(function () {
    config(['rsl.seed_demo_users' => true, 'rsl.demo_user_password' => 'x']);
    $this->seed(DatabaseSeeder::class);
    importAll();
    app(AnalysisEngine::class)->run();
    $this->analyst = User::where('email', 'l.thamae@rsl.test')->first();
    $this->specialist = User::where('email', 'p.mokoena@rsl.test')->first();
    $this->thaba = ReviewCase::where('tin', 'TN1000430-6')->sole();
    $this->finding = $this->thaba->findings->first();
});

it('shows lawful explanations and must-establish items as a checklist on the case page', function () {
    $this->actingAs($this->analyst)->get("/cases/{$this->thaba->reference}")->assertOk()
        ->assertSee('Lawful explanations to weigh')->assertSee('What you must establish');
});

it('lets the holding team tick a checklist item and note it, audited', function () {
    $item = $this->finding->checklist->first();

    Livewire::actingAs($this->analyst)->test(FindingChecklist::class, ['finding' => $this->finding, 'canAct' => true])
        ->call('toggle', $item->id)
        ->set("notes.{$item->id}", 'Checked the agreement, fees match market rate.')
        ->call('saveNote', $item->id);

    $fresh = $item->fresh();
    expect($fresh->status)->toBe('resolved')
        ->and($fresh->resolved_by)->toBe($this->analyst->id)
        ->and($fresh->note)->toBe('Checked the agreement, fees match market rate.')
        ->and(AuditEvent::where('action', 'checklist_item_resolved')->where('subject_ref', (string) $item->id)->exists())->toBeTrue()
        ->and(AuditEvent::where('action', 'checklist_item_noted')->where('subject_ref', (string) $item->id)->exists())->toBeTrue();

    // toggling again clears it
    Livewire::actingAs($this->analyst)->test(FindingChecklist::class, ['finding' => $this->finding, 'canAct' => true])
        ->call('toggle', $item->id);
    expect($item->fresh()->status)->toBe('open');
});

it('keeps a team that does not hold the case from ticking its checklist', function () {
    $item = $this->finding->checklist->first();

    Livewire::actingAs($this->specialist)->test(FindingChecklist::class, ['finding' => $this->finding, 'canAct' => false])
        ->call('toggle', $item->id)->assertStatus(403);

    expect($item->fresh()->status)->toBe('open');
});
