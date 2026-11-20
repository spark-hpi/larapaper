<?php

namespace App\Livewire\Actions;

use App\Models\User;
use Illuminate\Support\Carbon;
use Livewire\Attributes\On;
use Livewire\Component;

class DeviceAutoJoin extends Component
{
    public bool $deviceAutojoin = false;

    /**
     * Unix timestamp at which the Auto-Join window closes, or null when closed.
     */
    public ?int $autoJoinExpiresAt = null;

    /**
     * Server unix timestamp at the last sync, used by the countdown to correct
     * for client clock skew.
     */
    public int $serverNow = 0;

    public function mount(): void
    {
        $this->syncState();
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

        if ((bool) $value) {
            auth()->user()->enableAutoJoin();
        } else {
            // Auto-Join is server-wide: closing it closes every admin's window.
            User::withActiveAutoJoin()->update(['assign_new_devices_until' => null]);
        }

        $this->syncState();

        $this->dispatch('device-auto-join-changed');
    }

    /**
     * Reload the server-wide Auto-Join state. Also called by the countdown
     * once it reaches zero.
     */
    #[On('device-auto-join-changed')]
    public function syncState(): void
    {
        $expiresAt = User::withActiveAutoJoin()->max('assign_new_devices_until');

        $this->deviceAutojoin = $expiresAt !== null;
        $this->autoJoinExpiresAt = $expiresAt !== null ? Carbon::parse($expiresAt)->timestamp : null;
        $this->serverNow = now()->timestamp;
    }

    public function render(): \Illuminate\Contracts\View\View|\Illuminate\Contracts\View\Factory
    {
        return view('livewire.actions.device-auto-join', [
            'canToggle' => (bool) auth()->user()?->isAdmin(),
        ]);
    }
}
