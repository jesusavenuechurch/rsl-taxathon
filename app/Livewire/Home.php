<?php

namespace App\Livewire;

use App\Domain\Records\Channel;
use App\Domain\Records\Channels;
use App\Models\Analysis\Finding;
use App\Models\SourceImport;
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
            'openFindings' => Finding::open()->count(),
            'companiesWithFindings' => Finding::open()->distinct()->count('tin'),
            'channels' => collect(Channels::all())->map(fn (Channel $channel) => [
                'channel' => $channel,
                'count' => $channel->model::query()->current()->count(),
                'last' => SourceImport::where('channel', $channel->key)->latest('id')->first(),
            ])->values(),
        ]);
    }
}
