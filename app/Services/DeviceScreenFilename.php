<?php

namespace App\Services;

use Illuminate\Support\Facades\Storage;
use Throwable;

/**
 * Builds the `filename` the firmware receives alongside a screen image.
 *
 * TRMNL X keeps a browsable history of recent screens in SPIFFS and prunes it in
 * `filesystem_purge_old_file()`: a cached file is deleted when its name shares the
 * first 14 characters with the incoming one (same screen, newer render), or when the
 * last 10 characters of the name are not a Unix timestamp inside the last 24 hours.
 * A name without that trailing timestamp reads as expired, so the whole history is
 * wiped on every download and the touchbar has nothing to scroll through.
 *
 * The name therefore has to look like `<prefix:7><identity:6>-<timestamp:10>`, e.g.
 * `plugin-a9a4d9-1785312389`, which the firmware stores as `/plugin-a9a4d9-1785312389`:
 *
 *  - 25 characters, so `fixFileName()` keeps it verbatim (it mangles names over 31)
 *  - `/` + prefix + identity is exactly the 14-character "same screen" key, so a
 *    re-render replaces its predecessor instead of piling up a new history entry
 *  - the trailing 10 digits parse as the render time
 *
 * No extension: the firmware sniffs BMP vs PNG from the content bytes, and the cloud
 * omits it too. `image_url` carries the real path, so the two are independent.
 */
class DeviceScreenFilename
{
    public const PREFIX_PLUGIN = 'plugin-';

    public const PREFIX_MASHUP = 'mashup-';

    public const PREFIX_SYSTEM = 'screen-';

    /**
     * Unix timestamps are 10 digits wide from 2001-09-09 until 2286. The firmware
     * reads exactly the last 10 characters, so a narrower value would misparse.
     */
    private const MIN_TIMESTAMP = 1_000_000_000;

    public function make(?string $imagePath, string $identity, string $prefix = self::PREFIX_PLUGIN): ?string
    {
        if ($imagePath === null || $imagePath === '') {
            return null;
        }

        return $prefix.$this->identity($identity).'-'.$this->timestamp($imagePath);
    }

    /**
     * Stable 6-character key for "which screen is this", independent of how many
     * times it has been re-rendered.
     */
    private function identity(string $seed): string
    {
        return mb_substr(md5($seed), 0, 6);
    }

    /**
     * The image's own mtime, not the current time: the firmware skips the download
     * when the name is unchanged, so a name that moved on every poll would cost a
     * full image transfer on every wake.
     */
    private function timestamp(string $imagePath): int
    {
        $disk = Storage::disk('public');

        try {
            $modified = $disk->exists($imagePath) ? (int) $disk->lastModified($imagePath) : 0;
        } catch (Throwable) {
            $modified = 0;
        }

        // An unreadable mtime would otherwise stamp the screen as ancient and get it
        // purged on arrival; treat it as fresh and accept the redundant download.
        if ($modified <= 0) {
            $modified = now()->timestamp;
        }

        return max($modified, self::MIN_TIMESTAMP);
    }
}
