# Devices UI Adjustments Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Widen the device-model modal, swap the devices-list "view" icon for an edit icon, blank out the "Actions" column headers, and move the proxy toggle + refresh-interval display off the `/devices` list and onto the per-device `/devices/{device}/configure` page.

**Architecture:** Pure Blade/Livewire view edits across four single-file Livewire components (`device-models/index.blade.php`, `device-palettes/index.blade.php`, `devices/manage.blade.php`, `devices/configure.blade.php`). One method (`toggleProxyCloud`) is relocated from the `devices.manage` component to the `devices.configure` component, including its existing test coverage.

**Tech Stack:** Laravel Livewire (`new class extends Component` single-file components), Flux UI components (`flux:button`, `flux:switch`, `flux:tooltip`, `flux:modal`), Pest for tests.

## Global Constraints

- Spec: `docs/superpowers/specs/2026-07-07-devices-ui-adjustments-design.md`
- Eye icon on `device-models`/`device-palettes` for API-sourced (view-only) rows stays an eye icon — do not touch.
- No changes to the "Edit TRMNL" modal fields, playlists, firmware, or mirror sections of the configure page.
- No database/model/route changes.
- Follow existing code style in each file exactly (attribute ordering, indentation) — these are surgical edits, not rewrites.

---

### Task 1: Widen the device-model modal

**Files:**
- Modify: `resources/views/livewire/device-models/index.blade.php:288`
- Test: `tests/Feature/DeviceModelsTest.php`

**Interfaces:**
- Consumes: nothing new.
- Produces: nothing consumed by later tasks (independent change).

- [ ] **Step 1: Write the failing test**

Add to `tests/Feature/DeviceModelsTest.php` (matches existing style in that file — see the `it('allows a user to view the device models page', ...)` test already there):

```php
it('renders the device model modal at the wider width', function (): void {
    $user = User::factory()->create();
    DeviceModel::factory()->create();

    $response = $this->actingAs($user)->get('/device-models');

    $response->assertSuccessful();
    $response->assertSee('md:w-[36rem]', false);
});
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test --filter="renders the device model modal at the wider width"`
Expected: FAIL — `md:w-[36rem]` not found in response (current class is `md:w-96`).

- [ ] **Step 3: Widen the modal**

In `resources/views/livewire/device-models/index.blade.php:288`, change:

```blade
<flux:modal name="device-model-modal" class="md:w-96">
```

to:

```blade
<flux:modal name="device-model-modal" class="md:w-[36rem]">
```

- [ ] **Step 4: Run test to verify it passes**

Run: `php artisan test --filter="renders the device model modal at the wider width"`
Expected: PASS

- [ ] **Step 5: Commit**

```bash
git add resources/views/livewire/device-models/index.blade.php tests/Feature/DeviceModelsTest.php
git commit -m "feat: widen device model modal"
```

---

### Task 2: Blank the "Actions" column header on device-models

**Files:**
- Modify: `resources/views/livewire/device-models/index.blade.php:441-444`
- Test: `tests/Feature/DeviceModelsTest.php`

**Interfaces:**
- Consumes: nothing new.
- Produces: nothing consumed by later tasks.

- [ ] **Step 1: Write the failing test**

Add to `tests/Feature/DeviceModelsTest.php`:

```php
it('renders an empty Actions column header on device models', function (): void {
    $user = User::factory()->create();
    DeviceModel::factory()->create();

    $response = $this->actingAs($user)->get('/device-models');

    $response->assertSuccessful();
    $response->assertDontSee('Actions');
});
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test --filter="renders an empty Actions column header on device models"`
Expected: FAIL — "Actions" is currently present in the header.

- [ ] **Step 3: Blank the header text**

In `resources/views/livewire/device-models/index.blade.php:441-444`, change:

```blade
                    <th class="py-3 px-3 first:pl-0 last:pr-0 text-left text-sm font-medium text-zinc-800 dark:text-white"
                        data-flux-column>
                        <div class="whitespace-nowrap flex group-[]/right-align:justify-end">Actions</div>
                    </th>
```

to:

```blade
                    <th class="py-3 px-3 first:pl-0 last:pr-0 text-left text-sm font-medium text-zinc-800 dark:text-white"
                        data-flux-column>
                        <div class="whitespace-nowrap flex group-[]/right-align:justify-end"></div>
                    </th>
```

- [ ] **Step 4: Run test to verify it passes**

Run: `php artisan test --filter="renders an empty Actions column header on device models"`
Expected: PASS

- [ ] **Step 5: Commit**

```bash
git add resources/views/livewire/device-models/index.blade.php tests/Feature/DeviceModelsTest.php
git commit -m "feat: blank Actions column header on device models"
```

---

### Task 3: Blank the "Actions" column header on device-palettes

**Files:**
- Modify: `resources/views/livewire/device-palettes/index.blade.php:313-316`
- Test: `tests/Feature/Livewire/DevicePalettesTest.php`

**Interfaces:**
- Consumes: nothing new.
- Produces: nothing consumed by later tasks.

- [ ] **Step 1: Write the failing test**

Add to `tests/Feature/Livewire/DevicePalettesTest.php` (matches the file's existing style — `test('device palettes page can be rendered', ...)` is already there):

```php
test('device palettes page renders an empty Actions column header', function (): void {
    $user = User::factory()->create();
    DevicePalette::create(['name' => 'palette-1', 'grays' => 2, 'framework_class' => '']);

    $this->actingAs($user);

    $response = $this->get(route('device-palettes.index'));

    $response->assertOk();
    $response->assertDontSee('Actions');
});
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test --filter="device palettes page renders an empty Actions column header"`
Expected: FAIL — "Actions" is currently present in the header.

- [ ] **Step 3: Blank the header text**

In `resources/views/livewire/device-palettes/index.blade.php:313-316`, change:

```blade
                    <th class="py-3 px-3 first:pl-0 last:pr-0 text-left text-sm font-medium text-zinc-800 dark:text-white"
                        data-flux-column>
                        <div class="whitespace-nowrap flex group-[]/right-align:justify-end">Actions</div>
                    </th>
```

to:

```blade
                    <th class="py-3 px-3 first:pl-0 last:pr-0 text-left text-sm font-medium text-zinc-800 dark:text-white"
                        data-flux-column>
                        <div class="whitespace-nowrap flex group-[]/right-align:justify-end"></div>
                    </th>
```

- [ ] **Step 4: Run test to verify it passes**

Run: `php artisan test --filter="device palettes page renders an empty Actions column header"`
Expected: PASS

- [ ] **Step 5: Commit**

```bash
git add resources/views/livewire/device-palettes/index.blade.php tests/Feature/Livewire/DevicePalettesTest.php
git commit -m "feat: blank Actions column header on device palettes"
```

---

### Task 4: `/devices` list — eye→pencil icon, blank Actions header, remove Refresh column and proxy switch

**Files:**
- Modify: `resources/views/livewire/devices/manage.blade.php`
- Test: `tests/Feature/Devices/ManageTest.php`

**Interfaces:**
- Consumes: nothing new.
- Produces: nothing — `toggleProxyCloud` is deleted from this component entirely (it moves to `devices.configure` in Task 5). No other task depends on this component's proxy logic afterward.

This task also **removes** the two existing tests in `tests/Feature/Devices/ManageTest.php` that call `toggleProxyCloud` on the `devices.manage` Livewire component, since that method will no longer exist here. Equivalent coverage is added on the `devices.configure` component in Task 5.

- [ ] **Step 1: Write the failing tests**

Add to `tests/Feature/Devices/ManageTest.php`:

```php
test('device management page renders a pencil icon link to configure and an empty Actions header', function (): void {
    $user = User::factory()->create();
    // A distinct value (not the create-device modal's default of 900) so the
    // assertion below can't accidentally match that unrelated hidden modal.
    $device = Device::factory()->create([
        'user_id' => $user->id,
        'default_refresh_interval' => 1234,
    ]);

    $response = $this->actingAs($user)->get('/devices');

    $response->assertOk();
    $response->assertDontSee('Actions');
    // Precise tag match: the create-device modal still legitimately contains
    // the word "Refresh" (label "Refresh Rate (seconds)"), so a bare
    // assertDontSee('Refresh') would be a false failure.
    $response->assertDontSee('>Refresh<', false);
    $response->assertDontSee('1234');
    $response->assertDontSee('☁️ Proxy');
    $response->assertSee(route('devices.configure', $device), false);
});
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test --filter="device management page renders a pencil icon link to configure and an empty Actions header"`
Expected: FAIL — "Actions", the Refresh column header, the refresh interval value, and "☁️ Proxy" are all currently present.

- [ ] **Step 3: Remove the two toggleProxyCloud tests that target this component**

In `tests/Feature/Devices/ManageTest.php`, delete these two tests entirely (lines 69-105 in the current file):

```php
test('user can toggle proxy cloud for their device', function (): void {
    $user = User::factory()->create();
    $this->actingAs($user);
    $device = Device::factory()->create([
        'user_id' => $user->id,
        'proxy_cloud' => false,
    ]);

    $response = Livewire::test('devices.manage')
        ->call('toggleProxyCloud', $device);

    $response->assertHasNoErrors();
    expect($device->fresh()->proxy_cloud)->toBeTrue();

    // Toggle back to false
    $response = Livewire::test('devices.manage')
        ->call('toggleProxyCloud', $device);

    expect($device->fresh()->proxy_cloud)->toBeFalse();
});

test('user cannot toggle proxy cloud for other users devices', function (): void {
    $user = User::factory()->create();
    $this->actingAs($user);

    $otherUser = User::factory()->create();
    $device = Device::factory()->create([
        'user_id' => $otherUser->id,
        'proxy_cloud' => false,
    ]);

    $response = Livewire::test('devices.manage')
        ->call('toggleProxyCloud', $device);

    $response->assertStatus(403);
    expect($device->fresh()->proxy_cloud)->toBeFalse();
});
```

(These are re-created against `devices.configure` in Task 5, Step 1.)

- [ ] **Step 4: Remove `toggleProxyCloud` from the component**

In `resources/views/livewire/devices/manage.blade.php:94-104`, delete:

```php
    public function toggleProxyCloud(Device $device): void
    {
        abort_unless(auth()->user()->devices->contains($device), 403);
        $device->update([
            'proxy_cloud' => ! $device->proxy_cloud,
        ]);

        // if ($device->proxy_cloud) {
        //     \App\Jobs\FetchProxyCloudResponses::dispatch();
        // }
    }

```

- [ ] **Step 5: Blank the Actions header**

In `resources/views/livewire/devices/manage.blade.php:231-234`, change:

```blade
                    <th class="py-3 px-3 first:pl-0 last:pr-0 text-left text-sm font-medium text-zinc-800 dark:text-white"
                        data-flux-column="">
                        <div class="whitespace-nowrap flex group-[]/right-align:justify-end">Actions</div>
                    </th>
```

to:

```blade
                    <th class="py-3 px-3 first:pl-0 last:pr-0 text-left text-sm font-medium text-zinc-800 dark:text-white"
                        data-flux-column="">
                        <div class="whitespace-nowrap flex group-[]/right-align:justify-end"></div>
                    </th>
```

- [ ] **Step 6: Remove the Refresh column header**

In `resources/views/livewire/devices/manage.blade.php:227-230`, delete:

```blade
                    <th class="py-3 px-3 first:pl-0 last:pr-0 text-left text-sm font-medium text-zinc-800 dark:text-white"
                        data-flux-column="">
                        <div class="whitespace-nowrap flex group-[]/right-align:justify-end">Refresh</div>
                    </th>
```

- [ ] **Step 7: Remove the Refresh column cell**

In `resources/views/livewire/devices/manage.blade.php:256-259`, delete:

```blade
                        <td class="py-3 px-3 first:pl-0 last:pr-0 text-sm whitespace-nowrap  text-zinc-500 dark:text-zinc-300"
                        >
                            {{ $device->default_refresh_interval }}
                        </td>
```

- [ ] **Step 8: Swap the eye icon for pencil and remove the proxy switch**

In `resources/views/livewire/devices/manage.blade.php:260-288`, change:

```blade
                        <td class="py-3 px-3 first:pl-0 last:pr-0 text-sm whitespace-nowrap  font-medium text-zinc-800 dark:text-white"
                        >
                            <div class="flex items-center gap-4">
                                <flux:button.group>

                                <flux:button href="{{ route('devices.configure', $device) }}" wire:navigate icon="eye" iconVariant="outline">
                                </flux:button>
                                @if($device->isPauseActive())
                                    <flux:tooltip content="Device paused until: {{ $device->pause_until?->format('H:i') }}">
                                        <flux:button icon="pause-circle"/>
                                    </flux:tooltip>
                                @else
                                    <flux:modal.trigger name="pause-device-{{ $device->id }}">
                                        <flux:button icon="pause-circle" iconVariant="outline">
                                        </flux:button>
                                    </flux:modal.trigger>
                                @endif
                                </flux:button.group>

                                <flux:tooltip
                                    content="Proxies images from the TRMNL Cloud service when no image is set (available in TRMNL DEV Edition only)."
                                    position="bottom">
                                    <flux:switch wire:click="toggleProxyCloud({{ $device->id }})"
                                                 :checked="$device->proxy_cloud"
                                                 :disabled="$device->mirror_device_id !== null"
                                                 label="☁️ Proxy"/>
                                </flux:tooltip>
                            </div>
                        </td>
```

to:

```blade
                        <td class="py-3 px-3 first:pl-0 last:pr-0 text-sm whitespace-nowrap  font-medium text-zinc-800 dark:text-white"
                        >
                            <div class="flex items-center gap-4">
                                <flux:button.group>

                                <flux:button href="{{ route('devices.configure', $device) }}" wire:navigate icon="pencil" iconVariant="outline">
                                </flux:button>
                                @if($device->isPauseActive())
                                    <flux:tooltip content="Device paused until: {{ $device->pause_until?->format('H:i') }}">
                                        <flux:button icon="pause-circle"/>
                                    </flux:tooltip>
                                @else
                                    <flux:modal.trigger name="pause-device-{{ $device->id }}">
                                        <flux:button icon="pause-circle" iconVariant="outline">
                                        </flux:button>
                                    </flux:modal.trigger>
                                @endif
                                </flux:button.group>
                            </div>
                        </td>
```

- [ ] **Step 9: Run test to verify it passes**

Run: `php artisan test --filter=ManageTest`
Expected: PASS — all tests in the file, including the new one, and confirming the two deleted tests no longer run.

- [ ] **Step 10: Commit**

```bash
git add resources/views/livewire/devices/manage.blade.php tests/Feature/Devices/ManageTest.php
git commit -m "feat: clean up devices list — pencil icon, drop refresh column and proxy switch"
```

---

### Task 5: `/devices/{device}/configure` — add proxy toggle and refresh interval badge

**Files:**
- Modify: `resources/views/livewire/devices/configure.blade.php`
- Test: `tests/Feature/Devices/DeviceConfigureTest.php`

**Interfaces:**
- Consumes: nothing from earlier tasks (independent of Tasks 1-4's view files, but logically completes the proxy-toggle relocation started in Task 4).
- Produces: `toggleProxyCloud(App\Models\Device $device): void` on the `devices.configure` Livewire component — same signature and behavior as the method removed from `devices.manage` in Task 4.

- [ ] **Step 1: Write the failing tests**

Add to `tests/Feature/Devices/DeviceConfigureTest.php`:

```php
test('configure view displays refresh interval badge', function (): void {
    $user = User::factory()->create();
    $device = Device::factory()->create([
        'user_id' => $user->id,
        'default_refresh_interval' => 900,
    ]);

    $response = actingAs($user)
        ->get(route('devices.configure', $device));

    $response->assertOk()
        ->assertSee('900s');
});

test('configure view displays proxy toggle', function (): void {
    $user = User::factory()->create();
    $device = Device::factory()->create(['user_id' => $user->id]);

    $response = actingAs($user)
        ->get(route('devices.configure', $device));

    $response->assertOk()
        ->assertSee('☁️ Proxy');
});

test('user can toggle proxy cloud from the configure page', function (): void {
    $user = User::factory()->create();
    actingAs($user);
    $device = Device::factory()->create([
        'user_id' => $user->id,
        'proxy_cloud' => false,
    ]);

    $response = Livewire::test('devices.configure', ['device' => $device])
        ->call('toggleProxyCloud', $device);

    $response->assertHasNoErrors();
    expect($device->fresh()->proxy_cloud)->toBeTrue();

    Livewire::test('devices.configure', ['device' => $device])
        ->call('toggleProxyCloud', $device);

    expect($device->fresh()->proxy_cloud)->toBeFalse();
});
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test --filter="DeviceConfigureTest"`
Expected: The three new tests FAIL — "900s" and "☁️ Proxy" aren't in the view yet, and `toggleProxyCloud` doesn't exist on this component.

- [ ] **Step 3: Add the `toggleProxyCloud` method**

In `resources/views/livewire/devices/configure.blade.php`, add this method to the component class, directly after the `deleteDevice` method (after line 145, before `updatedDeviceModelId`):

```php

    public function toggleProxyCloud(App\Models\Device $device): void
    {
        abort_unless(auth()->user()->devices->contains($device), 403);
        $device->update([
            'proxy_cloud' => ! $device->proxy_cloud,
        ]);
    }
```

- [ ] **Step 4: Add the refresh interval badge**

In `resources/views/livewire/devices/configure.blade.php:415-421`, change:

```blade
                        <flux:tooltip content="Last refresh" position="bottom">
                            <span class="dark:text-zinc-200">{{$device->last_refreshed_at?->diffForHumans()}}</span>
                        </flux:tooltip>
                        <flux:separator vertical/>
                        <flux:tooltip content="MAC Address" position="bottom">
                            <span class="dark:text-zinc-200">{{$device->mac_address}}</span>
                        </flux:tooltip>
```

to:

```blade
                        <flux:tooltip content="Last refresh" position="bottom">
                            <span class="dark:text-zinc-200">{{$device->last_refreshed_at?->diffForHumans()}}</span>
                        </flux:tooltip>
                        <flux:separator vertical/>
                        <flux:tooltip content="Refresh interval" position="bottom">
                            <span class="dark:text-zinc-200 inline-flex items-center gap-1">
                                <flux:icon.clock class="size-4"/>
                                {{ $device->default_refresh_interval }}s
                            </span>
                        </flux:tooltip>
                        <flux:separator vertical/>
                        <flux:tooltip content="MAC Address" position="bottom">
                            <span class="dark:text-zinc-200">{{$device->mac_address}}</span>
                        </flux:tooltip>
```

- [ ] **Step 5: Add the proxy toggle to the header actions**

In `resources/views/livewire/devices/configure.blade.php:451-472`, change:

```blade
                    <div>
                        <flux:modal.trigger name="edit-device">
                            <flux:button icon="pencil-square" />
                        </flux:modal.trigger>

                        <flux:dropdown>
```

to:

```blade
                    <div class="flex items-center gap-3">
                        <flux:tooltip
                            content="Proxies images from the TRMNL Cloud service when no image is set (available in TRMNL DEV Edition only)."
                            position="bottom">
                            <flux:switch wire:click="toggleProxyCloud({{ $device->id }})"
                                         :checked="$device->proxy_cloud"
                                         :disabled="$device->mirror_device_id !== null"
                                         label="☁️ Proxy"/>
                        </flux:tooltip>

                        <flux:modal.trigger name="edit-device">
                            <flux:button icon="pencil-square" />
                        </flux:modal.trigger>

                        <flux:dropdown>
```

(The `</div>` that already closes this block at line 472 needs no change — it now closes the wider `<div class="flex items-center gap-3">`.)

- [ ] **Step 6: Run test to verify it passes**

Run: `php artisan test --filter="DeviceConfigureTest"`
Expected: PASS — all tests in the file, including the three new ones.

- [ ] **Step 7: Commit**

```bash
git add resources/views/livewire/devices/configure.blade.php tests/Feature/Devices/DeviceConfigureTest.php
git commit -m "feat: add proxy toggle and refresh interval to device configure page"
```

---

### Task 6: Full regression pass

**Files:** none (verification only)

**Interfaces:**
- Consumes: all changes from Tasks 1-5.
- Produces: nothing.

- [ ] **Step 1: Run the full devices-related test suite**

Run: `php artisan test tests/Feature/DeviceModelsTest.php tests/Feature/Livewire/DevicePalettesTest.php tests/Feature/Devices/`
Expected: PASS — all tests green, no leftover references to the removed `toggleProxyCloud` on `devices.manage`.

- [ ] **Step 2: Run the full test suite**

Run: `php artisan test`
Expected: PASS — confirms no other test (e.g. `DeviceResourceNavigationTest`) incidentally depended on the removed Refresh column, proxy switch, or eye icon markup.

- [ ] **Step 3: Manual browser check**

Use the `run` skill (or `php artisan serve` + browser) to visually confirm, logged in as a user with at least one device:
- `/device-models`: modal opens wider; API-sourced row still shows eye icon (read-only); manual row still shows pencil; "Actions" header cell is present but blank.
- `/device-palettes`: "Actions" header cell is present but blank; icons unchanged.
- `/devices`: row action shows pencil (not eye) linking to configure; no Refresh column; no proxy switch; "Actions" header cell is present but blank.
- `/devices/{id}/configure`: header info row shows a new refresh-interval badge next to "Last refresh"; header actions area shows the "☁️ Proxy" switch next to the pencil/dropdown, and toggling it persists (reload page, state holds).

No commit for this task — verification only.
