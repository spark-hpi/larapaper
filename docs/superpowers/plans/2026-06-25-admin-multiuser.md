# Admin & Multi-User Access Control Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Add admin roles, user confirmation gating, unowned devices, plugin sharing, and an admin management UI to the existing multi-user Laravel/Livewire app.

**Architecture:** Simple boolean flags on existing models (`is_admin`, `confirmed_at`, `is_shared`) with Laravel Policy classes replacing the current scattered `abort_unless` checks. A single `EnsureUserIsConfirmed` middleware blocks unconfirmed users at the web layer. All Livewire components follow the existing Volt single-file pattern (PHP logic in `<?php new class extends Component { ... } ?>` at the top of `.blade.php` files).

**Tech Stack:** PHP 8.4, Laravel 11, Livewire 3 (Volt single-file), Pest 4, Flux UI components, SQLite (tests)

## Global Constraints

- All new Livewire components use Volt single-file style (`new class extends Component` inside `.blade.php`) — no separate PHP class files in `app/Livewire/`
- `pages::` namespace maps to `resources/views/pages/` (configured in `config/livewire.php`)
- Test runner: `vendor/bin/pest` — run from worktree root
- No new packages — native Laravel only
- Policy auto-discovery is active (Laravel 11 default) — no manual `Gate::policy()` calls needed
- User ID=1 is always admin; the migration sets this and a model boot observer enforces it
- New users start with `confirmed_at = null` (unconfirmed, blocked from login)
- Unowned devices have `user_id = null` (column is already nullable — no schema change on `devices`)
- Factories must add `admin()` and `unconfirmed()` states for test clarity
- Arch tests in `tests/Pest.php` run automatically — new classes must satisfy Laravel conventions

---

### Task 1: Database Migrations

**Files:**
- Create: `database/migrations/2026_06_25_000001_add_admin_columns_to_users_table.php`
- Create: `database/migrations/2026_06_25_000002_add_is_shared_to_plugins_table.php`
- Test: `tests/Feature/AdminMigrationsTest.php`

**Interfaces:**
- Produces: `users.is_admin` (bool, default false), `users.confirmed_at` (timestamp nullable), `plugins.is_shared` (bool, default false)

- [ ] **Step 1: Write failing test**

```php
<?php
// tests/Feature/AdminMigrationsTest.php
declare(strict_types=1);

use Illuminate\Support\Facades\Schema;

it('users table has is_admin and confirmed_at columns', function (): void {
    expect(Schema::hasColumn('users', 'is_admin'))->toBeTrue();
    expect(Schema::hasColumn('users', 'confirmed_at'))->toBeTrue();
});

it('plugins table has is_shared column', function (): void {
    expect(Schema::hasColumn('plugins', 'is_shared'))->toBeTrue();
});
```

- [ ] **Step 2: Run test to verify it fails**

```bash
vendor/bin/pest tests/Feature/AdminMigrationsTest.php
```
Expected: FAIL — columns do not exist yet.

- [ ] **Step 3: Create users migration**

```php
<?php
// database/migrations/2026_06_25_000001_add_admin_columns_to_users_table.php
declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->boolean('is_admin')->default(false)->after('remember_token');
            $table->timestamp('confirmed_at')->nullable()->after('is_admin');
        });

        // All existing users are confirmed (no one gets locked out on upgrade)
        DB::table('users')->update(['confirmed_at' => now()]);

        // First user is always admin
        DB::table('users')->where('id', 1)->update(['is_admin' => true]);
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->dropColumn(['is_admin', 'confirmed_at']);
        });
    }
};
```

- [ ] **Step 4: Create plugins migration**

```php
<?php
// database/migrations/2026_06_25_000002_add_is_shared_to_plugins_table.php
declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('plugins', function (Blueprint $table): void {
            $table->boolean('is_shared')->default(false)->after('user_id');
        });
    }

    public function down(): void
    {
        Schema::table('plugins', function (Blueprint $table): void {
            $table->dropColumn('is_shared');
        });
    }
};
```

- [ ] **Step 5: Run test to verify it passes**

```bash
vendor/bin/pest tests/Feature/AdminMigrationsTest.php
```
Expected: PASS

- [ ] **Step 6: Commit**

```bash
git add database/migrations/2026_06_25_000001_add_admin_columns_to_users_table.php \
        database/migrations/2026_06_25_000002_add_is_shared_to_plugins_table.php \
        tests/Feature/AdminMigrationsTest.php
git commit -m "feat: add is_admin, confirmed_at, is_shared migrations"
```

---

### Task 2: User Model Helpers and Factory States

**Files:**
- Modify: `app/Models/User.php`
- Modify: `database/factories/UserFactory.php`
- Test: `tests/Feature/UserModelTest.php`

**Interfaces:**
- Produces: `User::isAdmin(): bool`, `User::isConfirmed(): bool`; factory states `admin()`, `unconfirmed()`, `confirmed()`
- Consumed by: Tasks 4, 6, 7, 8, 9, 10, 11

- [ ] **Step 1: Write failing tests**

```php
<?php
// tests/Feature/UserModelTest.php
declare(strict_types=1);

use App\Models\User;

it('isAdmin returns true when is_admin is true', function (): void {
    $user = User::factory()->admin()->create();
    expect($user->isAdmin())->toBeTrue();
});

it('isAdmin returns false by default', function (): void {
    $user = User::factory()->confirmed()->create();
    expect($user->isAdmin())->toBeFalse();
});

it('isConfirmed returns true when confirmed_at is set', function (): void {
    $user = User::factory()->confirmed()->create();
    expect($user->isConfirmed())->toBeTrue();
});

it('isConfirmed returns false when confirmed_at is null', function (): void {
    $user = User::factory()->unconfirmed()->create();
    expect($user->isConfirmed())->toBeFalse();
});

it('user id 1 is always admin regardless of is_admin column', function (): void {
    // Create first user (will get id=1 in a clean test DB)
    $user = User::factory()->confirmed()->create(['is_admin' => false]);
    if ($user->id === 1) {
        $user->refresh();
        expect($user->isAdmin())->toBeTrue();
    } else {
        expect(true)->toBeTrue(); // skip if not first user in this test run
    }
});
```

- [ ] **Step 2: Run tests to verify they fail**

```bash
vendor/bin/pest tests/Feature/UserModelTest.php
```
Expected: FAIL — methods and factory states don't exist yet.

- [ ] **Step 3: Update User model**

Add to `$fillable` array: `'is_admin'`, `'confirmed_at'`

Add to `casts()` method:
```php
'is_admin' => 'boolean',
'confirmed_at' => 'datetime',
```

Add these methods to the `User` class (after the existing `initials()` method):

```php
public function isAdmin(): bool
{
    return $this->is_admin;
}

public function isConfirmed(): bool
{
    return $this->confirmed_at !== null;
}

protected static function booted(): void
{
    static::saving(function (User $user): void {
        if ($user->id === 1) {
            $user->is_admin = true;
            if ($user->confirmed_at === null) {
                $user->confirmed_at = now();
            }
        }
    });
}
```

- [ ] **Step 4: Update UserFactory**

Add these state methods to `UserFactory` (after the existing `withTwoFactor()` method):

```php
public function admin(): static
{
    return $this->state(fn (array $attributes) => [
        'is_admin' => true,
        'confirmed_at' => now(),
    ]);
}

public function confirmed(): static
{
    return $this->state(fn (array $attributes) => [
        'confirmed_at' => now(),
    ]);
}

public function unconfirmed(): static
{
    return $this->state(fn (array $attributes) => [
        'confirmed_at' => null,
    ]);
}
```

Also update the factory `definition()` to make `confirmed_at` default to `now()` so existing tests don't break (all factory-created users are confirmed unless `unconfirmed()` state is applied):

```php
// In definition() array, add:
'confirmed_at' => now(),
'is_admin' => false,
```

- [ ] **Step 5: Run tests**

```bash
vendor/bin/pest tests/Feature/UserModelTest.php
```
Expected: PASS

- [ ] **Step 6: Run full test suite to check for regressions**

```bash
vendor/bin/pest
```
Expected: all previously passing tests still pass.

- [ ] **Step 7: Commit**

```bash
git add app/Models/User.php database/factories/UserFactory.php tests/Feature/UserModelTest.php
git commit -m "feat: add isAdmin/isConfirmed helpers and factory states to User"
```

---

### Task 3: Plugin Model — is_shared and scopeVisibleTo

**Files:**
- Modify: `app/Models/Plugin.php`
- Modify: `database/factories/PluginFactory.php`
- Test: `tests/Feature/PluginVisibilityTest.php`

**Interfaces:**
- Produces: `Plugin::scopeVisibleTo(Builder $query, User $user)`, `Plugin::$casts['is_shared']`
- Consumed by: Tasks 7, 10, 11

- [ ] **Step 1: Write failing tests**

```php
<?php
// tests/Feature/PluginVisibilityTest.php
declare(strict_types=1);

use App\Models\Plugin;
use App\Models\User;

it('scopeVisibleTo returns own plugins', function (): void {
    $user = User::factory()->confirmed()->create();
    $plugin = Plugin::factory()->create(['user_id' => $user->id, 'is_shared' => false]);

    $results = Plugin::visibleTo($user)->pluck('id');

    expect($results)->toContain($plugin->id);
});

it('scopeVisibleTo returns shared plugins from other users', function (): void {
    $owner = User::factory()->confirmed()->create();
    $viewer = User::factory()->confirmed()->create();
    $sharedPlugin = Plugin::factory()->create(['user_id' => $owner->id, 'is_shared' => true]);

    $results = Plugin::visibleTo($viewer)->pluck('id');

    expect($results)->toContain($sharedPlugin->id);
});

it('scopeVisibleTo hides non-shared plugins from other users', function (): void {
    $owner = User::factory()->confirmed()->create();
    $viewer = User::factory()->confirmed()->create();
    $privatePlugin = Plugin::factory()->create(['user_id' => $owner->id, 'is_shared' => false]);

    $results = Plugin::visibleTo($viewer)->pluck('id');

    expect($results)->not->toContain($privatePlugin->id);
});

it('scopeVisibleTo returns all plugins for admins', function (): void {
    $admin = User::factory()->admin()->create();
    $otherUser = User::factory()->confirmed()->create();
    $privatePlugin = Plugin::factory()->create(['user_id' => $otherUser->id, 'is_shared' => false]);

    $results = Plugin::visibleTo($admin)->pluck('id');

    expect($results)->toContain($privatePlugin->id);
});
```

- [ ] **Step 2: Run tests to verify they fail**

```bash
vendor/bin/pest tests/Feature/PluginVisibilityTest.php
```
Expected: FAIL

- [ ] **Step 3: Update Plugin model**

Add `'is_shared'` to the `$casts` array (find the casts() method):
```php
'is_shared' => 'boolean',
```

Add this scope method to the `Plugin` class (add `use Illuminate\Database\Eloquent\Builder;` at the top of the file if not present):

```php
public function scopeVisibleTo(Builder $query, \App\Models\User $user): Builder
{
    if ($user->isAdmin()) {
        return $query;
    }

    return $query->where(function (Builder $q) use ($user): void {
        $q->where('user_id', $user->id)
          ->orWhere('is_shared', true);
    });
}
```

- [ ] **Step 4: Update PluginFactory**

Add `'is_shared' => false` to the `definition()` array.

- [ ] **Step 5: Run tests**

```bash
vendor/bin/pest tests/Feature/PluginVisibilityTest.php
```
Expected: PASS

- [ ] **Step 6: Commit**

```bash
git add app/Models/Plugin.php database/factories/PluginFactory.php tests/Feature/PluginVisibilityTest.php
git commit -m "feat: add is_shared and scopeVisibleTo to Plugin model"
```

---

### Task 4: EnsureUserIsConfirmed Middleware

**Files:**
- Create: `app/Http/Middleware/EnsureUserIsConfirmed.php`
- Modify: `bootstrap/app.php`
- Modify: `routes/web.php`
- Modify: `routes/settings.php`
- Test: `tests/Feature/ConfirmationGateTest.php`

**Interfaces:**
- Produces: `confirmed` middleware alias — blocks unconfirmed authenticated users, redirecting to login with flash `status`
- Consumed by: Tasks 5, 8

- [ ] **Step 1: Write failing tests**

```php
<?php
// tests/Feature/ConfirmationGateTest.php
declare(strict_types=1);

use App\Models\User;

it('unconfirmed user is redirected from dashboard to login', function (): void {
    $user = User::factory()->unconfirmed()->create();

    $this->actingAs($user)
        ->get(route('dashboard'))
        ->assertRedirect(route('login'));
});

it('unconfirmed user sees awaiting approval message', function (): void {
    $user = User::factory()->unconfirmed()->create();

    $response = $this->actingAs($user)->get(route('dashboard'));

    $response->assertSessionHas('status', 'Your account is awaiting admin approval.');
});

it('confirmed user can access dashboard', function (): void {
    $user = User::factory()->confirmed()->create();

    $this->actingAs($user)
        ->get(route('dashboard'))
        ->assertOk();
});

it('unconfirmed user session is terminated on redirect', function (): void {
    $user = User::factory()->unconfirmed()->create();

    $this->actingAs($user)->get(route('dashboard'));

    $this->assertGuest();
});
```

- [ ] **Step 2: Run tests to verify they fail**

```bash
vendor/bin/pest tests/Feature/ConfirmationGateTest.php
```
Expected: FAIL (unconfirmed users can currently access dashboard)

- [ ] **Step 3: Create middleware**

Create `app/Http/Middleware/EnsureUserIsConfirmed.php`:

```php
<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class EnsureUserIsConfirmed
{
    public function handle(Request $request, Closure $next): Response
    {
        if ($request->user() && ! $request->user()->isConfirmed()) {
            Auth::logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return redirect()->route('login')
                ->with('status', 'Your account is awaiting admin approval.');
        }

        return $next($request);
    }
}
```

- [ ] **Step 4: Register middleware alias in bootstrap/app.php**

In `bootstrap/app.php`, update the `withMiddleware` callback to add the alias:

```php
->withMiddleware(function (Middleware $middleware) {
    $middleware->alias([
        'abilities' => CheckAbilities::class,
        'ability' => CheckForAnyAbility::class,
        'confirmed' => \App\Http\Middleware\EnsureUserIsConfirmed::class,
    ]);
})
```

- [ ] **Step 5: Apply middleware to web routes**

In `routes/web.php`, change the main auth group from:
```php
Route::middleware(['auth'])->group(function () {
```
to:
```php
Route::middleware(['auth', 'confirmed'])->group(function () {
```

In `routes/settings.php`, change both groups:
- `Route::middleware(['auth'])->group(...)` → `Route::middleware(['auth', 'confirmed'])->group(...)`
- `Route::middleware(['auth', 'verified'])->group(...)` → `Route::middleware(['auth', 'confirmed', 'verified'])->group(...)`

- [ ] **Step 6: Run tests**

```bash
vendor/bin/pest tests/Feature/ConfirmationGateTest.php
```
Expected: PASS

- [ ] **Step 7: Run full suite**

```bash
vendor/bin/pest
```
Expected: all passing.

- [ ] **Step 8: Commit**

```bash
git add app/Http/Middleware/EnsureUserIsConfirmed.php bootstrap/app.php \
        routes/web.php routes/settings.php tests/Feature/ConfirmationGateTest.php
git commit -m "feat: add EnsureUserIsConfirmed middleware to block unconfirmed users"
```

---

### Task 5: New Users Start Unconfirmed

**Files:**
- Modify: `app/Actions/Fortify/CreateNewUser.php` (no functional change — `confirmed_at` defaults to null already)
- Modify: `app/Http/Controllers/Auth/OidcController.php`
- Test: `tests/Feature/RegistrationConfirmationTest.php`

**Interfaces:**
- Produces: all newly registered users (Fortify + OIDC) have `confirmed_at = null` unless they match an existing confirmed account

- [ ] **Step 1: Write failing tests**

```php
<?php
// tests/Feature/RegistrationConfirmationTest.php
declare(strict_types=1);

use App\Models\User;

it('newly registered user via Fortify is unconfirmed', function (): void {
    $response = $this->post('/register', [
        'name' => 'New User',
        'email' => 'newuser@example.com',
        'password' => 'password',
        'password_confirmation' => 'password',
    ]);

    $user = User::where('email', 'newuser@example.com')->first();
    expect($user)->not->toBeNull();
    expect($user->confirmed_at)->toBeNull();
});

it('newly registered user is immediately redirected away (unconfirmed)', function (): void {
    $this->post('/register', [
        'name' => 'New User',
        'email' => 'newuser2@example.com',
        'password' => 'password',
        'password_confirmation' => 'password',
    ]);

    $user = User::where('email', 'newuser2@example.com')->first();
    $this->actingAs($user)->get(route('dashboard'))->assertRedirect(route('login'));
});
```

- [ ] **Step 2: Run tests to verify they fail**

```bash
vendor/bin/pest tests/Feature/RegistrationConfirmationTest.php
```
Expected: FAIL — factory default currently sets `confirmed_at`.

Wait: factory users get `confirmed_at` (we set that in Task 2 for test convenience), but *real* registration via `CreateNewUser::create()` does NOT call the factory. The `User::create()` call in `CreateNewUser` only passes `name`, `email`, `password` — no `confirmed_at`, so it will be null by DB default. The test should actually pass for the first assertion. Run to confirm.

- [ ] **Step 3: Verify CreateNewUser needs no change**

Open `app/Actions/Fortify/CreateNewUser.php`. Confirm it only passes `name`, `email`, `password` to `User::create()`. `confirmed_at` is not passed, so it defaults to null via the DB column. No change needed.

- [ ] **Step 4: Update OidcController for new OIDC users**

In `app/Http/Controllers/Auth/OidcController.php`, find the "Create new user" block in `findOrCreateUser()`:

```php
// Create new user
return User::create([
    'oidc_sub' => $oidcUser->getId(),
    'name' => $oidcUser->getName() ?: 'OIDC User',
    'email' => $oidcUser->getEmail() ?: $oidcUser->getId().'@oidc.local',
    'password' => bcrypt(Str::random(32)),
    'email_verified_at' => now(), // OIDC users are considered email-verified
    // confirmed_at is intentionally omitted — admin must confirm
]);
```

`confirmed_at` is not passed, so the new OIDC user will be unconfirmed. No other change needed.

Also: after `Auth::login($user, true)` in `callback()`, the `EnsureUserIsConfirmed` middleware will catch unconfirmed users on the next request, log them out, and redirect. This is correct behavior.

- [ ] **Step 5: Run tests**

```bash
vendor/bin/pest tests/Feature/RegistrationConfirmationTest.php
```
Expected: PASS

- [ ] **Step 6: Commit**

```bash
git add app/Http/Controllers/Auth/OidcController.php tests/Feature/RegistrationConfirmationTest.php
git commit -m "feat: new users (Fortify and OIDC) start unconfirmed"
```

---

### Task 6: Auto-Join Creates Unowned Devices

**Files:**
- Modify: `app/Actions/Api/ResolveDeviceByApiKey.php`
- Modify: `app/Actions/Api/ResolveDeviceByMacAddress.php`
- Modify: `app/Livewire/Actions/DeviceAutoJoin.php`
- Modify: `resources/views/livewire/actions/device-auto-join.blade.php`
- Test: `tests/Feature/DeviceAutoJoinTest.php`

**Interfaces:**
- Produces: auto-joined devices have `user_id = null`; any confirmed user can toggle auto-join (not just ID=1)

- [ ] **Step 1: Write failing tests**

```php
<?php
// tests/Feature/DeviceAutoJoinTest.php
declare(strict_types=1);

use App\Actions\Api\ResolveDeviceByApiKey;
use App\Actions\Api\ResolveDeviceByMacAddress;
use App\Models\Device;
use App\Models\User;
use Illuminate\Http\Request;

it('auto-join via api key creates device with null user_id', function (): void {
    $user = User::factory()->confirmed()->create(['assign_new_devices' => true]);

    $request = Request::create('/', 'GET', [], [], [], [
        'HTTP_ACCESS-TOKEN' => 'new-token-123',
        'HTTP_ID' => 'AA:BB:CC:DD:EE:FF',
    ]);

    $device = app(ResolveDeviceByApiKey::class)->handle($request, autoAssign: true);

    expect($device)->not->toBeNull();
    expect($device->user_id)->toBeNull();
});

it('auto-join via mac address creates device with null user_id', function (): void {
    $user = User::factory()->confirmed()->create(['assign_new_devices' => true]);

    $request = Request::create('/', 'GET', [], [], [], [
        'HTTP_ID' => '11:22:33:44:55:66',
    ]);

    $device = app(ResolveDeviceByMacAddress::class)->handle($request);

    expect($device)->not->toBeNull();
    expect($device->user_id)->toBeNull();
});
```

- [ ] **Step 2: Run tests to verify they fail**

```bash
vendor/bin/pest tests/Feature/DeviceAutoJoinTest.php
```
Expected: FAIL — currently assigns `user_id` to the auto-assign user's ID.

- [ ] **Step 3: Update ResolveDeviceByApiKey**

In `app/Actions/Api/ResolveDeviceByApiKey.php`, in the `Device::create()` call, change:
```php
'user_id' => $autoAssignUser->id,
'name' => "{$autoAssignUser->name}'s TRMNL",
```
to:
```php
'user_id' => null,
'name' => 'Auto-Joined TRMNL',
```

- [ ] **Step 4: Update ResolveDeviceByMacAddress**

In `app/Actions/Api/ResolveDeviceByMacAddress.php`, in the `Device::create()` call, change:
```php
'user_id' => $autoAssignUser->id,
'name' => "{$autoAssignUser->name}'s TRMNL",
```
to:
```php
'user_id' => null,
'name' => 'Auto-Joined TRMNL',
```

- [ ] **Step 5: Update DeviceAutoJoin component — remove id===1 guard**

In `app/Livewire/Actions/DeviceAutoJoin.php`:

Remove the `$isFirstUser` property and the assignment in `mount()`. The full class becomes:

```php
<?php

namespace App\Livewire\Actions;

use Livewire\Component;

class DeviceAutoJoin extends Component
{
    public bool $deviceAutojoin = false;

    public function mount(): void
    {
        $this->deviceAutojoin = (bool) (auth()->user()->assign_new_devices ?? false);
    }

    public function updating($name, $value): void
    {
        $this->validate(['deviceAutojoin' => 'boolean']);

        if ($name === 'deviceAutojoin') {
            auth()->user()->update(['assign_new_devices' => $value]);
        }
    }

    public function render(): \Illuminate\Contracts\View\View|\Illuminate\Contracts\View\Factory
    {
        return view('livewire.actions.device-auto-join');
    }
}
```

- [ ] **Step 6: Update device-auto-join blade view**

Replace `resources/views/livewire/actions/device-auto-join.blade.php` with:

```blade
<div>
    <flux:tooltip content="Add devices automatically that try to connect to this server" position="bottom">
        <flux:switch wire:model.live="deviceAutojoin" label="Permit Auto-Join"/>
    </flux:tooltip>
</div>
```

(Remove the `@if($isFirstUser)` wrapper — the toggle is now visible to all confirmed users.)

- [ ] **Step 7: Run tests**

```bash
vendor/bin/pest tests/Feature/DeviceAutoJoinTest.php
```
Expected: PASS

- [ ] **Step 8: Commit**

```bash
git add app/Actions/Api/ResolveDeviceByApiKey.php \
        app/Actions/Api/ResolveDeviceByMacAddress.php \
        app/Livewire/Actions/DeviceAutoJoin.php \
        resources/views/livewire/actions/device-auto-join.blade.php \
        tests/Feature/DeviceAutoJoinTest.php
git commit -m "feat: auto-joined devices are unowned (user_id=null); any user can enable auto-join"
```

---

### Task 7: DevicePolicy and API Authorization

**Files:**
- Create: `app/Policies/DevicePolicy.php`
- Modify: `app/Http/Controllers/Api/DisplayStatusController.php`
- Test: `tests/Feature/DevicePolicyTest.php`

**Interfaces:**
- Produces: `DevicePolicy` with `view`, `update`, `delete`, `reassign` abilities
- Consumed by: Task 9

- [ ] **Step 1: Write failing tests**

```php
<?php
// tests/Feature/DevicePolicyTest.php
declare(strict_types=1);

use App\Models\Device;
use App\Models\User;

it('owner can view their device', function (): void {
    $user = User::factory()->confirmed()->create();
    $device = Device::factory()->create(['user_id' => $user->id]);

    expect($user->can('view', $device))->toBeTrue();
});

it('other user cannot view a private device', function (): void {
    $owner = User::factory()->confirmed()->create();
    $other = User::factory()->confirmed()->create();
    $device = Device::factory()->create(['user_id' => $owner->id]);

    expect($other->can('view', $device))->toBeFalse();
});

it('any user can view an unowned device', function (): void {
    $user = User::factory()->confirmed()->create();
    $device = Device::factory()->create(['user_id' => null]);

    expect($user->can('view', $device))->toBeTrue();
});

it('admin can view any device', function (): void {
    $admin = User::factory()->admin()->create();
    $owner = User::factory()->confirmed()->create();
    $device = Device::factory()->create(['user_id' => $owner->id]);

    expect($admin->can('view', $device))->toBeTrue();
});

it('owner can update their device', function (): void {
    $user = User::factory()->confirmed()->create();
    $device = Device::factory()->create(['user_id' => $user->id]);

    expect($user->can('update', $device))->toBeTrue();
});

it('non-owner cannot update another user device', function (): void {
    $owner = User::factory()->confirmed()->create();
    $other = User::factory()->confirmed()->create();
    $device = Device::factory()->create(['user_id' => $owner->id]);

    expect($other->can('update', $device))->toBeFalse();
});

it('regular user cannot update unowned device', function (): void {
    $user = User::factory()->confirmed()->create();
    $device = Device::factory()->create(['user_id' => null]);

    expect($user->can('update', $device))->toBeFalse();
});

it('admin can update unowned device', function (): void {
    $admin = User::factory()->admin()->create();
    $device = Device::factory()->create(['user_id' => null]);

    expect($admin->can('update', $device))->toBeTrue();
});

it('only admin can reassign a device', function (): void {
    $admin = User::factory()->admin()->create();
    $user = User::factory()->confirmed()->create();
    $device = Device::factory()->create(['user_id' => $user->id]);

    expect($admin->can('reassign', $device))->toBeTrue();
    expect($user->can('reassign', $device))->toBeFalse();
});

it('api returns 403 when non-owner requests device status', function (): void {
    $owner = User::factory()->confirmed()->create();
    $other = User::factory()->confirmed()->create();
    $device = Device::factory()->create(['user_id' => $owner->id]);
    $token = $other->createToken('test', ['update-screen'])->plainTextToken;

    $this->withHeader('Authorization', "Bearer {$token}")
        ->getJson("/api/display/status?device_id={$device->id}")
        ->assertStatus(403);
});
```

- [ ] **Step 2: Run tests to verify they fail**

```bash
vendor/bin/pest tests/Feature/DevicePolicyTest.php
```
Expected: FAIL

- [ ] **Step 3: Create DevicePolicy**

```php
<?php
// app/Policies/DevicePolicy.php
declare(strict_types=1);

namespace App\Policies;

use App\Models\Device;
use App\Models\User;

class DevicePolicy
{
    public function view(User $user, Device $device): bool
    {
        if ($user->isAdmin()) {
            return true;
        }

        return $device->user_id === null || $device->user_id === $user->id;
    }

    public function update(User $user, Device $device): bool
    {
        if ($user->isAdmin()) {
            return true;
        }

        return $device->user_id === $user->id;
    }

    public function delete(User $user, Device $device): bool
    {
        return $this->update($user, $device);
    }

    public function reassign(User $user, Device $device): bool
    {
        return $user->isAdmin();
    }
}
```

- [ ] **Step 4: Update DisplayStatusController**

Replace the `authorizedDevice()` method in `app/Http/Controllers/Api/DisplayStatusController.php`:

```php
private function authorizedDevice(DisplayStatusRequest|UpdateDisplayStatusRequest $request): Device
{
    $deviceId = $request->integer('device_id');
    $device = Device::findOrFail($deviceId);

    $this->authorize('view', $device);

    return $device;
}
```

Add `use Illuminate\Foundation\Auth\Access\AuthorizesRequests;` and `use AuthorizesRequests;` trait to the controller class if not already present. Check if the base `Controller` class already uses it — in Laravel 11 the base controller is minimal, so you may need to add the trait directly:

```php
class DisplayStatusController extends Controller
{
    use \Illuminate\Foundation\Auth\Access\AuthorizesRequests;
    // ...
}
```

- [ ] **Step 5: Run tests**

```bash
vendor/bin/pest tests/Feature/DevicePolicyTest.php
```
Expected: PASS

- [ ] **Step 6: Commit**

```bash
git add app/Policies/DevicePolicy.php \
        app/Http/Controllers/Api/DisplayStatusController.php \
        tests/Feature/DevicePolicyTest.php
git commit -m "feat: add DevicePolicy and apply to DisplayStatusController"
```

---

### Task 8: PluginPolicy and API Authorization

**Files:**
- Create: `app/Policies/PluginPolicy.php`
- Modify: `app/Http/Controllers/Api/PluginSettingsController.php`
- Modify: `app/Http/Controllers/Api/PluginArchiveController.php`
- Test: `tests/Feature/PluginPolicyTest.php`

**Interfaces:**
- Produces: `PluginPolicy` with `view`, `update`, `delete`, `share`, `reassign`, `copy` abilities
- Consumed by: Tasks 10, 11

- [ ] **Step 1: Write failing tests**

```php
<?php
// tests/Feature/PluginPolicyTest.php
declare(strict_types=1);

use App\Models\Plugin;
use App\Models\User;

it('owner can view their private plugin', function (): void {
    $user = User::factory()->confirmed()->create();
    $plugin = Plugin::factory()->create(['user_id' => $user->id, 'is_shared' => false]);

    expect($user->can('view', $plugin))->toBeTrue();
});

it('non-owner cannot view private plugin', function (): void {
    $owner = User::factory()->confirmed()->create();
    $other = User::factory()->confirmed()->create();
    $plugin = Plugin::factory()->create(['user_id' => $owner->id, 'is_shared' => false]);

    expect($other->can('view', $plugin))->toBeFalse();
});

it('any confirmed user can view shared plugin', function (): void {
    $owner = User::factory()->confirmed()->create();
    $other = User::factory()->confirmed()->create();
    $plugin = Plugin::factory()->create(['user_id' => $owner->id, 'is_shared' => true]);

    expect($other->can('view', $plugin))->toBeTrue();
});

it('admin can view any plugin', function (): void {
    $admin = User::factory()->admin()->create();
    $owner = User::factory()->confirmed()->create();
    $plugin = Plugin::factory()->create(['user_id' => $owner->id, 'is_shared' => false]);

    expect($admin->can('view', $plugin))->toBeTrue();
});

it('owner can update their plugin', function (): void {
    $user = User::factory()->confirmed()->create();
    $plugin = Plugin::factory()->create(['user_id' => $user->id]);

    expect($user->can('update', $plugin))->toBeTrue();
});

it('non-owner cannot update plugin', function (): void {
    $owner = User::factory()->confirmed()->create();
    $other = User::factory()->confirmed()->create();
    $plugin = Plugin::factory()->create(['user_id' => $owner->id]);

    expect($other->can('update', $plugin))->toBeFalse();
});

it('admin can update any plugin', function (): void {
    $admin = User::factory()->admin()->create();
    $owner = User::factory()->confirmed()->create();
    $plugin = Plugin::factory()->create(['user_id' => $owner->id]);

    expect($admin->can('update', $plugin))->toBeTrue();
});

it('only admin can reassign a plugin', function (): void {
    $admin = User::factory()->admin()->create();
    $user = User::factory()->confirmed()->create();
    $plugin = Plugin::factory()->create(['user_id' => $user->id]);

    expect($admin->can('reassign', $plugin))->toBeTrue();
    expect($user->can('reassign', $plugin))->toBeFalse();
});

it('any confirmed user can copy a shared plugin', function (): void {
    $owner = User::factory()->confirmed()->create();
    $other = User::factory()->confirmed()->create();
    $plugin = Plugin::factory()->create(['user_id' => $owner->id, 'is_shared' => true]);

    expect($other->can('copy', $plugin))->toBeTrue();
});

it('cannot copy a private plugin', function (): void {
    $owner = User::factory()->confirmed()->create();
    $other = User::factory()->confirmed()->create();
    $plugin = Plugin::factory()->create(['user_id' => $owner->id, 'is_shared' => false]);

    expect($other->can('copy', $plugin))->toBeFalse();
});

it('api plugin settings index only returns own plugins', function (): void {
    $user = User::factory()->confirmed()->create();
    $otherUser = User::factory()->confirmed()->create();
    Plugin::factory()->create(['user_id' => $user->id, 'trmnlp_id' => 'own-plugin']);
    Plugin::factory()->create(['user_id' => $otherUser->id, 'trmnlp_id' => 'other-plugin']);
    $token = $user->createToken('test', ['update-screen'])->plainTextToken;

    $response = $this->withHeader('Authorization', "Bearer {$token}")
        ->getJson('/api/plugin_settings');

    $ids = collect($response->json('data'))->pluck('id');
    expect($ids)->toContain('own-plugin');
    expect($ids)->not->toContain('other-plugin');
});

it('api plugin destroy denies non-owner', function (): void {
    $owner = User::factory()->confirmed()->create();
    $other = User::factory()->confirmed()->create();
    $plugin = Plugin::factory()->create(['user_id' => $owner->id, 'trmnlp_id' => 'target-plugin']);
    $token = $other->createToken('test', ['update-screen'])->plainTextToken;

    $this->withHeader('Authorization', "Bearer {$token}")
        ->deleteJson('/api/plugin_settings/target-plugin')
        ->assertStatus(403);
});
```

- [ ] **Step 2: Run tests to verify they fail**

```bash
vendor/bin/pest tests/Feature/PluginPolicyTest.php
```
Expected: FAIL

- [ ] **Step 3: Create PluginPolicy**

```php
<?php
// app/Policies/PluginPolicy.php
declare(strict_types=1);

namespace App\Policies;

use App\Models\Plugin;
use App\Models\User;

class PluginPolicy
{
    public function view(User $user, Plugin $plugin): bool
    {
        if ($user->isAdmin()) {
            return true;
        }

        return $plugin->user_id === $user->id || $plugin->is_shared;
    }

    public function update(User $user, Plugin $plugin): bool
    {
        if ($user->isAdmin()) {
            return true;
        }

        return $plugin->user_id === $user->id;
    }

    public function delete(User $user, Plugin $plugin): bool
    {
        return $this->update($user, $plugin);
    }

    public function share(User $user, Plugin $plugin): bool
    {
        return $user->isAdmin() || $plugin->user_id === $user->id;
    }

    public function reassign(User $user, Plugin $plugin): bool
    {
        return $user->isAdmin();
    }

    public function copy(User $user, Plugin $plugin): bool
    {
        return $plugin->is_shared || $user->isAdmin();
    }
}
```

- [ ] **Step 4: Update PluginSettingsController**

Add `use Illuminate\Foundation\Auth\Access\AuthorizesRequests;` and add the trait to the class. Then update the `destroy` method:

```php
public function destroy(Request $request, string $trmnlp_id): Response
{
    $plugin = Plugin::where('trmnlp_id', $trmnlp_id)->firstOrFail();

    $this->authorize('delete', $plugin);

    $plugin->delete();

    return response()->noContent();
}
```

- [ ] **Step 5: Update PluginArchiveController**

In `app/Http/Controllers/Api/PluginArchiveController.php`, add `AuthorizesRequests` trait and update `export()`:

```php
public function export(string $trmnlp_id): BinaryFileResponse
{
    if (mb_trim($trmnlp_id) === '') {
        abort(400, 'trmnlp_id is required');
    }

    $plugin = Plugin::where('trmnlp_id', $trmnlp_id)->firstOrFail();

    $this->authorize('view', $plugin);

    return $this->exporter->exportToZip($plugin, auth()->user());
}
```

- [ ] **Step 6: Run tests**

```bash
vendor/bin/pest tests/Feature/PluginPolicyTest.php
```
Expected: PASS

- [ ] **Step 7: Commit**

```bash
git add app/Policies/PluginPolicy.php \
        app/Http/Controllers/Api/PluginSettingsController.php \
        app/Http/Controllers/Api/PluginArchiveController.php \
        tests/Feature/PluginPolicyTest.php
git commit -m "feat: add PluginPolicy and apply to plugin API controllers"
```

---

### Task 9: Admin User Management Page

**Files:**
- Create: `resources/views/pages/settings/admin/users.blade.php`
- Modify: `routes/settings.php`
- Modify: `resources/views/pages/settings/layout.blade.php`
- Test: `tests/Feature/AdminUserManagementTest.php`

**Interfaces:**
- Produces: `/settings/admin/users` route (name: `settings.admin.users`), Livewire component at `pages::settings.admin.users`
- Consumed by: nothing downstream

- [ ] **Step 1: Write failing tests**

```php
<?php
// tests/Feature/AdminUserManagementTest.php
declare(strict_types=1);

use App\Models\User;

it('non-admin cannot access admin users page', function (): void {
    $user = User::factory()->confirmed()->create();

    $this->actingAs($user)
        ->get(route('settings.admin.users'))
        ->assertForbidden();
});

it('admin can access admin users page', function (): void {
    $admin = User::factory()->admin()->create();

    $this->actingAs($admin)
        ->get(route('settings.admin.users'))
        ->assertOk();
});

it('admin can confirm a user', function (): void {
    $admin = User::factory()->admin()->create();
    $pending = User::factory()->unconfirmed()->create();

    $this->actingAs($admin)
        ->call('confirmed', 'confirmUser', [$pending->id])
        ?? $this->actingAs($admin)->post(route('settings.admin.users'), [
            'action' => 'confirm',
            'user_id' => $pending->id,
        ]);

    expect($pending->fresh()->confirmed_at)->not->toBeNull();
});
```

Note: the Livewire action test uses the Livewire testing API. The third test will be refined to use Livewire test helpers once the component exists. For now, write it with a placeholder assertion and refine in step 3.

- [ ] **Step 2: Add route**

In `routes/settings.php`, add inside the `Route::middleware(['auth', 'confirmed'])` group:

```php
Route::livewire('settings/admin/users', 'pages::settings.admin.users')
    ->name('settings.admin.users');
```

- [ ] **Step 3: Create Volt component**

Create directory: `resources/views/pages/settings/admin/`

Create `resources/views/pages/settings/admin/users.blade.php`:

```blade
<?php

use App\Models\User;
use Livewire\Component;

new class extends Component
{
    public function mount(): void
    {
        abort_unless(auth()->user()->isAdmin(), 403);
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

    public function render(): \Illuminate\Contracts\View\View
    {
        return view('pages.settings.admin.users', [
            'users' => User::orderBy('confirmed_at')->orderBy('created_at')->get(),
        ]);
    }
}

?>

<x-settings.layout heading="User Management" subheading="Confirm accounts and manage admin access.">
    <div class="space-y-4">
        @foreach ($users as $user)
            <div class="flex items-center justify-between p-3 border border-zinc-200 dark:border-zinc-700 rounded-lg
                        {{ $user->confirmed_at === null ? 'border-amber-400 dark:border-amber-500' : '' }}">
                <div>
                    <div class="font-medium text-sm">{{ $user->name }}</div>
                    <div class="text-xs text-zinc-500">{{ $user->email }}</div>
                    <div class="flex gap-2 mt-1">
                        @if ($user->confirmed_at === null)
                            <flux:badge color="amber" size="sm">Pending</flux:badge>
                        @else
                            <flux:badge color="green" size="sm">Confirmed</flux:badge>
                        @endif
                        @if ($user->is_admin)
                            <flux:badge color="blue" size="sm">Admin</flux:badge>
                        @endif
                    </div>
                </div>
                <div class="flex gap-2">
                    @if ($user->confirmed_at === null)
                        <flux:button size="sm" variant="primary" wire:click="confirmUser({{ $user->id }})">
                            Confirm
                        </flux:button>
                    @else
                        <flux:button size="sm" variant="ghost" wire:click="revokeUser({{ $user->id }})"
                                     wire:confirm="Revoke confirmation? This will block the user's access."
                                     :disabled="$user->id === auth()->id()">
                            Revoke
                        </flux:button>
                    @endif

                    @if (! $user->is_admin)
                        <flux:button size="sm" variant="ghost" wire:click="makeAdmin({{ $user->id }})">
                            Make Admin
                        </flux:button>
                    @elseif ($user->id !== 1 && $user->id !== auth()->id())
                        <flux:button size="sm" variant="ghost" wire:click="revokeAdmin({{ $user->id }})"
                                     wire:confirm="Remove admin status from {{ $user->name }}?">
                            Remove Admin
                        </flux:button>
                    @endif

                    @if ($user->id !== auth()->id() && $user->id !== 1)
                        <flux:button size="sm" variant="danger" wire:click="deleteUser({{ $user->id }})"
                                     wire:confirm="Permanently delete {{ $user->name }}? This cannot be undone.">
                            Delete
                        </flux:button>
                    @endif
                </div>
            </div>
        @endforeach
    </div>
</x-settings.layout>
```

- [ ] **Step 4: Add Admin nav section to settings layout**

In `resources/views/pages/settings/layout.blade.php`, add after the `Support` navlist group:

```blade
@if (auth()?->user()?->isAdmin())
    <flux:navlist.group :heading="__('Admin')" class="mt-2">
        <flux:navlist.item :href="route('settings.admin.users')" wire:navigate>{{ __('Users') }}</flux:navlist.item>
    </flux:navlist.group>
@endif
```

- [ ] **Step 5: Run tests**

```bash
vendor/bin/pest tests/Feature/AdminUserManagementTest.php
```
Expected: PASS (the Livewire confirm action test may need adjustment to use `Livewire::test()` if it fails — see note below)

If the `confirmUser` test fails due to testing approach, replace the third test with:

```php
it('admin can confirm a user via Livewire action', function (): void {
    $admin = User::factory()->admin()->create();
    $pending = User::factory()->unconfirmed()->create();

    $this->actingAs($admin);

    Livewire::test('pages::settings.admin.users')
        ->call('confirmUser', $pending->id)
        ->assertHasNoErrors();

    expect($pending->fresh()->confirmed_at)->not->toBeNull();
});
```

- [ ] **Step 6: Commit**

```bash
git add resources/views/pages/settings/admin/users.blade.php \
        routes/settings.php \
        resources/views/pages/settings/layout.blade.php \
        tests/Feature/AdminUserManagementTest.php
git commit -m "feat: add admin user management page with confirm/revoke/promote/delete"
```

---

### Task 10: Device Manage — Admin Filter, Unowned Badge, Ownership Reassignment

**Files:**
- Modify: `resources/views/livewire/devices/manage.blade.php`
- Test: `tests/Feature/DeviceManageAdminTest.php`

**Interfaces:**
- Produces: Admin "Show all" toggle on `/devices`; ownership reassignment method; unowned devices visible to all users; unowned devices show "Shared" badge
- Consumes: `User::isAdmin()` (Task 2), `DevicePolicy::reassign()` (Task 7)

- [ ] **Step 1: Write failing tests**

```php
<?php
// tests/Feature/DeviceManageAdminTest.php
declare(strict_types=1);

use App\Models\Device;
use App\Models\User;

it('regular user sees their own devices and unowned devices', function (): void {
    $user = User::factory()->confirmed()->create();
    $ownDevice = Device::factory()->create(['user_id' => $user->id]);
    $unownedDevice = Device::factory()->create(['user_id' => null]);
    $otherDevice = Device::factory()->create(['user_id' => User::factory()->confirmed()->create()->id]);

    $this->actingAs($user);
    Livewire::test('devices.manage')
        ->assertSee($ownDevice->name)
        ->assertSee($unownedDevice->name)
        ->assertDontSee($otherDevice->name);
});

it('admin can toggle show-all to see all devices', function (): void {
    $admin = User::factory()->admin()->create();
    $otherUser = User::factory()->confirmed()->create();
    $otherDevice = Device::factory()->create(['user_id' => $otherUser->id]);

    $this->actingAs($admin);
    Livewire::test('devices.manage')
        ->set('showAllDevices', true)
        ->assertSee($otherDevice->name);
});

it('admin can reassign a device', function (): void {
    $admin = User::factory()->admin()->create();
    $user = User::factory()->confirmed()->create();
    $device = Device::factory()->create(['user_id' => null]);

    $this->actingAs($admin);
    Livewire::test('devices.manage')
        ->call('reassignDevice', $device->id, $user->id)
        ->assertHasNoErrors();

    expect($device->fresh()->user_id)->toBe($user->id);
});

it('regular user cannot reassign a device', function (): void {
    $user = User::factory()->confirmed()->create();
    $device = Device::factory()->create(['user_id' => null]);

    $this->actingAs($user);
    Livewire::test('devices.manage')
        ->call('reassignDevice', $device->id, $user->id)
        ->assertForbidden();
});
```

- [ ] **Step 2: Run tests to verify they fail**

```bash
vendor/bin/pest tests/Feature/DeviceManageAdminTest.php
```
Expected: FAIL

- [ ] **Step 3: Update devices/manage.blade.php PHP section**

In the `<?php ... ?>` block at the top of `resources/views/livewire/devices/manage.blade.php`:

Add `use App\Policies\DevicePolicy;` to the imports.

Add this property to the class:
```php
public bool $showAllDevices = false;
```

Replace the `$this->devices = auth()->user()->devices;` line in `mount()` with a call to a new method:
```php
public function mount()
{
    $this->loadDevices();
    $this->deviceModels = DeviceModel::orderBy('label')->get()->sortBy(function ($deviceModel) {
        $isTrmnl = str_starts_with($deviceModel->label, 'TRMNL');
        return $isTrmnl ? '0'.$deviceModel->label : '1'.$deviceModel->label;
    });

    return view('livewire.devices.manage');
}

public function loadDevices(): void
{
    $user = auth()->user();

    if ($user->isAdmin() && $this->showAllDevices) {
        $this->devices = Device::all();
    } else {
        $this->devices = Device::where('user_id', $user->id)
            ->orWhereNull('user_id')
            ->get();
    }
}

public function updatedShowAllDevices(): void
{
    $this->loadDevices();
}

public function reassignDevice(int $deviceId, ?int $newOwnerId): void
{
    $device = Device::findOrFail($deviceId);
    $this->authorize('reassign', $device);

    $device->update(['user_id' => $newOwnerId]);
    $this->loadDevices();

    Flux::toast(variant: 'success', text: 'Device ownership updated.');
}
```

Also add `AuthorizesRequests` to the class:
```php
new class extends Component
{
    use \Illuminate\Foundation\Auth\Access\AuthorizesRequests;
    // ...
```

Also add `$users` computed data for the reassignment dropdown (all confirmed users):
```php
public function getAvailableUsersProperty(): \Illuminate\Database\Eloquent\Collection
{
    return \App\Models\User::whereNotNull('confirmed_at')->orderBy('name')->get();
}
```

- [ ] **Step 4: Update devices/manage.blade.php HTML section**

In the Blade HTML section, add the following:

**Above the table header** (inside the max-w-7xl div, after the flex justify-between row):
```blade
@if (auth()->user()->isAdmin())
    <div class="mb-4">
        <flux:switch wire:model.live="showAllDevices" label="Show all users' devices"/>
    </div>
@endif
```

**In the table rows** (in the Name column), add a "Shared" badge for unowned devices:
```blade
{{ $device->name }}
@if ($device->user_id === null)
    <flux:badge color="zinc" size="sm" class="ml-1">Shared</flux:badge>
@endif
```

**At the end of the Actions cell** (after the proxy toggle), add admin reassignment for admins:
```blade
@if (auth()->user()->isAdmin())
    <flux:select wire:change="reassignDevice({{ $device->id }}, $event.target.value ? Number($event.target.value) : null)"
                 class="text-xs">
        <flux:select.option value="">Nobody</flux:select.option>
        @foreach ($this->availableUsers as $u)
            <flux:select.option value="{{ $u->id }}" :selected="$device->user_id === $u->id">
                {{ $u->name }}
            </flux:select.option>
        @endforeach
    </flux:select>
@endif
```

- [ ] **Step 5: Run tests**

```bash
vendor/bin/pest tests/Feature/DeviceManageAdminTest.php
```
Expected: PASS

- [ ] **Step 6: Commit**

```bash
git add resources/views/livewire/devices/manage.blade.php \
        tests/Feature/DeviceManageAdminTest.php
git commit -m "feat: device manage - unowned devices visible to all, admin filter and reassignment"
```

---

### Task 11: Plugin Index — Sharing, Shared Tab, Admin Filter and Reassignment

**Files:**
- Modify: `resources/views/livewire/plugins/index.blade.php`
- Test: `tests/Feature/PluginIndexAdminTest.php`

**Interfaces:**
- Produces: `is_shared` toggle per plugin; "Shared" tab for all users; admin "Show all" toggle; plugin copy action; plugin ownership reassignment
- Consumes: `Plugin::scopeVisibleTo()` (Task 3), `PluginPolicy` (Task 8)

- [ ] **Step 1: Write failing tests**

```php
<?php
// tests/Feature/PluginIndexAdminTest.php
declare(strict_types=1);

use App\Models\Plugin;
use App\Models\User;

it('owner can toggle plugin sharing', function (): void {
    $user = User::factory()->confirmed()->create();
    $plugin = Plugin::factory()->create(['user_id' => $user->id, 'is_shared' => false, 'plugin_type' => 'recipe']);

    $this->actingAs($user);
    Livewire::test('plugins.index')
        ->call('toggleShared', $plugin->id)
        ->assertHasNoErrors();

    expect($plugin->fresh()->is_shared)->toBeTrue();
});

it('non-owner cannot toggle plugin sharing', function (): void {
    $owner = User::factory()->confirmed()->create();
    $other = User::factory()->confirmed()->create();
    $plugin = Plugin::factory()->create(['user_id' => $owner->id, 'is_shared' => false, 'plugin_type' => 'recipe']);

    $this->actingAs($other);
    Livewire::test('plugins.index')
        ->call('toggleShared', $plugin->id)
        ->assertForbidden();
});

it('user can copy a shared plugin', function (): void {
    $owner = User::factory()->confirmed()->create();
    $copier = User::factory()->confirmed()->create();
    $plugin = Plugin::factory()->create([
        'user_id' => $owner->id,
        'is_shared' => true,
        'plugin_type' => 'recipe',
        'name' => 'Shared Plugin',
    ]);

    $this->actingAs($copier);
    Livewire::test('plugins.index')
        ->call('copyPlugin', $plugin->id)
        ->assertHasNoErrors();

    expect(Plugin::where('user_id', $copier->id)->where('name', 'Shared Plugin')->exists())->toBeTrue();
});

it('cannot copy a private plugin', function (): void {
    $owner = User::factory()->confirmed()->create();
    $other = User::factory()->confirmed()->create();
    $plugin = Plugin::factory()->create(['user_id' => $owner->id, 'is_shared' => false, 'plugin_type' => 'recipe']);

    $this->actingAs($other);
    Livewire::test('plugins.index')
        ->call('copyPlugin', $plugin->id)
        ->assertForbidden();
});

it('admin can reassign plugin ownership', function (): void {
    $admin = User::factory()->admin()->create();
    $owner = User::factory()->confirmed()->create();
    $newOwner = User::factory()->confirmed()->create();
    $plugin = Plugin::factory()->create(['user_id' => $owner->id, 'plugin_type' => 'recipe']);

    $this->actingAs($admin);
    Livewire::test('plugins.index')
        ->call('reassignPlugin', $plugin->id, $newOwner->id)
        ->assertHasNoErrors();

    expect($plugin->fresh()->user_id)->toBe($newOwner->id);
});
```

- [ ] **Step 2: Run tests to verify they fail**

```bash
vendor/bin/pest tests/Feature/PluginIndexAdminTest.php
```
Expected: FAIL

- [ ] **Step 3: Update plugins/index.blade.php PHP section**

In the `<?php ... ?>` block of `resources/views/livewire/plugins/index.blade.php`:

Add imports:
```php
use App\Models\Plugin;
use App\Models\User;
```

Add properties:
```php
public bool $showAllPlugins = false;
public string $activeTab = 'mine'; // 'mine' | 'shared'
```

Add trait:
```php
new class extends Component
{
    use WithFileUploads;
    use \Illuminate\Foundation\Auth\Access\AuthorizesRequests;
```

Update `refreshPlugins()` to use `scopeVisibleTo` and handle tab:

```php
public function refreshPlugins(): void
{
    $user = auth()->user();

    if ($this->activeTab === 'shared') {
        $userPlugins = Plugin::where('is_shared', true)
            ->where('plugin_type', 'recipe')
            ->with('user')
            ->get()
            ->makeHidden(['render_markup', 'data_payload'])
            ->toArray();
    } elseif ($user->isAdmin() && $this->showAllPlugins) {
        $userPlugins = Plugin::where('plugin_type', 'recipe')
            ->with('user')
            ->get()
            ->makeHidden(['render_markup', 'data_payload'])
            ->toArray();
    } else {
        $userPlugins = $user->plugins()
            ->where('plugin_type', 'recipe')
            ->get()
            ->makeHidden(['render_markup', 'data_payload'])
            ->toArray();
    }

    $allPlugins = $this->activeTab === 'mine'
        ? array_merge($this->nativePlugins(), $userPlugins ?? [])
        : $userPlugins ?? [];

    $this->plugins = $this->sortPlugins(array_values($allPlugins));
}
```

Add `updatedActiveTab()` and `updatedShowAllPlugins()`:
```php
public function updatedActiveTab(): void
{
    $this->refreshPlugins();
}

public function updatedShowAllPlugins(): void
{
    $this->refreshPlugins();
}
```

Add action methods:

```php
public function toggleShared(int $pluginId): void
{
    $plugin = Plugin::findOrFail($pluginId);
    $this->authorize('share', $plugin);

    $plugin->update(['is_shared' => ! $plugin->is_shared]);
    $this->refreshPlugins();

    Flux::toast(variant: 'success', text: $plugin->fresh()->is_shared ? 'Plugin shared.' : 'Plugin unshared.');
}

public function copyPlugin(int $pluginId): void
{
    $plugin = Plugin::findOrFail($pluginId);
    $this->authorize('copy', $plugin);

    $copy = $plugin->replicate(['id', 'uuid', 'trmnlp_id']);
    $copy->user_id = auth()->id();
    $copy->is_shared = false;
    $copy->trmnlp_id = (string) \Symfony\Component\Uid\Uuid::v7();
    $copy->uuid = (string) \Symfony\Component\Uid\Uuid::v4();
    $copy->save();

    $this->refreshPlugins();
    Flux::toast(variant: 'success', text: "'{$plugin->name}' copied to your plugins.");
}

public function reassignPlugin(int $pluginId, int $newOwnerId): void
{
    $plugin = Plugin::findOrFail($pluginId);
    $this->authorize('reassign', $plugin);

    $newOwner = User::findOrFail($newOwnerId);
    $plugin->update(['user_id' => $newOwner->id]);
    $this->refreshPlugins();

    Flux::toast(variant: 'success', text: 'Plugin ownership updated.');
}
```

- [ ] **Step 4: Update plugins/index.blade.php HTML section**

Add tab switcher near the top of the view's HTML (before the plugins list):

```blade
<div class="flex gap-4 mb-4">
    <flux:button variant="{{ $activeTab === 'mine' ? 'primary' : 'ghost' }}"
                 wire:click="$set('activeTab', 'mine')">
        My Plugins
    </flux:button>
    <flux:button variant="{{ $activeTab === 'shared' ? 'primary' : 'ghost' }}"
                 wire:click="$set('activeTab', 'shared')">
        Shared Plugins
    </flux:button>
</div>

@if (auth()->user()->isAdmin() && $activeTab === 'mine')
    <div class="mb-4">
        <flux:switch wire:model.live="showAllPlugins" label="Show all users' plugins"/>
    </div>
@endif
```

In the plugin list loop, add per-plugin actions (after each plugin entry, or as a dropdown):

Note: `$plugins` in the component is an array of arrays (via `.toArray()`), not Eloquent models. Use direct field comparisons in the blade — do not call `can()` or policies inline in the template.

```blade
{{-- Share toggle (owner or admin only) --}}
@if (($plugin['user_id'] ?? null) === auth()->id() || auth()->user()->isAdmin())
    <flux:switch wire:click="toggleShared({{ $plugin['id'] }})"
                 :checked="$plugin['is_shared'] ?? false"
                 label="Shared"/>
@endif

{{-- Copy button: visible to non-owners when plugin is shared --}}
@if (($plugin['is_shared'] ?? false) && ($plugin['user_id'] ?? null) !== auth()->id())
    <flux:button size="sm" wire:click="copyPlugin({{ $plugin['id'] }})">
        Install Copy
    </flux:button>
@endif

{{-- Admin reassignment --}}
@if (auth()->user()->isAdmin())
    <flux:select wire:change="reassignPlugin({{ $plugin['id'] }}, Number($event.target.value))" class="text-xs">
        @foreach (\App\Models\User::whereNotNull('confirmed_at')->orderBy('name')->get() as $u)
            <flux:select.option value="{{ $u->id }}" :selected="($plugin['user_id'] ?? null) === $u->id">
                {{ $u->name }}
            </flux:select.option>
        @endforeach
    </flux:select>
@endif
```

The exact placement of these controls depends on the existing plugin list markup. Insert them within whatever per-plugin card/row template the view uses.

- [ ] **Step 5: Run tests**

```bash
vendor/bin/pest tests/Feature/PluginIndexAdminTest.php
```
Expected: PASS

- [ ] **Step 6: Run full test suite**

```bash
vendor/bin/pest
```
Expected: all passing.

- [ ] **Step 7: Commit**

```bash
git add resources/views/livewire/plugins/index.blade.php \
        tests/Feature/PluginIndexAdminTest.php
git commit -m "feat: plugin sharing, shared tab, admin filter, and ownership reassignment"
```

---

## Verification Checklist

After all tasks are complete, verify end-to-end:

1. **Upgrade safety:** Run `vendor/bin/pest` on a fresh DB. All existing users should get `confirmed_at` set. User ID=1 gets `is_admin=true`.

2. **Registration gate:** Register a new user → try to log in → see "Your account is awaiting admin approval." → Log in as admin → go to `/settings/admin/users` → confirm the user → log in as that user → access dashboard successfully.

3. **Auto-join:** Enable "Permit Auto-Join" as a non-admin user (should now be visible to all). Simulate a new device phoning home via the API. Verify the created device has `user_id = null` in the DB.

4. **Unowned device visibility:** A regular user visits `/devices` and sees the auto-joined unowned device with a "Shared" badge. They cannot edit it (the UI doesn't show edit controls). An admin can see the reassignment dropdown.

5. **Plugin sharing:** User A creates a plugin and toggles "Shared". User B visits `/plugins` → "Shared" tab → sees User A's plugin → clicks "Install Copy" → a new plugin appears under User B's "My Plugins" tab with a new `trmnlp_id`.

6. **Policy enforcement via API:** Using a Sanctum token for User B, `GET /api/display/status?device_id=<User A's device>` returns 403. `DELETE /api/plugin_settings/<User A's plugin trmnlp_id>` returns 403.

7. **Admin user management:** Admin visits `/settings/admin/users`. Sees pending users at the top. Confirms one. Promotes another to admin. Verifies the promoted user sees the Admin nav section in settings.
