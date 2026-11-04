<?php

namespace App\Livewire\Admin;

use App\Models\NotificationDelivery;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

/** So a judge can see what went out, and that no taxpayer detail did (officer-desk.md section 7). */
#[Layout('components.layouts.app')]
#[Title('Notifications sent')]
class NotificationsSent extends Component
{
    public function mount(): void
    {
        Gate::authorize('view_audit_log');
    }

    public function render()
    {
        return view('livewire.admin.notifications-sent', [
            'deliveries' => NotificationDelivery::with('recipient')->latest('id')->limit(200)->get(),
        ]);
    }
}
