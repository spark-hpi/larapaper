<?php

namespace App\Http\Requests\Api;

use Illuminate\Foundation\Http\FormRequest;

/**
 * The playlist settings exposed by the account API (PATCH /api/playlists/{id}).
 */
class UpdatePlaylistRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'name' => 'string|max:255',
            'weekdays' => 'nullable|array',
            'weekdays.*' => 'integer|between:0,6',
            'active_from' => 'nullable|date_format:H:i',
            'active_until' => 'nullable|date_format:H:i',
            'refresh_time' => 'nullable|integer|min:60',
            'is_active' => 'boolean',
        ];
    }

    /**
     * The given settings as playlist attributes. Fields that were not sent are left
     * untouched, while nullable fields can be cleared with an explicit null.
     *
     * @return array<string, mixed>
     */
    public function playlistAttributes(): array
    {
        $attributes = $this->only(['name', 'weekdays', 'active_from', 'active_until', 'refresh_time']);

        if (array_key_exists('weekdays', $attributes) && $attributes['weekdays'] === []) {
            $attributes['weekdays'] = null;
        }

        if ($this->has('is_active')) {
            $attributes['is_active'] = $this->boolean('is_active');
        }

        return $attributes;
    }
}
