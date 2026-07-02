<?php

namespace App\Livewire\Actions;

use App\Models\User;
use Livewire\Component;

class DeviceAutoJoin extends Component
{
    public bool $deviceAutojoin = false;

    public function mount(): void
    {
        $this->deviceAutojoin = User::where('assign_new_devices', true)->exists();
    }

    public function updating($name, $value): void
    {
        abort_unless(auth()->user()?->isAdmin(), 403);

        $this->validate([
            'deviceAutojoin' => 'boolean',
        ]);

        if ($name === 'deviceAutojoin') {
            if ($value) {
                auth()->user()->update(['assign_new_devices' => true]);
            } else {
                User::where('assign_new_devices', true)->update(['assign_new_devices' => false]);
            }
        }
    }

    public function render(): \Illuminate\Contracts\View\View|\Illuminate\Contracts\View\Factory
    {
        return view('livewire.actions.device-auto-join', [
            'canToggle' => (bool) auth()->user()?->isAdmin(),
        ]);
    }
}
