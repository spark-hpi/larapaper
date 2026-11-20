<?php

use App\Models\Device;
use App\Models\Playlist;
use App\Models\User;
use Laravel\Sanctum\Sanctum;

/**
 * @param  array<string, mixed>  $attributes
 * @return array{0: User, 1: Device, 2: Playlist}
 */
function createOwnedPlaylist(array $attributes = []): array
{
    $user = User::factory()->create();
    $device = Device::factory()->create(['user_id' => $user->id]);
    $playlist = Playlist::factory()->create(array_merge([
        'device_id' => $device->id,
    ], $attributes));

    return [$user, $device, $playlist];
}

test('authenticated user can update a playlist', function (): void {
    [$user, $device, $playlist] = createOwnedPlaylist([
        'name' => 'Old name',
        'is_active' => false,
        'weekdays' => null,
        'active_from' => null,
        'active_until' => null,
        'refresh_time' => null,
    ]);

    Sanctum::actingAs($user);

    $this->patchJson(route('api.playlists.update', $playlist), [
        'name' => 'Mattina',
        'weekdays' => [1, 2, 3, 4, 5],
        'active_from' => '07:00',
        'active_until' => '10:00',
        'refresh_time' => 300,
        'is_active' => true,
    ])
        ->assertOk()
        ->assertJson(['data' => [
            'id' => $playlist->id,
            'device_id' => $device->id,
            'name' => 'Mattina',
            'is_active' => true,
            'active_from' => '07:00',
            'active_until' => '10:00',
            'refresh_time' => 300,
        ]])
        ->assertJsonPath('data.weekdays', [1, 2, 3, 4, 5]);

    $playlist->refresh();
    expect($playlist->name)->toBe('Mattina')
        ->and($playlist->is_active)->toBeTrue()
        ->and($playlist->weekdays)->toBe([1, 2, 3, 4, 5])
        ->and($playlist->active_from->format('H:i'))->toBe('07:00')
        ->and($playlist->active_until->format('H:i'))->toBe('10:00')
        ->and($playlist->refresh_time)->toBe(300);
});

test('playlist update toggles is_active', function (): void {
    [$user, , $playlist] = createOwnedPlaylist(['is_active' => false]);

    Sanctum::actingAs($user);

    $this->patchJson(route('api.playlists.update', $playlist), ['is_active' => true])
        ->assertOk()
        ->assertJsonPath('data.is_active', true);

    expect($playlist->refresh()->is_active)->toBeTrue();

    // The boolean rule also accepts 0/1 and "1"/"0".
    $this->patchJson(route('api.playlists.update', $playlist), ['is_active' => 0])
        ->assertOk()
        ->assertJsonPath('data.is_active', false);

    expect($playlist->refresh()->is_active)->toBeFalse();
});

test('activating one playlist does not deactivate others on the same device', function (): void {
    [$user, $device, $requestedPlaylist] = createOwnedPlaylist(['is_active' => false]);
    $otherPlaylist = Playlist::factory()->create([
        'device_id' => $device->id,
        'is_active' => true,
    ]);

    Sanctum::actingAs($user);

    $this->patchJson(route('api.playlists.update', $requestedPlaylist), ['is_active' => true])
        ->assertOk();

    expect($requestedPlaylist->refresh()->is_active)->toBeTrue()
        ->and($otherPlaylist->refresh()->is_active)->toBeTrue();
});

test('playlist update leaves out fields that were not sent', function (): void {
    [$user, , $playlist] = createOwnedPlaylist([
        'name' => 'Kitchen',
        'is_active' => true,
        'weekdays' => [1, 2],
        'active_from' => '07:00',
        'active_until' => '10:00',
        'refresh_time' => 300,
    ]);

    Sanctum::actingAs($user);

    $this->patchJson(route('api.playlists.update', $playlist), ['name' => 'Living room'])
        ->assertOk();

    $playlist->refresh();
    expect($playlist->name)->toBe('Living room')
        ->and($playlist->is_active)->toBeTrue()
        ->and($playlist->weekdays)->toBe([1, 2])
        ->and($playlist->active_from->format('H:i'))->toBe('07:00')
        ->and($playlist->active_until->format('H:i'))->toBe('10:00')
        ->and($playlist->refresh_time)->toBe(300);
});

test('playlist update clears nullable fields with an explicit null', function (): void {
    [$user, , $playlist] = createOwnedPlaylist([
        'name' => 'Kitchen',
        'weekdays' => [1, 2],
        'active_from' => '07:00',
        'active_until' => '10:00',
        'refresh_time' => 300,
    ]);

    Sanctum::actingAs($user);

    $this->patchJson(route('api.playlists.update', $playlist), [
        'weekdays' => null,
        'active_from' => null,
        'active_until' => null,
        'refresh_time' => null,
    ])->assertOk();

    $playlist->refresh();
    expect($playlist->weekdays)->toBeNull()
        ->and($playlist->active_from)->toBeNull()
        ->and($playlist->active_until)->toBeNull()
        ->and($playlist->refresh_time)->toBeNull()
        ->and($playlist->name)->toBe('Kitchen');
});

test('playlist update normalizes empty weekdays to null', function (): void {
    [$user, , $playlist] = createOwnedPlaylist(['weekdays' => [1, 2]]);

    Sanctum::actingAs($user);

    $this->patchJson(route('api.playlists.update', $playlist), ['weekdays' => []])
        ->assertOk();

    expect($playlist->refresh()->weekdays)->toBeNull();
});

test('playlist update requires authentication with 401', function (): void {
    $playlist = Playlist::factory()->create(['name' => 'Kitchen']);

    $this->patchJson(route('api.playlists.update', $playlist), ['name' => 'Mine'])
        ->assertUnauthorized();

    expect($playlist->refresh()->name)->toBe('Kitchen');
});

test('playlist update returns 404 for a missing playlist', function (): void {
    $user = User::factory()->create();

    Sanctum::actingAs($user);

    $this->patchJson(route('api.playlists.update', 999999), ['name' => 'Mine'])
        ->assertNotFound();
});

test('user cannot update a playlist they do not own', function (): void {
    $otherUser = User::factory()->create();
    $device = Device::factory()->create(['user_id' => $otherUser->id]);
    $playlist = Playlist::factory()->create([
        'device_id' => $device->id,
        'name' => 'Kitchen',
    ]);

    Sanctum::actingAs(User::factory()->create());

    $this->patchJson(route('api.playlists.update', $playlist), ['name' => 'Mine'])
        ->assertNotFound();

    expect($playlist->refresh()->name)->toBe('Kitchen');
});

test('playlist update rejects a too long name with 422', function (): void {
    [$user, , $playlist] = createOwnedPlaylist(['name' => 'Kitchen']);

    Sanctum::actingAs($user);

    $this->patchJson(route('api.playlists.update', $playlist), ['name' => str_repeat('a', 256)])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['name']);

    expect($playlist->refresh()->name)->toBe('Kitchen');
});

test('playlist update rejects an invalid time format with 422', function (): void {
    [$user, , $playlist] = createOwnedPlaylist();

    Sanctum::actingAs($user);

    $this->patchJson(route('api.playlists.update', $playlist), ['active_from' => '7 ore'])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['active_from']);

    $this->patchJson(route('api.playlists.update', $playlist), ['active_until' => '25:00'])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['active_until']);
});

test('playlist update rejects a refresh time below 60 seconds with 422', function (): void {
    [$user, , $playlist] = createOwnedPlaylist(['refresh_time' => 300]);

    Sanctum::actingAs($user);

    $this->patchJson(route('api.playlists.update', $playlist), ['refresh_time' => 30])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['refresh_time']);

    expect($playlist->refresh()->refresh_time)->toBe(300);
});

test('playlist update rejects invalid weekdays with 422', function (): void {
    [$user, , $playlist] = createOwnedPlaylist(['weekdays' => [1, 2]]);

    Sanctum::actingAs($user);

    $this->patchJson(route('api.playlists.update', $playlist), ['weekdays' => 'lun'])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['weekdays']);

    $this->patchJson(route('api.playlists.update', $playlist), ['weekdays' => [1, 9]])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['weekdays.1']);

    expect($playlist->refresh()->weekdays)->toBe([1, 2]);
});

test('authenticated user can list their playlists', function (): void {
    $user = User::factory()->create();
    $device = Device::factory()->create(['user_id' => $user->id]);
    $otherDevice = Device::factory()->create(['user_id' => $user->id]);

    Playlist::factory()->count(2)->create(['device_id' => $device->id]);
    Playlist::factory()->create(['device_id' => $otherDevice->id]);

    $foreignPlaylist = Playlist::factory()->create([
        'device_id' => Device::factory()->create(['user_id' => User::factory()->create()->id])->id,
    ]);

    Sanctum::actingAs($user);

    $this->getJson(route('api.playlists.index'))
        ->assertOk()
        ->assertJsonCount(3, 'data')
        ->assertJsonStructure([
            'data' => [
                '*' => [
                    'id',
                    'device_id',
                    'name',
                    'is_active',
                    'weekdays',
                    'active_from',
                    'active_until',
                    'refresh_time',
                ],
            ],
        ])
        ->assertJsonMissing(['id' => $foreignPlaylist->id]);
});

test('playlist index can be filtered by device', function (): void {
    [$user, $device, $playlist] = createOwnedPlaylist();
    Playlist::factory()->create([
        'device_id' => Device::factory()->create(['user_id' => $user->id])->id,
    ]);

    Sanctum::actingAs($user);

    $this->getJson(route('api.playlists.index', ['device_id' => $device->id]))
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.id', $playlist->id);
});

test('playlist index returns no playlists for a device the user does not own', function (): void {
    $user = User::factory()->create();
    $foreignDevice = Device::factory()->create(['user_id' => User::factory()->create()->id]);

    Sanctum::actingAs($user);

    $this->getJson(route('api.playlists.index', ['device_id' => $foreignDevice->id]))
        ->assertOk()
        ->assertJsonCount(0, 'data');
});

test('playlist index requires authentication with 401', function (): void {
    Playlist::factory()->create();

    $this->getJson(route('api.playlists.index'))->assertUnauthorized();
});

test('playlist index rejects an invalid device_id with 422', function (): void {
    $user = User::factory()->create();

    Sanctum::actingAs($user);

    $this->getJson(route('api.playlists.index', ['device_id' => 'lun']))
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['device_id']);
});

test('playlist update returns the resource shape', function (): void {
    [$user, , $playlist] = createOwnedPlaylist();

    Sanctum::actingAs($user);

    $this->patchJson(route('api.playlists.update', $playlist))
        ->assertOk()
        ->assertJsonStructure([
            'data' => [
                'id',
                'device_id',
                'name',
                'is_active',
                'weekdays',
                'active_from',
                'active_until',
                'refresh_time',
            ],
        ]);
});
