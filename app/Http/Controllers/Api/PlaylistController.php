<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\IndexPlaylistRequest;
use App\Http\Requests\Api\UpdatePlaylistRequest;
use App\Http\Resources\PlaylistResource;
use App\Models\Playlist;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class PlaylistController extends Controller
{
    public function index(IndexPlaylistRequest $request): AnonymousResourceCollection
    {
        $playlists = Playlist::query()
            ->whereHas('device', fn (Builder $query) => $query->where('user_id', $request->user()->id))
            ->when($request->has('device_id'), fn (Builder $query) => $query->where('device_id', $request->integer('device_id')))
            ->orderBy('id')
            ->get();

        return PlaylistResource::collection($playlists);
    }

    public function update(UpdatePlaylistRequest $request, Playlist $playlist): PlaylistResource
    {
        abort_unless($playlist->device->user_id === $request->user()->id, 404);

        $playlist->update($request->playlistAttributes());

        return new PlaylistResource($playlist->refresh());
    }
}
