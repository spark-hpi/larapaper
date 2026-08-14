<?php

namespace App\Models;

use App\Enums\FirmwareModel;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Firmware extends Model
{
    use HasFactory;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'latest' => 'boolean',
            'model' => FirmwareModel::class,
        ];
    }

    /**
     * @param  Builder<Firmware>  $query
     * @return Builder<Firmware>
     */
    public function scopeLatestVersion(Builder $query): Builder
    {
        return $query->where('latest', true);
    }

    /**
     * @param  Builder<Firmware>  $query
     * @return Builder<Firmware>
     */
    public function scopeForModel(Builder $query, FirmwareModel|string $model): Builder
    {
        return $query->where('model', $model instanceof FirmwareModel ? $model->value : $model);
    }

    /**
     * @param  Builder<Firmware>  $query
     * @return Builder<Firmware>
     */
    public function scopeOrderedForSelection(Builder $query): Builder
    {
        return $query->orderByDesc('latest')->latest();
    }

    public static function getLatest(?FirmwareModel $model = null): ?self
    {
        return self::query()
            ->latestVersion()
            ->when($model instanceof FirmwareModel, fn (Builder $query) => $query->forModel($model))
            ->first();
    }

    public static function upsertAsLatest(FirmwareModel $model, string $version, string $url): self
    {
        $firmware = self::updateOrCreate(
            [
                'version_tag' => $version,
                'model' => $model,
            ],
            [
                'url' => $url,
                'latest' => true,
            ]
        );

        self::query()
            ->forModel($model)
            ->whereKeyNot($firmware->id)
            ->update(['latest' => false]);

        return $firmware;
    }

    public function storagePath(): string
    {
        return "firmwares/{$this->model->value}/FW{$this->version_tag}.bin";
    }

    public function needsDownload(): bool
    {
        return filled($this->url) && $this->storage_location === null;
    }
}
