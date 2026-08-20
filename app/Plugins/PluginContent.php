<?php

namespace App\Plugins;

use App\Plugins\Enums\PluginOutput;

/**
 * Immutable value object returned by PluginHandler::produce().
 *
 * Exactly one of $html, $binary, or $uuid is populated depending on $type.
 */
final readonly class PluginContent
{
    private function __construct(
        public PluginOutput $type,
        public ?string $html = null,
        public ?string $binary = null,
        public ?string $uuid = null,
        public ?string $extension = null,
    ) {}

    public static function html(string $markup): self
    {
        return new self(
            type: PluginOutput::Html,
            html: $markup,
        );
    }

    public static function image(string $binary, string $extension): self
    {
        return new self(
            type: PluginOutput::Image,
            binary: $binary,
            extension: mb_strtolower($extension),
        );
    }

    public static function processedImage(string $uuid, string $extension): self
    {
        return new self(
            type: PluginOutput::ProcessedImage,
            uuid: $uuid,
            extension: mb_strtolower($extension),
        );
    }
}
