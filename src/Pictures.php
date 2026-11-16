<?php

declare(strict_types=1);

namespace WebxUi\Widgets;

use Illuminate\Contracts\Config\Repository as Config;
use Illuminate\Contracts\Container\Container;
use Illuminate\Contracts\Foundation\Application;
use Throwable;
use WebxUi\Media\Images\Thumbnails;
use WebxUi\Media\Models\MediaFile;
use WebxUi\Media\Screens\MediaFiles;
use WebxUi\Media\Storage\FileUrls;

/**
 * What a block's picture of the library says to the browser beside its address: the smaller
 * copies to pick from (`srcset`) and how wide it stands (`sizes`). Without them a gallery's
 * pictures of 2560 px were fetched whole into cells a third of a phone wide.
 *
 * The copies are the library's own variants, cut by width (`webx-media.thumbs.widths`, the ones
 * from 480 up and narrower than the picture). One that is not cut yet is left out of this page
 * and cut after its response has gone: the visitor who happened to be first does not wait for
 * the resize, the next page lists it. Without `module-media` — or for a picture that is not in
 * the library — the copies are the square preview and the picture itself, as they always were.
 *
 * Bound per request (`scoped`): a variant is put off once per request.
 */
final class Pictures
{
    /** The narrowest copy worth listing: below it the square preview, or the picture, does. */
    private const int NARROWEST = 480;

    /** A cell is never narrower than this (the grid's `minmax(9em, …)`), with the grid's gap. */
    private const float CELL_EM = 9.0;

    private const float GAP_EM = 0.75;

    /** The mosaic's large picture stands beside the two small ones from this width of the block. */
    private const string MOSAIC_WIDE = '(min-width: 36em)';

    /** @var array<string, true> What this request has already put off until its response is gone. */
    private array $scheduled = [];

    public function __construct(
        private readonly Container $app,
        private readonly Config $config,
    ) {}

    /**
     * `srcset` of a library picture (`url`, `width`, `path`, `thumb` — a value of a media field),
     * or null when there is nothing but the picture itself to choose from. `$preview` lists the
     * library's square preview too: only where the cell is square, as the grid's is — anywhere
     * else it would show a different cut of the picture.
     */
    public static function srcset(mixed $picture, bool $preview = false): ?string
    {
        return app(self::class)->sources($picture, $preview);
    }

    public function sources(mixed $picture, bool $preview = false): ?string
    {
        if (! is_array($picture) || ! is_string($picture['url'] ?? null) || $picture['url'] === '') {
            return null;
        }

        $width = is_numeric($picture['width'] ?? null) ? (int) $picture['width'] : 0;

        // A `w` descriptor is the picture's own width: without it there is nothing to say.
        if ($width <= 0) {
            return null;
        }

        $candidates = [];
        $thumb = is_string($picture['thumb'] ?? null) ? $picture['thumb'] : '';

        if ($preview && $thumb !== '' && $thumb !== $picture['url'] && $width > 320) {
            $candidates[320] = $thumb;
        }

        foreach ($this->variants($picture, $width) as $size => $url) {
            $candidates[$size] = $url;
        }

        if ($candidates === []) {
            return null;
        }

        $candidates[$width] = $picture['url'];
        ksort($candidates);

        return implode(', ', array_map(static fn (int $size, string $url): string => "{$url} {$size}w", array_keys($candidates), $candidates));
    }

    /**
     * `sizes` of a grid's cell: up to `$across` columns, fewer where a cell would be narrower than
     * 9em — the number of columns a phone really shows, not half of the screen whatever it holds.
     */
    public static function gridSizes(int $across): string
    {
        $across = max(1, min(6, $across));
        $sizes = [];

        for ($columns = $across; $columns > 1; $columns--) {
            $from = $columns * self::CELL_EM + ($columns - 1) * self::GAP_EM;
            $sizes[] = '(min-width: '.self::number($from).'em) '.(int) ceil(100 / $columns).'vw';
        }

        $sizes[] = '100vw';

        return implode(', ', $sizes);
    }

    /**
     * `sizes` of a mosaic's tile: of three, the first two thirds of the block and the others one
     * third; in a narrow block the first is the whole width and the others half of it — or the
     * whole of it, one under another (`narrow: column`). Two are halves side by side and each the
     * whole width in a narrow block; one is the whole width.
     */
    public static function mosaicSizes(int $count, int $index, bool $column): string
    {
        return match (true) {
            $count >= 3 && $index === 0 => self::MOSAIC_WIDE.' 67vw, 100vw',
            $count >= 3 => self::MOSAIC_WIDE.' 34vw, '.($column ? '100vw' : '50vw'),
            $count === 2 => self::MOSAIC_WIDE.' 50vw, 100vw',
            default => '100vw',
        };
    }

    /**
     * The library's variants of the picture by width, the ones already cut; the rest put off.
     *
     * @param  array<string, mixed>  $picture
     * @return array<int, string>
     */
    private function variants(array $picture, int $width): array
    {
        $path = $picture['path'] ?? null;

        if (! is_string($path) || $path === '' || ! $this->available()) {
            return [];
        }

        try {
            $file = $this->app->make(MediaFiles::class)->find($path);
        } catch (Throwable) {
            // The library is installed and not migrated: the picture and its preview.
            return [];
        }

        if (! $file instanceof MediaFile || ! $file->isImage()) {
            return [];
        }

        $thumbnails = $this->app->make(Thumbnails::class);
        $urls = $this->app->make(FileUrls::class);
        $found = [];

        foreach ($this->widths($width) as $size) {
            try {
                $key = $thumbnails->existing($file, $size);
            } catch (Throwable) {
                continue;
            }

            if ($key !== null) {
                $found[$size] = $urls->variantUrl($file, $key);
            } else {
                $this->schedule($file, $size);
            }
        }

        return $found;
    }

    /** @return list<int> The widths of the library's variants a picture this wide has use for. */
    private function widths(int $width): array
    {
        $widths = array_filter(
            array_map(intval(...), (array) $this->config->get('webx-media.thumbs.widths', [])),
            static fn (int $size): bool => $size >= self::NARROWEST && $size < $width,
        );
        sort($widths);

        return array_values(array_unique($widths));
    }

    /** Whether the site has a library to cut copies in: its provider ran, not merely its classes are there. */
    private function available(): bool
    {
        return class_exists(Thumbnails::class) && $this->app->bound(MediaFiles::class);
    }

    private function schedule(MediaFile $file, int $size): void
    {
        $name = $file->getKey().'.'.$size;

        if (isset($this->scheduled[$name]) || ! $this->app instanceof Application) {
            return;
        }

        $this->scheduled[$name] = true;

        $this->app->terminating(function () use ($file, $size): void {
            try {
                $this->app->make(Thumbnails::class)->variant($file, $size, null, 'cover');
            } catch (Throwable) {
                // An SVG, a HEIC, bytes gone: the picture whole, as before.
            }
        });
    }

    private static function number(float $value): string
    {
        return rtrim(rtrim(number_format($value, 2, '.', ''), '0'), '.');
    }
}
