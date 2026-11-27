<?php

declare(strict_types=1);

namespace WebxUi\Widgets;

use Illuminate\Contracts\Config\Repository as Config;
use Illuminate\Contracts\Container\Container;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Contracts\View\Factory as ViewFactory;
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
 * from `thumbs.srcset_from` up and narrower than the picture). One that is not cut yet is left out
 * of this page and cut after its response has gone: the visitor who happened to be first does not
 * wait for the resize, the next page lists it. Without `module-media` — or for a picture that is
 * not in the library — the copies are the square preview and the picture itself, as they always
 * were.
 *
 * A site that cuts its copies in formats of their own (`webx-media.thumbs.formats`: avif, webp)
 * gets its blocks' pictures as `<picture>`: a `<source>` per format before the block's own `<img>`
 * (WIDGETS §14.2) — `@webxPicture` … `@endwebxPicture` around it, which without the formats, and
 * without a picture for the phone, prints the `<img>` as it is. The first screen's picture is
 * preloaded from the head, and the pictures nobody sees yet — slides past the first in view — are
 * held back until the widget shows them (`defer()`).
 *
 * Bound per request (`scoped`): a variant is put off once per request.
 */
final class Pictures
{
    /** The formats a variant is cut in besides the picture's own, and the type a browser knows them by. */
    private const array TYPES = ['avif' => 'image/avif', 'webp' => 'image/webp', 'jpg' => 'image/jpeg', 'jpeg' => 'image/jpeg', 'png' => 'image/png'];

    /** A cell is never narrower than this (the grid's `minmax(9em, …)`), with the grid's gap. */
    private const float CELL_EM = 9.0;

    private const float GAP_EM = 0.75;

    /**
     * The page's gutters on both sides of the column a `sizes` query does not see: it asks of the
     * window, the grid counts its columns by the block. Taken a little wide, a screen just past the
     * width of N columns is asked for N - 1 — a copy larger than the cell, never a smaller one.
     */
    private const float GUTTERS_EM = 3.0;

    /** The mosaic's large picture stands beside the two small ones from this width of the block. */
    private const string MOSAIC_WIDE = '(min-width: 36em)';

    /** The attributes a held picture keeps its addresses under until the widget shows it. */
    private const array HELD = ['src' => 'data-webx-src', 'srcset' => 'data-webx-srcset'];

    /** @var array<string, true> What this request has already put off until its response is gone. */
    private array $scheduled = [];

    /** @var list<array{mixed, string, array<string, mixed>}> The `@webxPicture`s open, innermost last. */
    private array $open = [];

    /** @var list<bool> The `@webxHeld`s open, innermost last. */
    private array $held = [];

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

    /**
     * The same in a format of its own (`avif`): its variants by width and one as wide as the
     * picture, which stands in for the picture itself. Null for the picture's own format — that
     * is `sources()` — or when none is cut yet.
     */
    public function sources(mixed $picture, bool $preview = false, ?string $format = null): ?string
    {
        if (! is_array($picture) || ! is_string($picture['url'] ?? null) || $picture['url'] === '') {
            return null;
        }

        $width = is_numeric($picture['width'] ?? null) ? (int) $picture['width'] : 0;

        // A `w` descriptor is the picture's own width: without it there is nothing to say.
        if ($width <= 0) {
            return null;
        }

        $format = $format !== null && $format !== $this->formatOf($picture) ? $format : null;
        $candidates = [];
        $thumb = is_string($picture['thumb'] ?? null) ? $picture['thumb'] : '';

        if ($format === null && $preview && $thumb !== '' && $thumb !== $picture['url'] && $width > 320) {
            $candidates[320] = $thumb;
        }

        foreach ($this->variants($picture, $width, $format) as $size => $url) {
            $candidates[$size] = $url;
        }

        if ($candidates === []) {
            return null;
        }

        if ($format === null) {
            $candidates[$width] = $picture['url'];
        }

        ksort($candidates);

        return implode(', ', array_map(static fn (int $size, string $url): string => "{$url} {$size}w", array_keys($candidates), $candidates));
    }

    /**
     * The formats the site cuts its copies in besides a picture's own, best first; empty — as by
     * default — and a block prints its `<img>` as it always did.
     *
     * @return list<string>
     */
    public function formats(): array
    {
        $formats = array_map(static fn (mixed $format): string => strtolower(trim((string) $format)), (array) $this->config->get('webx-media.thumbs.formats', []));

        return array_values(array_unique(array_filter($formats, static fn (string $format): bool => in_array($format, ['avif', 'webp'], true))));
    }

    /**
     * The formats a picture is offered in besides its own: a `<source>` of its own format would
     * say what its `<img>` (or its own `<source>`) already says.
     *
     * @return list<string>
     */
    public function formatsBesides(mixed $picture): array
    {
        return is_array($picture) ? array_values(array_diff($this->formats(), [$this->formatOf($picture)])) : $this->formats();
    }

    /** Whether the pictures nobody sees yet are held back (`webx-widgets.pictures.defer`, null: with the formats). */
    public function deferring(): bool
    {
        $defer = $this->config->get('webx-widgets.pictures.defer');

        return is_bool($defer) ? $defer : $this->formats() !== [];
    }

    /**
     * `@webxPicture($picture, $sizes, $options)`: what the `<img>` up to `@endwebxPicture` is a
     * picture of. Options: `mobile` — a picture for the phone (a value of a media field), `lead` —
     * the first screen's, preloaded from the head, `preview` — the grid's square preview listed.
     *
     * @param  array<string, mixed>  $options
     */
    public function open(mixed $picture = null, ?string $sizes = null, array $options = []): void
    {
        $this->open[] = [$picture, $sizes ?? '100vw', $options];
    }

    /**
     * `@endwebxPicture`: the `<img>` printed between, as `<picture>` with a `<source>` per format
     * and the phone's picture before them — or as it is, without formats and a picture for the
     * phone: a block saved before prints byte for byte what it printed.
     */
    public function close(string $html): string
    {
        [$picture, $sizes, $options] = array_pop($this->open) ?? [null, '100vw', []];

        if (! is_array($picture) || ! is_string($picture['url'] ?? null) || $picture['url'] === '') {
            return $html;
        }

        $mobile = self::shown($options['mobile'] ?? null) ? $options['mobile'] : null;
        $formats = $this->formats();

        if (($formats === [] && $mobile === null) || ! preg_match('~<img\b[^>]*>~i', $html, $found, PREG_OFFSET_CAPTURE)) {
            return $html;
        }

        [$image, $at] = $found[0];
        $own = $this->sources($picture, (bool) ($options['preview'] ?? false));

        if ($own !== null && ! preg_match('~\ssrcset\s*=~i', $image)) {
            $image = (string) preg_replace('~\s*/?>$~', ' srcset="'.e($own).'" sizes="'.e($sizes).'">', $image);
        }

        $sources = [];
        $media = (string) $this->config->get('webx-widgets.pictures.mobile', '(max-width: 768px)');

        if ($mobile !== null) {
            foreach (array_diff($formats, [$this->formatOf($mobile)]) as $format) {
                if (($set = $this->sources($mobile, false, $format)) !== null) {
                    $sources[] = ['media' => $media, 'type' => self::TYPES[$format], 'srcset' => $set, 'sizes' => $sizes] + self::box($mobile);
                }
            }

            $type = self::TYPES[$this->formatOf($mobile)] ?? null;
            $sources[] = array_filter(['media' => $media, 'type' => $type, 'srcset' => $this->sources($mobile) ?? $mobile['url'], 'sizes' => $this->sources($mobile) !== null ? $sizes : null]) + self::box($mobile);
        }

        // The picture's own format is the `<img>`'s: a `<source>` of it would say the same twice.
        foreach (array_diff($formats, [$this->formatOf($picture)]) as $format) {
            if (($set = $this->sources($picture, false, $format)) !== null) {
                $sources[] = ['type' => self::TYPES[$format], 'srcset' => $set, 'sizes' => $sizes];
            }
        }

        if (! empty($options['lead'])) {
            $this->preload($picture, $own, $sources, $sizes, $mobile !== null ? $media : null);
        }

        if ($sources === [] && $image === $found[0][0]) {
            return $html;
        }

        // `display: contents` hands the `<source>`s to the picture's parent: in a flex or grid
        // container each was an item of its own, an empty one with the container's `gap` beside
        // it. Hidden inline, as the picture is made boxless inline: wherever the directive prints,
        // with or without the runtime's stylesheet (deferred on a site with critical CSS).
        $printed = implode('', array_map(static fn (array $source): string => '<source'.self::attributes($source).' style="display: none">', $sources));

        return substr($html, 0, $at).'<picture class="webx-picture" style="display: contents">'.$printed.$image.'</picture>'.substr($html, $at + strlen($found[0][0]));
    }

    /**
     * `@webxHeld(<when>)` … `@endwebxHeld`: what is printed between held back, when `<when>` and
     * the site holds back pictures nobody sees yet.
     */
    public function hold(bool $when = true): void
    {
        $this->held[] = $when;
    }

    public function release(string $html): string
    {
        return array_pop($this->held) ? self::defer($html) : $html;
    }

    /**
     * The pictures of `$html` held back until the widget shows them: `src` and `srcset` of every
     * `<img>` and `<source>` under `data-webx-src` / `data-webx-srcset`, which the slider, the
     * photos and the tabs give back to the slide they show and to the next one. Without
     * `pictures.defer` the markup as it is.
     */
    public static function defer(string $html): string
    {
        if (! app(self::class)->deferring()) {
            return $html;
        }

        return (string) preg_replace_callback('~<(?:img|source)\b[^>]*>~i', static function (array $tag): string {
            return (string) preg_replace_callback(
                '~(\s)(src|srcset)(\s*=)~i',
                static fn (array $attribute): string => $attribute[1].self::HELD[strtolower($attribute[2])].$attribute[3],
                $tag[0],
            );
        }, $html);
    }

    /**
     * `sizes` of a grid's cell: up to `$across` columns, fewer where a cell would be narrower than
     * 9em — the number of columns a phone really shows, not half of the screen whatever it holds.
     * The window holds the page's gutters too (`GUTTERS_EM`): a phone whose column folds the grid
     * into one is asked for the whole width, not for half of it.
     */
    public static function gridSizes(int $across): string
    {
        $across = max(1, min(6, $across));
        $sizes = [];

        for ($columns = $across; $columns > 1; $columns--) {
            $from = $columns * self::CELL_EM + ($columns - 1) * self::GAP_EM + self::GUTTERS_EM;
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
     * The first screen's picture asked for from the head, before the stylesheet has said where it
     * stands: in the best format cut, at the width the screen needs — split by the phone's media
     * query when the block has a picture for the phone.
     *
     * @param  array<string, mixed>  $picture
     * @param  list<array<string, mixed>>  $sources
     */
    private function preload(array $picture, ?string $own, array $sources, string $sizes, ?string $media): void
    {
        $links = [];
        $wide = array_values(array_filter($sources, static fn (array $source): bool => ! isset($source['media'])));
        $narrow = array_values(array_filter($sources, static fn (array $source): bool => isset($source['media'])));
        $best = $wide[0] ?? ['srcset' => $own, 'type' => self::TYPES[$this->formatOf($picture)] ?? null];

        $links[] = ['href' => $best['srcset'] === null ? $picture['url'] : null, 'type' => $best['type'] ?? null, 'imagesrcset' => $best['srcset'], 'imagesizes' => $best['srcset'] !== null ? $sizes : null, 'media' => $media !== null ? "not all and {$media}" : null];

        if ($narrow !== []) {
            $links[] = ['type' => $narrow[0]['type'] ?? null, 'imagesrcset' => $narrow[0]['srcset'], 'imagesizes' => isset($narrow[0]['sizes']) ? $sizes : null, 'media' => $media];
        }

        $printed = implode("\n", array_map(static fn (array $link): string => '<link rel="preload" as="image"'.self::attributes(array_filter($link, static fn (mixed $value): bool => $value !== null)).' fetchpriority="high">', $links));

        try {
            $this->app->make(ViewFactory::class)->startPush('head', $printed."\n");
        } catch (Throwable) {
            // No view to push into — a block rendered alone: the picture keeps its own priority.
        }
    }

    /**
     * The library's variants of the picture by width, the ones already cut; the rest put off.
     *
     * @param  array<string, mixed>  $picture
     * @return array<int, string>
     */
    private function variants(array $picture, int $width, ?string $format): array
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
        // In a format of its own the picture itself is not there to be the widest: a copy as wide is.
        $widths = $format === null ? $this->widths($width) : [...$this->widths($width), $width];

        foreach ($widths as $size) {
            try {
                $key = $thumbnails->existing($file, $size, null, 'cover', $format);
            } catch (Throwable) {
                continue;
            }

            if ($key !== null) {
                $found[$size] = $urls->variantUrl($file, $key);
            } else {
                $this->schedule($file, $size, $format);
            }
        }

        return $found;
    }

    /** @return list<int> The widths of the library's variants a picture this wide has use for. */
    private function widths(int $width): array
    {
        $from = (int) $this->config->get('webx-media.thumbs.srcset_from', 480);
        $widths = array_filter(
            array_map(intval(...), (array) $this->config->get('webx-media.thumbs.widths', [])),
            static fn (int $size): bool => $size >= $from && $size < $width,
        );
        sort($widths);

        return array_values(array_unique($widths));
    }

    /** Whether the site has a library to cut copies in: its provider ran, not merely its classes are there. */
    private function available(): bool
    {
        return class_exists(Thumbnails::class) && $this->app->bound(MediaFiles::class);
    }

    private function schedule(MediaFile $file, int $size, ?string $format): void
    {
        $name = $file->getKey().'.'.$size.'.'.($format ?? '');

        if (isset($this->scheduled[$name]) || ! $this->app instanceof Application) {
            return;
        }

        $this->scheduled[$name] = true;

        $this->app->terminating(function () use ($file, $size, $format): void {
            try {
                $this->app->make(Thumbnails::class)->variant($file, $size, null, 'cover', $format);
            } catch (Throwable) {
                // An SVG, a HEIC, bytes gone: the picture whole, as before.
            }
        });
    }

    /** @param  array<string, mixed>  $picture */
    private function formatOf(array $picture): string
    {
        $path = parse_url((string) ($picture['path'] ?? $picture['url'] ?? ''), PHP_URL_PATH);
        $extension = strtolower(pathinfo(is_string($path) ? $path : '', PATHINFO_EXTENSION));

        return $extension === 'jpeg' ? 'jpg' : $extension;
    }

    private static function shown(mixed $picture): bool
    {
        return is_array($picture) && is_string($picture['url'] ?? null) && $picture['url'] !== '';
    }

    /**
     * The phone's picture says its own shape: a `<source>` with `width` and `height` keeps its box
     * where it differs from the wide one's (CLS).
     *
     * @param  array<string, mixed>  $picture
     * @return array<string, int>
     */
    private static function box(array $picture): array
    {
        $width = is_numeric($picture['width'] ?? null) ? (int) $picture['width'] : 0;
        $height = is_numeric($picture['height'] ?? null) ? (int) $picture['height'] : 0;

        return $width > 0 && $height > 0 ? ['width' => $width, 'height' => $height] : [];
    }

    /** @param  array<string, mixed>  $attributes */
    private static function attributes(array $attributes): string
    {
        $printed = '';

        foreach ($attributes as $name => $value) {
            $printed .= ' '.$name.'="'.e((string) $value).'"';
        }

        return $printed;
    }

    private static function number(float $value): string
    {
        return rtrim(rtrim(number_format($value, 2, '.', ''), '0'), '.');
    }
}
