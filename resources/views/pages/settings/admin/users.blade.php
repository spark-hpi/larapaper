<?php

use App\Models\User;
use Flux\Flux;
use Livewire\Component;

new class extends Component
{
    public function mount(): void
    {
        abort_unless(config('app.multi_user_mode') && auth()->user()->isAdmin(), 403);
    }

    public function confirmUser(int $userId): void
    {
        abort_unless(auth()->user()->isAdmin(), 403);

        User::findOrFail($userId)->update(['confirmed_at' => now()]);

        Flux::toast(variant: 'success', text: 'User confirmed.');
    }

    public function revokeUser(int $userId): void
    {
        abort_unless(auth()->user()->isAdmin(), 403);
        abort_if($userId === auth()->id(), 403, 'Cannot revoke yourself.');

        User::findOrFail($userId)->update(['confirmed_at' => null]);

        Flux::toast(variant: 'success', text: 'User confirmation revoked.');
    }

    public function makeAdmin(int $userId): void
    {
        abort_unless(auth()->user()->isAdmin(), 403);

        User::findOrFail($userId)->update(['is_admin' => true]);

        Flux::toast(variant: 'success', text: 'User promoted to admin.');
    }

    public function revokeAdmin(int $userId): void
    {
        abort_unless(auth()->user()->isAdmin(), 403);
        abort_if($userId === 1, 403, 'Cannot remove admin from the primary admin.');
        abort_if($userId === auth()->id(), 403, 'Cannot remove your own admin status.');

        User::findOrFail($userId)->update(['is_admin' => false]);

        Flux::toast(variant: 'success', text: 'Admin status removed.');
    }

    public function deleteUser(int $userId): void
    {
        abort_unless(auth()->user()->isAdmin(), 403);
        abort_if($userId === auth()->id(), 403, 'Cannot delete yourself.');
        abort_if($userId === 1, 403, 'Cannot delete the primary admin.');

        User::findOrFail($userId)->delete();

        Flux::toast(variant: 'success', text: 'User deleted.');
    }

    /**
     * @return array<string, mixed>
     */
    public function with(): array
    {
        return [
            'users' => User::orderBy('confirmed_at')->orderBy('created_at')->get(),
        ];
    }
};
?>

<section class="w-full py-12">
    <div class="mx-auto max-w-7xl sm:px-6 lg:px-8">
        @include('partials.settings-heading')

        <x-pages::settings.layout heading="User Management" subheading="Confirm accounts and manage admin access.">
            <table class="min-w-full table-auto divide-y divide-zinc-800/10 dark:divide-white/20" data-flux-table>
                <thead data-flux-columns>
                <tr>
                    <th class="py-3 px-3 first:pl-0 text-left text-sm font-medium text-zinc-800 dark:text-white" data-flux-column>Name</th>
                    <th class="py-3 px-3 text-left text-sm font-medium text-zinc-800 dark:text-white" data-flux-column>Email</th>
                    <th class="py-3 px-3 text-left text-sm font-medium text-zinc-800 dark:text-white" data-flux-column>Status</th>
                    <th class="py-3 px-3 text-left text-sm font-medium text-zinc-800 dark:text-white" data-flux-column>Role</th>
                    <th class="py-3 px-3 text-left text-sm font-medium text-zinc-800 dark:text-white" data-flux-column>Joined</th>
                    <th class="py-3 px-3 last:pr-0 text-right text-sm font-medium text-zinc-800 dark:text-white" data-flux-column>
                        <span class="sr-only">Actions</span>
                    </th>
                </tr>
                </thead>
                <tbody class="divide-y divide-zinc-800/10 dark:divide-white/20" data-flux-rows>
                @foreach ($users as $user)
                    <tr data-flux-row wire:key="user-{{ $user->id }}">
                        <td class="py-3 px-3 first:pl-0 text-sm text-zinc-800 dark:text-zinc-200 font-medium">
                            {{ $user->name }}
                            @if ($user->id === auth()->id())
                                <span class="text-xs text-zinc-500 ml-1">(you)</span>
                            @endif
                        </td>
                        <td class="py-3 px-3 text-sm text-zinc-500 dark:text-zinc-300">{{ $user->email }}</td>
                        <td class="py-3 px-3 text-sm">
                            @if ($user->confirmed_at === null)
                                <flux:badge color="amber" size="sm">Pending</flux:badge>
                            @else
                                <flux:badge color="green" size="sm">Confirmed</flux:badge>
                            @endif
                        </td>
                        <td class="py-3 px-3 text-sm">
                            @if ($user->is_admin)
                                <flux:badge color="blue" size="sm">Admin</flux:badge>
                            @else
                                <span class="text-zinc-500 dark:text-zinc-400">User</span>
                            @endif
                        </td>
                        <td class="py-3 px-3 text-sm text-zinc-500 dark:text-zinc-400 whitespace-nowrap">
                            {{ $user->created_at?->format('Y-m-d') }}
                        </td>
                        <td class="py-3 px-3 last:pr-0 text-sm text-right">
                            <div class="flex items-center justify-end gap-2">
                                @if ($user->confirmed_at === null)
                                    <flux:button size="sm" variant="primary" wire:click="confirmUser({{ $user->id }})">
                                        Confirm
                                    </flux:button>
                                @endif
                                <flux:dropdown>
                                    <flux:button icon="ellipsis-horizontal" variant="ghost" size="sm"/>
                                    <flux:menu>
                                        @if ($user->confirmed_at !== null && $user->id !== auth()->id())
                                            <flux:menu.item icon="no-symbol"
                                                            wire:click="revokeUser({{ $user->id }})"
                                                            wire:confirm="Revoke confirmation? This will block the user's access.">
                                                Revoke Confirmation
                                            </flux:menu.item>
                                        @endif
                                        @if (! $user->is_admin)
                                            <flux:menu.item icon="shield-check" wire:click="makeAdmin({{ $user->id }})">
                                                Make Admin
                                            </flux:menu.item>
                                        @elseif ($user->id !== 1 && $user->id !== auth()->id())
                                            <flux:menu.item icon="shield-exclamation"
                                                            wire:click="revokeAdmin({{ $user->id }})"
                                                            wire:confirm="Remove admin status from {{ $user->name }}?">
                                                Remove Admin
                                            </flux:menu.item>
                                        @endif
                                        @if ($user->id !== auth()->id() && $user->id !== 1)
                                            <flux:menu.separator/>
                                            <flux:menu.item icon="trash" variant="danger"
                                                            wire:click="deleteUser({{ $user->id }})"
                                                            wire:confirm="Permanently delete {{ $user->name }}? This cannot be undone.">
                                                Delete User
                                            </flux:menu.item>
                                        @endif
                                    </flux:menu>
                                </flux:dropdown>
                            </div>
                        </td>
                    </tr>
                @endforeach
                </tbody>
            </table>
        </x-pages::settings.layout>
    </div>
</section>
