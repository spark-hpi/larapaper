<?php

use Flux\Flux;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('API Tokens')] class extends Component {
    public string $tokenName = '';

    public string $tokenType = 'api';

    #[Locked]
    public array $tokens = [];

    #[Locked]
    public ?string $newTokenValue = null;

    public bool $showNewTokenModal = false;

    #[Locked]
    public ?int $revokingTokenId = null;

    #[Locked]
    public string $revokingTokenName = '';

    public bool $showRevokeModal = false;

    public function mount(): void
    {
        $this->loadTokens();
    }

    public function loadTokens(): void
    {
        $this->tokens = Auth::user()->tokens()
            ->orderByDesc('created_at')
            ->get()
            ->map(fn ($token) => [
                'id' => $token->id,
                'name' => $token->name,
                'abilities' => $token->abilities ?? [],
                'created_at' => $token->created_at->diffForHumans(),
                'last_used_at' => $token->last_used_at?->diffForHumans(),
            ])
            ->toArray();
    }

    public function createToken(): void
    {
        $this->validate([
            'tokenName' => 'required|string|max:255',
            'tokenType' => 'required|string|in:api,mcp',
        ]);

        $abilities = $this->tokenType === 'mcp'
            ? ['mcp']
            : ['*'];

        $token = Auth::user()->createToken($this->tokenName, $abilities);

        $this->newTokenValue = $token->plainTextToken;
        $this->tokenName = '';
        $this->tokenType = 'api';
        $this->showNewTokenModal = true;
        $this->loadTokens();
    }

    public function confirmRevoke(int $tokenId): void
    {
        $token = Auth::user()->tokens()->findOrFail($tokenId);
        $this->revokingTokenId = $token->id;
        $this->revokingTokenName = $token->name;
        $this->showRevokeModal = true;
    }

    public function revokeToken(): void
    {
        if (! $this->revokingTokenId) {
            return;
        }

        Auth::user()->tokens()->findOrFail($this->revokingTokenId)->delete();

        $this->closeRevokeModal();
        $this->loadTokens();

        Flux::toast(variant: 'success', text: __('Token revoked.'));
    }

    public function closeRevokeModal(): void
    {
        $this->showRevokeModal = false;
        $this->revokingTokenId = null;
        $this->revokingTokenName = '';
    }

    public function closeNewTokenModal(): void
    {
        $this->showNewTokenModal = false;
        $this->newTokenValue = null;
    }
}; ?>

<section class="w-full py-12">
    <div class="mx-auto max-w-7xl sm:px-6 lg:px-8">
        @include('partials.settings-heading')

        <flux:heading class="sr-only">{{ __('API Tokens') }}</flux:heading>

        <x-pages::settings.layout :heading="__('API Tokens')" :subheading="__('Manage tokens for CLI tools and integrations')">

            <form wire:submit="createToken" class="my-6 w-full space-y-4">
                <flux:input
                    wire:model="tokenName"
                    :label="__('Token name')"
                    :placeholder="__('e.g. trmnlp CLI')"
                    required
                    autofocus
                />
                @toggle('mcp')
                    <flux:radio.group wire:model="tokenType" :label="__('Token type')" variant="segmented">
                        <flux:radio value="api" :label="__('API')" />
                        <flux:radio value="mcp" :label="__('MCP')" />
                    </flux:radio.group>
                @endtoggle
                <div>
                    <flux:button variant="primary" type="submit">
                        {{ __('Create token') }}
                    </flux:button>
                </div>
            </form>

            @if (count($tokens) > 0)
                <section class="mt-8">
                    <flux:heading>{{ __('Manage tokens') }}</flux:heading>
                    <flux:subheading>You may delete any of your existing tokens if they are no longer needed.</flux:subheading>

                    <div class="mt-4 border rounded-lg border-zinc-200 dark:border-zinc-700 overflow-hidden">
                        @foreach ($tokens as $token)
                            <div class="flex items-center justify-between p-4 {{ ! $loop->last ? 'border-b border-zinc-200 dark:border-zinc-700' : '' }}">
                                <div class="flex items-center gap-4">
                                    <div class="flex size-10 shrink-0 items-center justify-center rounded-xl bg-zinc-100 dark:bg-zinc-800">
                                        <flux:icon.key class="size-5 text-zinc-500 dark:text-zinc-400" />
                                    </div>
                                    <div class="space-y-0.5">
                                        <p class="font-medium tracking-tight">{{ $token['name'] }}</p>
                                        @if (count($token['abilities']) > 0)
                                            <div class="flex flex-wrap gap-1.5 mt-1">
                                                @foreach ($token['abilities'] as $ability)
                                                    <flux:badge size="sm" color="zinc">{{ $ability }}</flux:badge>
                                                @endforeach
                                            </div>
                                        @endif
                                        <p class="text-zinc-500 dark:text-zinc-400 text-xs">
                                            {{ __('Created :time', ['time' => $token['created_at']]) }}
                                            @if ($token['last_used_at'])
                                                <span class="opacity-50 mx-1">/</span>
                                                {{ __('Last used :time', ['time' => $token['last_used_at']]) }}
                                            @else
                                                <span class="opacity-50 mx-1">/</span>
                                                {{ __('Never used') }}
                                            @endif
                                        </p>
                                    </div>
                                </div>

                                <flux:button
                                    variant="ghost"
                                    size="sm"
                                    icon="trash"
                                    icon:variant="outline"
                                    wire:click="confirmRevoke({{ $token['id'] }})"
                                    class="text-red-500 hover:text-red-600 hover:bg-red-50 dark:hover:bg-red-950/50"
                                />
                            </div>
                        @endforeach
                    </div>
                </section>
            @endif

        </x-pages::settings.layout>

        {{-- New token modal --}}
        <flux:modal
            name="new-token-modal"
            class="max-w-md md:min-w-md"
            @close="$wire.closeNewTokenModal()"
            wire:model="showNewTokenModal"
        >
            <div class="space-y-6">
                <div class="space-y-2">
                    <flux:heading size="lg">{{ __('Token created') }}</flux:heading>
                    <flux:text>
                        {{ __('Copy your token now — it will not be shown again.') }}
                    </flux:text>
                </div>

                <flux:input
                    value="{{ $newTokenValue }}"
                    readonly
                    copyable
                    class="font-mono text-sm"
                />

                <div class="flex justify-end">
                    <flux:button variant="primary" wire:click="closeNewTokenModal">
                        {{ __('Done') }}
                    </flux:button>
                </div>
            </div>
        </flux:modal>

        {{-- Revoke confirmation modal --}}
        <flux:modal
            name="revoke-token-modal"
            class="max-w-md md:min-w-md"
            @close="$wire.closeRevokeModal()"
            wire:model="showRevokeModal"
        >
            <div class="space-y-6">
                <div class="space-y-2">
                    <flux:heading size="lg">{{ __('Revoke token') }}</flux:heading>
                    <flux:text>
                        {{ __('Are you sure you want to revoke ":name"? Any tools using it will stop working immediately.', ['name' => $revokingTokenName]) }}
                    </flux:text>
                </div>

                <div class="flex gap-3 justify-end">
                    <flux:button variant="outline" wire:click="closeRevokeModal">
                        {{ __('Cancel') }}
                    </flux:button>
                    <flux:button variant="danger" wire:click="revokeToken">
                        {{ __('Revoke token') }}
                    </flux:button>
                </div>
            </div>
        </flux:modal>
    </div>
</section>
