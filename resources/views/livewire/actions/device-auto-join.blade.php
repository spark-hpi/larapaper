<div>
    @if ($canToggle)
        <flux:toggle
            wire:model.live="deviceAutojoin"
            size="sm"
            off:icon="link-slash"
            off:label="Auto-Join Disabled"
            on:icon="link"
            on:label="Auto-Join Permitted"
            tooltip="Add devices automatically that try to connect to this server"
        />
    @endif
</div>
