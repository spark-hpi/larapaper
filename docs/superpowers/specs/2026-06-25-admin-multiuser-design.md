# Admin & Multi-User Access Control Design

**Date:** 2026-06-25
**Branch:** multi-user

## Context

LaraPaper already supports multiple registered users with clean per-user data isolation (devices and plugins scoped by `user_id`). The only privilege differentiation is a hardcoded `id === 1` guard on the device auto-join toggle. This design introduces a proper admin role, user confirmation gates, shared/unowned resources, and an admin UI — without adding any package dependencies.

---

## Data Model

### New migrations

| Table | Column | Type | Default | Purpose |
|---|---|---|---|---|
| `users` | `is_admin` | boolean | false | Admin flag |
| `users` | `confirmed_at` | timestamp, nullable | null | Null = unconfirmed and blocked from login |
| `plugins` | `is_shared` | boolean | false | Visible and copyable by all confirmed users |

**Device `user_id`:** Already nullable — `null` means unowned. No schema change needed.

### Migration safety

The `is_admin` / `confirmed_at` migration must also:
- Set `is_admin = true` WHERE `id = 1`
- Set `confirmed_at = now()` for ALL existing users (no one gets locked out on upgrade)

### Model helpers

**`User`**
- `isAdmin(): bool` — returns `$this->is_admin`
- `isConfirmed(): bool` — returns `$this->confirmed_at !== null`
- Boot observer: ensures user ID=1 always has `is_admin = true` and `confirmed_at` set (safety net against accidental lockout)

**`Plugin`**
- `scopeVisibleTo(User $user)` — `WHERE user_id = $user->id OR is_shared = true`; admins bypass this and see everything

---

## Authentication & Confirmation

### Blocking unconfirmed users

A new middleware `EnsureUserIsConfirmed` is registered after `auth` in the web middleware stack. On every request:
- If `confirmed_at` is null → log out, redirect to `/login` with flash: *"Your account is awaiting admin approval."*
- Excluded routes: logout, login, registration, password reset, OIDC callback

### OIDC users

OIDC-created users start with `confirmed_at = null` unless they match an existing confirmed account by email.

### Auto-join behaviour change

The `DeviceAutoJoin` Livewire component drops the `id === 1` guard. Any confirmed user can toggle auto-join. When a new device phones home and a user has `assign_new_devices = true`, the device is created with `user_id = null` (unowned) rather than assigned to that user.

---

## Authorization — Policies

Replace scattered `abort_unless` calls with three Policy classes:

### `DevicePolicy`

| Ability | Who |
|---|---|
| `view` | Owner OR admin OR device is unowned |
| `update` / `delete` | Owner OR admin; unowned → admin only |
| `reassign` | Admin only |

### `PluginPolicy`

| Ability | Who |
|---|---|
| `view` | Owner OR `is_shared = true` OR admin |
| `update` / `delete` | Owner OR admin |
| `share` | Owner OR admin (toggle `is_shared`) |
| `reassign` | Admin only |
| `copy` | Any confirmed user (clones shared plugin with new `trmnlp_id`) |

### `UserPolicy`

| Ability | Who |
|---|---|
| `confirm` / `revoke` | Admin only |
| `makeAdmin` / `revokeAdmin` | Admin only |
| `delete` | Admin only |

---

## Admin UI

A **Settings > Admin** section, visible only when `auth()->user()->isAdmin()`, with three sub-pages added to the existing settings sidebar.

### `/settings/admin/users`
- Table of all users: name, email, confirmed status, admin badge, registered date
- Unconfirmed users sorted to the top with a visual highlight
- Per-row actions: Confirm / Revoke, Make Admin / Remove Admin, Delete
- Implemented as a Livewire component

### `/devices` — admin filter toggle
- Admins see a toggle "Show all devices" above the device list
- When enabled, the list expands to include all users' devices and unowned devices
- Unowned devices display a "Shared" badge
- Inline ownership reassignment: user dropdown per row (options include "Nobody" → sets `user_id = null`)
- Non-admins never see the toggle

### `/plugins` — two additions for admins + shared tab for everyone

**Shared tab (all confirmed users):**
- A "Shared" tab alongside the user's own plugins list
- Lists all plugins where `is_shared = true`, showing the owner's name
- "Install copy" button → clones plugin into the current user's account with a new `trmnlp_id` (uses existing `PluginImportService` / clone logic)

**Admin toggle (admins only):**
- "Show all plugins" toggle on the user's own plugins tab
- Reveals all users' plugins with owner attribution
- `is_shared` toggle per plugin (owner or admin can flip)
- Ownership reassignment dropdown

---

## Affected Files (key)

- `database/migrations/` — two new migrations (users columns, plugins column)
- `app/Models/User.php` — `isAdmin()`, `isConfirmed()`, boot observer
- `app/Models/Plugin.php` — `scopeVisibleTo()`, `is_shared` cast
- `app/Http/Middleware/EnsureUserIsConfirmed.php` — new
- `app/Policies/DevicePolicy.php` — new
- `app/Policies/PluginPolicy.php` — new
- `app/Policies/UserPolicy.php` — new
- `app/Providers/AppServiceProvider.php` — register middleware + policies
- `app/Livewire/Actions/DeviceAutoJoin.php` — drop `id === 1` guard
- `app/Http/Controllers/Api/` — replace `abort_unless` with `$this->authorize()`
- `routes/settings.php` — add `/settings/admin/users` route
- `app/Livewire/Admin/UserManager.php` — new component for user list + actions
- `app/Livewire/Devices/DeviceList.php` (or equivalent) — add admin filter toggle
- `app/Livewire/Plugins/PluginList.php` (or equivalent) — add shared tab + admin filter toggle
- `resources/views/` — admin settings sidebar entry, shared plugins tab, "Show all" toggles

---

## Verification

1. **Confirmation gate:** Register a new user → verify redirect to login with the "awaiting approval" message. Admin confirms → user can now log in.
2. **Admin auto-assignment:** Fresh install with no users → register → user ID=1 gets `is_admin = true` and `confirmed_at` set automatically.
3. **Policy enforcement:** As a regular user, attempt to access another user's device via API → expect 403. As admin → expect 200.
4. **Unowned devices:** Toggle auto-join, register a new device → verify `user_id = null` in DB. Regular user sees it; cannot edit it; admin can edit and reassign.
5. **Plugin sharing:** Owner marks plugin as shared → another user sees it in `/plugins/shared` and installs a copy → verify new plugin row with the copier's `user_id`.
6. **Admin UI:** Visit `/settings/admin/users` as non-admin → 403. As admin → user table renders correctly.
7. **Upgrade safety:** Run migrations on a DB with existing users → verify all have `confirmed_at` set and none are locked out.
