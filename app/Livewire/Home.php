<?php

namespace App\Livewire;

use App\Support\Roles;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('components.layouts.app')]
#[Title('Home')]
class Home extends Component
{
    public function render()
    {
        $user = auth()->user()->load('team');

        return view('livewire.home', [
            'user' => $user,
            'roles' => $user->getRoleNames()->map(Roles::label(...))->implode(', '),
        ]);
    }
}
