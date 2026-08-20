<?php

namespace App\Enums;

use App\Models\Device;
use Illuminate\Support\Str;

enum FirmwareModel: string
{
    case Trmnl = 'trmnl';
    case TrmnlX = 'trmnl_x';

    public function label(): string
    {
        return match ($this) {
            self::Trmnl => 'TRMNL (OG)',
            self::TrmnlX => 'TRMNL X',
        };
    }

    /**
     * @return array<string, string>
     */
    public static function options(): array
    {
        return collect(self::cases())
            ->mapWithKeys(fn (self $model): array => [$model->value => $model->label()])
            ->all();
    }

    public static function forDevice(Device $device): self
    {
        return $device->usesTouchBar() ? self::TrmnlX : self::Trmnl;
    }

    /**
     * Derive the TRMNL X firmware URL from an OG firmware URL when possible.
     */
    public static function xUrlFromOg(string $ogUrl): ?string
    {
        $xUrl = Str::replaceFirst('_og', '_x', $ogUrl);

        return $xUrl !== $ogUrl ? $xUrl : null;
    }
}
