# Devices UI Adjustments — Design

## Goal

Small set of UI cleanups across the devices/device-models/device-palettes pages, and moving the proxy toggle + refresh interval display from the devices list into the per-device configure page.

## Scope

Four Livewire views, all under `resources/views/livewire/`:

- `device-models/index.blade.php`
- `device-palettes/index.blade.php`
- `devices/manage.blade.php` (route `/devices`)
- `devices/configure.blade.php` (route `/devices/{device}/configure`)

No database/model changes. No new routes.

## Changes

### 1. `/device-models` — wider modal

`flux:modal name="device-model-modal" class="md:w-96"` → `class="md:w-[36rem]"`.

Purely visual; form contents unchanged.

### 2. Eye → edit icon; empty "Actions" header

- `devices/manage.blade.php`: the row action button that links to `devices.configure` currently uses `icon="eye"`. Change to `icon="pencil"`. No behavior change — same link, same route.
- `device-models/index.blade.php` and `device-palettes/index.blade.php`: the eye icon there opens a **read-only** modal for API-sourced rows (`source === 'api'`). Per decision, this stays an eye icon and stays read-only — not touched.
- Table headers: in all three views (`device-models`, `device-palettes`, `devices`), the `<th>` currently containing the text "Actions" keeps the `<th>` and its layout wrapper `<div>`, but the text content is removed (empty cell, header row width/alignment unaffected).

### 3. `/devices` list — remove proxy toggle and refresh column

In `devices/manage.blade.php`:
- Remove the `Refresh` column: the `<th>` header cell and the `<td>` cell rendering `{{ $device->default_refresh_interval }}`.
- Remove the proxy `flux:switch` (with its `flux:tooltip` wrapper) from the Actions cell. The `toggleProxyCloud()` method stays on the `manage.blade.php` Livewire component only if still referenced elsewhere in that component — it is not, so it moves to `configure.blade.php` (see below) and is deleted from `manage.blade.php`.

### 4. `/devices/{device}/configure` — add proxy toggle + refresh interval

Reuse the existing top info-bar / header-action pattern already present in this view (tooltip badges row for MAC/firmware/wifi/battery, `flux:modal.trigger` pencil button, `flux:dropdown` for secondary actions) — this is the same structural pattern the recipe page (`plugins/recipe.blade.php`) uses for its header (title + badge, `flux:button.group` primary actions, `flux:dropdown` secondary menu). No new pattern is introduced; existing elements are extended:

- **Refresh interval badge**: add a new `flux:tooltip` + icon badge to the existing info row (alongside "Last refresh", MAC, firmware, wifi, battery), showing `{{ $device->default_refresh_interval }}s` with a clock/arrow-path icon and tooltip text "Refresh interval".
- **Proxy toggle**: add a `flux:switch` (same label/tooltip copy as removed from `manage.blade.php`: "☁️ Proxy", tooltip "Proxies images from the TRMNL Cloud service when no image is set (available in TRMNL DEV Edition only)."), placed in the header action area next to the pencil/dropdown. Disabled when `$device->mirror_device_id !== null` (same rule as before).
- **Component change**: add `toggleProxyCloud(Device $device)` method to `devices/configure.blade.php`'s Livewire component (moved from `manage.blade.php`, same implementation — toggles `proxy_cloud`, guarded by `abort_unless(auth()->user()->devices->contains($device), 403)`).

The existing `default_refresh_interval` field in the "Edit TRMNL" modal is untouched — the new badge is a read-only display; editing the interval still happens through that modal.

## Out of scope

- No change to the "Edit TRMNL" modal fields.
- No change to device-models/device-palettes view-only (eye) behavior.
- No change to playlists, firmware, or mirror sections of the configure page.

## Testing

Existing feature tests touch this area (`tests/Feature/Devices/DeviceConfigureTest.php`, `tests/Feature/Devices/DeviceTest.php`). If any assert on the presence of "Refresh" column text, proxy switch on `/devices`, or the eye icon on the configure-link button, they'll need updating to match. No new automated tests planned beyond keeping these green — this is a pure view/markup change plus one method relocation.
