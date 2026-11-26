<?php

declare(strict_types=1);

namespace WebxUi\Widgets;

/**
 * A section that slides over the one above it (spec §14.1): what a block's template prints for it.
 *
 * `@webxOverlap($overlap)` on the section's root — ` data-webx-overlap`, or nothing when the
 * option is off; `@webxOverlapEdge($overlap, $edge, $edgeNarrow)` as its first child — the edge
 * picture over its top, a `<picture>` whose phone source is the second file. Either file alone is
 * the picture at every width. Off, or with no file, neither prints a character: a block that
 * never had the option keeps its markup.
 *
 * The edge is decoration (`alt=""`, `aria-hidden`): the fog says nothing a reader needs. Its
 * width and height go with it, so the browser knows its height before the file comes. Both files
 * are offered as the library's copies by width (`Pictures`) and in the formats the site cuts:
 * a 2560 px edge was fetched whole by a phone 412 px wide.
 */
final class Overlap
{
    /** Below this width of the screen the phone's file is the edge: the edge spans the page, not a container. */
    public const string NARROW = '(max-width: 40rem)';

    /** The edge spans the window at every width. */
    private const string SIZES = '100vw';

    /** The type a browser knows a format of the site's copies by. */
    private const array TYPES = ['avif' => 'image/avif', 'webp' => 'image/webp'];

    public static function attributes(mixed $on): string
    {
        return self::on($on) ? ' data-webx-overlap' : '';
    }

    public static function edge(mixed $on, mixed $edge = null, mixed $narrow = null): string
    {
        if (! self::on($on)) {
            return '';
        }

        $wide = self::file($edge);
        $phone = self::file($narrow);
        $main = $wide ?? $phone;
        if ($main === null) {
            return '';
        }

        $pictures = app(Pictures::class);
        $sources = '';

        // The phone's file first: its copies in the site's formats, then in its own.
        if ($wide !== null && $phone !== null) {
            foreach ($pictures->formatsBesides($phone) as $format) {
                if (($set = $pictures->sources($phone, false, $format)) !== null) {
                    $sources .= '<source media="'.self::NARROW.'" type="'.self::TYPES[$format].'" srcset="'.e($set).'" sizes="'.self::SIZES.'"'.self::size($phone).'>';
                }
            }

            $own = $pictures->sources($phone);
            $sources .= '<source media="'.self::NARROW.'" srcset="'.e($own ?? $phone['url']).'"'.($own !== null ? ' sizes="'.self::SIZES.'"' : '').self::size($phone).'>';
        }

        foreach ($pictures->formatsBesides($main) as $format) {
            if (($set = $pictures->sources($main, false, $format)) !== null) {
                $sources .= '<source type="'.self::TYPES[$format].'" srcset="'.e($set).'" sizes="'.self::SIZES.'">';
            }
        }

        $own = $pictures->sources($main);
        $choice = $own !== null ? ' srcset="'.e($own).'" sizes="'.self::SIZES.'"' : '';

        // Low priority, not lazy: on a phone the edge may be in the first screen, but it is fog —
        // the words of the page and its first picture come before it.
        return '<picture class="webx-overlap__edge" aria-hidden="true">'.$sources
            .'<img class="webx-overlap__image" src="'.e($main['url']).'"'.$choice.' alt=""'.self::size($main).' decoding="async" fetchpriority="low"></picture>';
    }

    /** Whether the switch of the block is on: a stored `true`, or what a form sends for it. */
    public static function on(mixed $on): bool
    {
        return $on === true || $on === 1 || $on === '1' || $on === 'true' || $on === 'on';
    }

    /**
     * @return array{url: string, width?: mixed, height?: mixed}|null
     */
    private static function file(mixed $file): ?array
    {
        if (! is_array($file) || ! is_string($file['url'] ?? null) || $file['url'] === '') {
            return null;
        }

        /** @var array{url: string, width?: mixed, height?: mixed} $file */
        return $file;
    }

    /**
     * @param  array{url: string, width?: mixed, height?: mixed}  $file
     */
    private static function size(array $file): string
    {
        $width = (int) ($file['width'] ?? 0);
        $height = (int) ($file['height'] ?? 0);

        return $width > 0 && $height > 0 ? ' width="'.$width.'" height="'.$height.'"' : '';
    }
}
