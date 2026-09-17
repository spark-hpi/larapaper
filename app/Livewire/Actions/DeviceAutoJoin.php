<?php

namespace App\Livewire\Actions;

use App\Models\User;
use Livewire\Attributes\On;
use Livewire\Component;

class DeviceAutoJoin extends Component
{
    public bool $deviceAutojoin = false;

    public function mount(): void
    {
        $this->deviceAutojoin = User::where('assign_new_devices', true)->exists();
    }

    public function updating(string $name, mixed $value): void
    {
        if ($name !== 'deviceAutojoin') {
            return;
        }

        abort_unless(auth()->user()?->isAdmin(), 403);

        $this->validate([
            'deviceAutojoin' => 'boolean',
        ]);

        $enabled = (bool) $value;

        if ($enabled) {
            auth()->user()->update(['assign_new_devices' => true]);
        } else {
            User::where('assign_new_devices', true)->update(['assign_new_devices' => false]);
        }

        $this->dispatch('device-auto-join-changed', enabled: $enabled);
    }

    #[On('device-auto-join-changed')]
    public function syncFromSibling(bool $enabled): void
    {
        $this->deviceAutojoin = $enabled;
    }

    public function render(): \Illuminate\Contracts\View\View|\Illuminate\Contracts\View\Factory
    {
        return view('livewire.actions.device-auto-join', [
            'canToggle' => (bool) auth()->user()?->isAdmin(),
        ]);
    }
}
