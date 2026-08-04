<?php

namespace App\Jobs;

use App\Models\Firmware;
use Exception;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class FirmwareDownloadJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(private Firmware $firmware) {}

    public function handle(): void
    {
        $disk = Storage::disk('public');

        if (! $disk->exists('firmwares')) {
            $disk->makeDirectory('firmwares');
        }

        try {
            $path = $this->firmware->storagePath();
            $response = Http::get($this->firmware->url);

            if (! $response->successful()) {
                throw new Exception('HTTP request failed with status: '.$response->status());
            }

            $disk->put($path, $response->body());

            $this->firmware->update([
                'storage_location' => $path,
            ]);
        } catch (ConnectionException $e) {
            Log::error('Firmware download failed: '.$e->getMessage());
        } catch (Exception $e) {
            Log::error('An unexpected error occurred: '.$e->getMessage());
        }
    }
}
