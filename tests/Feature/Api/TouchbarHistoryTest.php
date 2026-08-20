<?php

declare(strict_types=1);

use App\Models\Device;
use App\Models\Playlist;
use App\Models\PlaylistItem;
use App\Models\Plugin;
use App\Models\User;
use App\Services\DeviceScreenFilename;
use App\Services\ImageGenerationService;
use Bnussbau\EpaperPipeline\EpaperPipeline;
use Illuminate\Support\Facades\Storage;

beforeEach(function (): void {
    EpaperPipeline::fake();
    Storage::fake('public');
    Storage::disk('public')->makeDirectory('images/generated');
});

test('same plugin returns stable filename across polls without re-render', function (): void {
    $device = Device::factory()->create([
        'mac_address' => '55:11:22:33:44:01',
        'api_key' => 'touchbar-stable-key',
        'proxy_cloud' => false,
    ]);

    $plugin = Plugin::factory()->create([
        'name' => 'Stable Plugin',
        'data_strategy' => 'polling',
        'polling_url' => null,
        'polling_verb' => 'get',
        'data_stale_minutes' => 60,
        'data_payload_updated_at' => now(),
        'current_image' => 'touchbar-stable-image',
        'current_image_metadata' => ImageGenerationService::buildImageMetadataFromDevice($device),
    ]);

    Storage::disk('public')->put('images/generated/touchbar-stable-image.png', 'stable');

    $playlist = Playlist::factory()->create([
        'device_id' => $device->id,
        'is_active' => true,
        'weekdays' => null,
        'active_from' => null,
        'active_until' => null,
    ]);

    PlaylistItem::factory()->create([
        'playlist_id' => $playlist->id,
        'plugin_id' => $plugin->id,
        'order' => 1,
        'is_active' => true,
    ]);

    $headers = [
        'id' => $device->mac_address,
        'access-token' => $device->api_key,
        'rssi' => -70,
        'battery_voltage' => 3.8,
        'fw-version' => '1.0.0',
    ];

    $first = $this->withHeaders($headers)->get('/api/display')->assertOk()->json();
    $second = $this->withHeaders($headers)->get('/api/display')->assertOk()->json();

    expect($second['filename'])->toBe($first['filename']);
});

test('same plugin returns new timestamp when image file mtime changes', function (): void {
    $device = Device::factory()->create([
        'mac_address' => '55:11:22:33:44:02',
        'api_key' => 'touchbar-mtime-key',
        'proxy_cloud' => false,
    ]);

    $plugin = Plugin::factory()->create([
        'name' => 'Mtime Plugin',
        'data_strategy' => 'polling',
        'polling_url' => null,
        'polling_verb' => 'get',
        'data_stale_minutes' => 60,
        'data_payload_updated_at' => now(),
        'current_image' => 'touchbar-mtime-image',
        'current_image_metadata' => ImageGenerationService::buildImageMetadataFromDevice($device),
    ]);

    Storage::disk('public')->put('images/generated/touchbar-mtime-image.png', 'v1');
    touch(Storage::disk('public')->path('images/generated/touchbar-mtime-image.png'), 1_700_000_000);

    $playlist = Playlist::factory()->create([
        'device_id' => $device->id,
        'is_active' => true,
        'weekdays' => null,
        'active_from' => null,
        'active_until' => null,
    ]);

    PlaylistItem::factory()->create([
        'playlist_id' => $playlist->id,
        'plugin_id' => $plugin->id,
        'order' => 1,
        'is_active' => true,
    ]);

    $headers = [
        'id' => $device->mac_address,
        'access-token' => $device->api_key,
        'rssi' => -70,
        'battery_voltage' => 3.8,
        'fw-version' => '1.0.0',
    ];

    $first = $this->withHeaders($headers)->get('/api/display')->assertOk()->json();

    touch(Storage::disk('public')->path('images/generated/touchbar-mtime-image.png'), 1_700_000_100);

    $second = $this->withHeaders($headers)->get('/api/display')->assertOk()->json();

    expect(mb_substr($first['filename'], 0, 13))->toBe(mb_substr($second['filename'], 0, 13))
        ->and($second['filename'])->not->toBe($first['filename']);
});

test('playlist rotation produces different 13-char prefixes', function (): void {
    $device = Device::factory()->create([
        'mac_address' => '55:11:22:33:44:03',
        'api_key' => 'touchbar-rotation-key',
        'proxy_cloud' => false,
    ]);

    $imageMetadata = ImageGenerationService::buildImageMetadataFromDevice($device);

    $firstPlugin = Plugin::factory()->create([
        'name' => 'Rotation Plugin A',
        'data_strategy' => 'polling',
        'polling_url' => null,
        'polling_verb' => 'get',
        'data_stale_minutes' => 60,
        'data_payload_updated_at' => now(),
        'current_image' => 'touchbar-rotation-a',
        'current_image_metadata' => $imageMetadata,
    ]);

    $secondPlugin = Plugin::factory()->create([
        'name' => 'Rotation Plugin B',
        'data_strategy' => 'polling',
        'polling_url' => null,
        'polling_verb' => 'get',
        'data_stale_minutes' => 60,
        'data_payload_updated_at' => now(),
        'current_image' => 'touchbar-rotation-b',
        'current_image_metadata' => $imageMetadata,
    ]);

    Storage::disk('public')->put('images/generated/touchbar-rotation-a.png', 'a');
    Storage::disk('public')->put('images/generated/touchbar-rotation-b.png', 'b');

    $playlist = Playlist::factory()->create([
        'device_id' => $device->id,
        'is_active' => true,
        'weekdays' => null,
        'active_from' => null,
        'active_until' => null,
    ]);

    PlaylistItem::factory()->create([
        'playlist_id' => $playlist->id,
        'plugin_id' => $firstPlugin->id,
        'order' => 1,
        'is_active' => true,
        'last_displayed_at' => now()->subMinute(),
    ]);

    PlaylistItem::factory()->create([
        'playlist_id' => $playlist->id,
        'plugin_id' => $secondPlugin->id,
        'order' => 2,
        'is_active' => true,
        'last_displayed_at' => null,
    ]);

    $headers = [
        'id' => $device->mac_address,
        'access-token' => $device->api_key,
        'rssi' => -70,
        'battery_voltage' => 3.8,
        'fw-version' => '1.0.0',
    ];

    $first = $this->withHeaders($headers)->get('/api/display')->assertOk()->json();

    $this->travel(1)->seconds();

    $second = $this->withHeaders($headers)->get('/api/display')->assertOk()->json();

    expect(mb_substr($first['filename'], 0, 13))->not->toBe(mb_substr($second['filename'], 0, 13));
});

test('display and current_screen return matching filenames for the same plugin screen', function (): void {
    $device = Device::factory()->create([
        'mac_address' => '55:11:22:33:44:04',
        'api_key' => 'touchbar-parity-key',
        'proxy_cloud' => false,
    ]);

    $plugin = Plugin::factory()->create([
        'user_id' => $device->user_id,
        'data_strategy' => 'polling',
        'polling_url' => null,
        'polling_verb' => 'get',
        'data_stale_minutes' => 60,
        'data_payload_updated_at' => now(),
        'current_image' => 'touchbar-parity-image',
        'current_image_metadata' => ImageGenerationService::buildImageMetadataFromDevice($device),
    ]);

    Storage::disk('public')->put('images/generated/touchbar-parity-image.png', 'parity');

    $playlist = Playlist::factory()->create([
        'device_id' => $device->id,
        'is_active' => true,
        'weekdays' => null,
        'active_from' => null,
        'active_until' => null,
    ]);

    PlaylistItem::factory()->create([
        'playlist_id' => $playlist->id,
        'plugin_id' => $plugin->id,
        'order' => 1,
        'is_active' => true,
    ]);

    $device->update(['current_screen_image' => 'touchbar-parity-image']);

    $display = $this->withHeaders([
        'id' => $device->mac_address,
        'access-token' => $device->api_key,
        'rssi' => -70,
        'battery_voltage' => 3.8,
        'fw-version' => '1.0.0',
    ])->get('/api/display')->assertOk()->json();

    $currentScreen = $this->withHeaders([
        'access-token' => $device->api_key,
    ])->get('/api/current_screen')->assertOk()->json();

    expect($currentScreen['filename'])->toBe($display['filename']);
});

test('two users with same image uuid get different filenames', function (): void {
    $sharedImageUuid = 'shared-touchbar-image';

    $firstUser = User::factory()->create();
    $secondUser = User::factory()->create();

    $firstDevice = Device::factory()->create([
        'user_id' => $firstUser->id,
        'mac_address' => '55:11:22:33:44:05',
        'api_key' => 'touchbar-user-a-key',
        'current_screen_image' => $sharedImageUuid,
        'proxy_cloud' => false,
    ]);

    $secondDevice = Device::factory()->create([
        'user_id' => $secondUser->id,
        'mac_address' => '55:11:22:33:44:06',
        'api_key' => 'touchbar-user-b-key',
        'current_screen_image' => $sharedImageUuid,
        'proxy_cloud' => false,
    ]);

    $firstPlugin = Plugin::factory()->create([
        'user_id' => $firstUser->id,
        'current_image' => $sharedImageUuid,
        'data_strategy' => 'polling',
        'polling_url' => null,
        'polling_verb' => 'get',
        'data_stale_minutes' => 60,
        'data_payload_updated_at' => now(),
        'current_image_metadata' => ImageGenerationService::buildImageMetadataFromDevice($firstDevice),
    ]);

    $secondPlugin = Plugin::factory()->create([
        'user_id' => $secondUser->id,
        'current_image' => $sharedImageUuid,
        'data_strategy' => 'polling',
        'polling_url' => null,
        'polling_verb' => 'get',
        'data_stale_minutes' => 60,
        'data_payload_updated_at' => now(),
        'current_image_metadata' => ImageGenerationService::buildImageMetadataFromDevice($secondDevice),
    ]);

    Storage::disk('public')->put('images/generated/'.$sharedImageUuid.'.png', 'shared');

    $firstPlaylist = Playlist::factory()->create(['device_id' => $firstDevice->id, 'is_active' => true]);
    $secondPlaylist = Playlist::factory()->create(['device_id' => $secondDevice->id, 'is_active' => true]);

    PlaylistItem::factory()->create([
        'playlist_id' => $firstPlaylist->id,
        'plugin_id' => $firstPlugin->id,
        'order' => 1,
        'is_active' => true,
    ]);

    PlaylistItem::factory()->create([
        'playlist_id' => $secondPlaylist->id,
        'plugin_id' => $secondPlugin->id,
        'order' => 1,
        'is_active' => true,
    ]);

    $firstDisplay = $this->withHeaders([
        'id' => $firstDevice->mac_address,
        'access-token' => $firstDevice->api_key,
        'rssi' => -70,
        'battery_voltage' => 3.8,
        'fw-version' => '1.0.0',
    ])->get('/api/display')->assertOk()->json();

    $secondDisplay = $this->withHeaders([
        'id' => $secondDevice->mac_address,
        'access-token' => $secondDevice->api_key,
        'rssi' => -70,
        'battery_voltage' => 3.8,
        'fw-version' => '1.0.0',
    ])->get('/api/display')->assertOk()->json();

    expect($firstDisplay['filename'])->not->toBe($secondDisplay['filename'])
        ->and(mb_substr($firstDisplay['filename'], 0, 13))->not->toBe(mb_substr($secondDisplay['filename'], 0, 13));
});

test('mirrored device uses mirror identity instead of source plugin identity', function (): void {
    $sourceDevice = Device::factory()->create([
        'mac_address' => '55:11:22:33:44:07',
        'api_key' => 'touchbar-source-key',
        'current_screen_image' => 'touchbar-mirror-source',
    ]);

    $sourcePlugin = Plugin::factory()->create([
        'user_id' => $sourceDevice->user_id,
        'current_image' => 'touchbar-mirror-source',
    ]);

    Storage::disk('public')->put('images/generated/touchbar-mirror-source.png', 'source');

    $mirrorDevice = Device::factory()->create([
        'mac_address' => '55:11:22:33:44:08',
        'api_key' => 'touchbar-mirror-key',
        'mirror_device_id' => $sourceDevice->id,
    ]);

    $mirrorResponse = $this->withHeaders([
        'id' => $mirrorDevice->mac_address,
        'access-token' => $mirrorDevice->api_key,
        'rssi' => -70,
        'battery_voltage' => 3.8,
        'fw-version' => '1.0.0',
    ])->get('/api/display')->assertOk()->json();

    $screenFilename = app(DeviceScreenFilename::class);
    $path = 'images/generated/touchbar-mirror-source.png';

    expect($mirrorResponse['filename'])->toBe(
        $screenFilename->make($path, 'mirror:'.$sourceDevice->id, DeviceScreenFilename::PREFIX_SYSTEM)
    )->not->toBe(
        $screenFilename->make($path, 'plugin:'.$sourcePlugin->id, DeviceScreenFilename::PREFIX_PLUGIN)
    );
});

test('sleep mode returns screen prefix with sleep identity', function (): void {
    $device = Device::factory()->create([
        'mac_address' => '55:11:22:33:44:09',
        'api_key' => 'touchbar-sleep-key',
        'sleep_mode_enabled' => true,
        'sleep_mode_from' => '19:00',
        'sleep_mode_to' => '23:00',
        'current_screen_image' => 'ignored-during-sleep',
    ]);

    Carbon\Carbon::setTestNow(Carbon\Carbon::parse('2000-01-01 20:00:00'));

    $response = $this->withHeaders([
        'id' => $device->mac_address,
        'access-token' => $device->api_key,
        'rssi' => -70,
        'battery_voltage' => 3.8,
        'fw-version' => '1.0.0',
    ])->get('/api/display')->assertOk()->json();

    $imagePath = 'images/generated/'.basename(parse_url((string) $response['image_url'], PHP_URL_PATH));
    $screenFilename = app(DeviceScreenFilename::class);

    expect($response['filename'])->toBe(
        $screenFilename->make($imagePath, 'sleep', DeviceScreenFilename::PREFIX_SYSTEM)
    )->toMatch('/^'.preg_quote(DeviceScreenFilename::PREFIX_SYSTEM, '/').'[a-f0-9]{6}-\d{10}$/');

    Carbon\Carbon::setTestNow();
});
