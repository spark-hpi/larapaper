<?php

use App\Models\Plugin;
use App\Plugins\PluginHandler;
use App\Plugins\PluginRegistry;
use Illuminate\Support\Str;
use Livewire\Attributes\Locked;
use Livewire\Component;

new class extends Component
{
    #[Locked]
    public string $type = '';

    public string $name = '';

    public array $instances = [];

    protected $rules = [
        'name' => 'required|string|max:255',
    ];

    public function mount(string $type): void
    {
        $handler = app(PluginRegistry::class)->get($type);
        abort_if($handler === null || ! $handler->hasInstances(), 404);

        $this->type = $type;
        $this->refreshInstances();
    }

    public function getHandlerProperty(): PluginHandler
    {
        return app(PluginRegistry::class)->get($this->type);
    }

    public function refreshInstances(): void
    {
        $this->instances = auth()->user()
            ->plugins()
            ->where('plugin_type', $this->type)
            ->orderBy('created_at', 'desc')
            ->get()
            ->toArray();
    }

    public function createInstance(): void
    {
        abort_unless(auth()->user() !== null, 403);
        $this->validate();

        Plugin::create([
            'uuid' => Str::uuid(),
            'user_id' => auth()->id(),
            'name' => $this->name,
            'plugin_type' => $this->type,
            ...$this->handler->defaultAttributes(),
        ]);

        $this->reset(['name']);
        $this->refreshInstances();

        Flux::modal('create-instance')->close();
    }

    public function deleteInstance(int $pluginId): void
    {
        abort_unless(auth()->user() !== null, 403);

        $plugin = Plugin::where('id', $pluginId)
            ->where('user_id', auth()->id())
            ->where('plugin_type', $this->type)
            ->firstOrFail();

        $plugin->delete();
        $this->refreshInstances();
    }
};
?>

<div class="py-12">
    <div class="mx-auto max-w-7xl sm:px-6 lg:px-8">
        <div class="mb-6 flex items-center justify-between">
            <h2 class="text-2xl font-semibold dark:text-gray-100">
                {{ $this->handler->name() }}
                <flux:badge size="sm" class="ml-2">Plugin</flux:badge>
            </h2>
            <flux:modal.trigger name="create-instance">
                <flux:button icon="plus" variant="primary">Create Instance</flux:button>
            </flux:modal.trigger>
        </div>

        <flux:modal name="create-instance" class="md:w-96">
            <div class="space-y-6">
                <div>
                    <flux:heading size="lg">Create {{ $this->handler->name() }} Instance</flux:heading>
                    <flux:subheading>{{ $this->handler->description() }}</flux:subheading>
                </div>

                <form wire:submit="createInstance">
                    <div class="mb-4">
                        <flux:input
                            label="Name"
                            wire:model="name"
                            id="name"
                            class="mt-1 block w-full"
                            type="text"
                            name="name"
                            autofocus
                        />
                    </div>

                    <div class="flex">
                        <flux:spacer />
                        <flux:button type="submit" variant="primary">Create Instance</flux:button>
                    </div>
                </form>
            </div>
        </flux:modal>

        @if (empty($instances))
            <div class="py-12 text-center">
                <flux:callout>
                    <flux:heading size="sm">No instances yet</flux:heading>
                    <flux:text>Create your first {{ $this->handler->name() }} instance to get started.</flux:text>
                </flux:callout>
            </div>
        @else
            <table
                class="min-w-full table-auto divide-y divide-zinc-800/10 text-zinc-800 dark:divide-white/20"
                data-flux-table=""
            >
                <thead data-flux-columns="">
                    <tr>
                        <th
                            class="px-3 py-3 text-left text-sm font-medium text-zinc-800 first:pl-0 last:pr-0 dark:text-white"
                            data-flux-column=""
                        >
                            <div class="flex whitespace-nowrap">Name</div>
                        </th>
                        <th
                            class="px-3 py-3 text-right text-sm font-medium text-zinc-800 first:pl-0 last:pr-0 dark:text-white"
                            data-flux-column=""
                        >
                            <div class="flex justify-end whitespace-nowrap">Actions</div>
                        </th>
                    </tr>
                </thead>

                <tbody class="divide-y divide-zinc-800/10 dark:divide-white/20" data-flux-rows="">
                    @foreach ($instances as $instance)
                        <tr data-flux-row="">
                            <td class="px-3 py-3 text-sm whitespace-nowrap text-zinc-500 first:pl-0 last:pr-0 dark:text-zinc-300">
                                {{ $instance['name'] }}
                            </td>
                            <td class="px-3 py-3 text-right text-sm font-medium whitespace-nowrap text-zinc-800 first:pl-0 last:pr-0 dark:text-white">
                                <div class="flex items-center justify-end">
                                    <flux:button.group>
                                        <flux:button
                                            href="{{ route('plugins.type-instance', ['type' => $type, 'plugin' => $instance['id']]) }}"
                                            wire:navigate
                                            icon="pencil"
                                            iconVariant="outline"
                                        >
                                        </flux:button>
                                        <flux:modal.trigger name="delete-instance-{{ $instance['id'] }}">
                                            <flux:button icon="trash" iconVariant="outline"> </flux:button>
                                        </flux:modal.trigger>
                                    </flux:button.group>
                                </div>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        @endif

        @foreach ($instances as $instance)
            <flux:modal name="delete-instance-{{ $instance['id'] }}" class="min-w-88 space-y-6">
                <div>
                    <flux:heading size="lg">Delete {{ $instance['name'] }}?</flux:heading>
                    <p class="mt-2 text-sm text-zinc-600 dark:text-zinc-400">
                        This will also remove this instance from your playlists.
                    </p>
                </div>

                <div class="flex gap-2">
                    <flux:spacer />
                    <flux:modal.close>
                        <flux:button variant="ghost">Cancel</flux:button>
                    </flux:modal.close>
                    <flux:button wire:click="deleteInstance({{ $instance['id'] }})" variant="danger"
                        >Delete instance</flux:button>
                </div>
            </flux:modal>
        @endforeach
    </div>
</div>
