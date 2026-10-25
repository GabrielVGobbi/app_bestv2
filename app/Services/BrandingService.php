<?php

declare(strict_types=1);

namespace App\Services;

use Illuminate\Support\Facades\Log;

final class BrandingService
{
    public const DEFAULT_HUE = 215;

    /**
     * @return array{name: string, shortName: string, hue: int, logo: array{mark: ?string, light: ?string, dark: ?string}}
     */
    public function toArray(): array
    {
        return [
            'name' => $this->name(),
            'shortName' => $this->shortName(),
            'hue' => $this->hue(),
            'logo' => [
                'mark' => $this->logoPath('mark'),
                'light' => $this->logoPath('light'),
                'dark' => $this->logoPath('dark'),
            ],
        ];
    }

    public function name(): string
    {
        $name = trim((string) config('branding.name', ''));

        return $name !== '' ? $name : (string) config('app.name', 'Laravel');
    }

    public function shortName(): string
    {
        $short = trim((string) config('branding.short_name', ''));

        if ($short === '') {
            $short = mb_substr($this->name(), 0, 2);
        }

        return mb_strtoupper(mb_substr($short, 0, 3));
    }

    public function hue(): int
    {
        $hue = config('branding.hue');

        if (! is_numeric($hue)) {
            return self::DEFAULT_HUE;
        }

        $hue = (int) $hue;

        return $hue >= 0 && $hue <= 360 ? $hue : self::DEFAULT_HUE;
    }

    public function logoPath(string $kind): ?string
    {
        $path = config("branding.logo.{$kind}");

        if (! is_string($path) || trim($path) === '') {
            return null;
        }

        $relative = '/'.ltrim(trim($path), '/');
        // Lexical check keeps the public/storage symlink usable, unlike realpath containment.
        $escapesPublic = in_array('..', preg_split('~[\\\\/]~', $relative), true);

        if ($escapesPublic || ! is_file(public_path($relative))) {
            Log::warning('Logo de marca não encontrada; usando o símbolo do kit.', [
                'kind' => $kind,
                'path' => $path,
            ]);

            return null;
        }

        return $relative;
    }
}
