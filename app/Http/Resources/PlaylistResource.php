<?php

namespace App\Http\Resources;

use App\Models\Playlist;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * A playlist as returned by the account API (PATCH /api/playlists/{id}).
 *
 * @mixin Playlist
 */
class PlaylistResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'device_id' => $this->device_id,
            'name' => $this->name,
            'is_active' => $this->is_active,
            'weekdays' => $this->weekdays,
            'active_from' => $this->active_from?->format('H:i'),
            'active_until' => $this->active_until?->format('H:i'),
            'refresh_time' => $this->refresh_time,
        ];
    }
}
