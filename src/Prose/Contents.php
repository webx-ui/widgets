<?php

declare(strict_types=1);

namespace WebxUi\Widgets\Prose;

use Illuminate\Support\Str;

/**
 * The table of contents of a long page (spec §14), filled when the whole page is in hand.
 *
 * `<x-webx-toc>` prints a marker where its list goes and says which element its headings come
 * from: its own slot, an element by id, or `<main>`. That element may be printed after the
 * marker — the list stands above the text, or beside it — and may be any module's prose, so the
 * list is made here, from the finished page, as the tables of prose are (`Tables`).
 *
 * Each `h2` (and `h3`, to the depth asked) of the element is an item; a heading without an id gets
 * one from its words, in the page's language and unique on the page, so the links work without
 * JavaScript and a link to a section can be shared. An id the editor or the template gave is kept.
 * A heading with `data-webx-toc-skip` is left out.
 */
final class Contents
{
    public const string MARKER = '~<!--webx-toc:([A-Za-z0-9+/=]+)-->~';

    /** Where a list without a slot or an id comes from: `<main>`, or the body without one. */
    public const string MAIN = '<main>';

    /** What the browser reads as text, not markup: a heading written there is not a heading. */
    private const string RAW = '~<!--.*?-->|<(script|style|template|textarea)\b[^>]*>.*?</\1\s*>~is';

    private const string HEADING = '~<h([2-6])\b([^>]*)>(.*?)</h\1\s*>~is';

    private const string ID = '~\sid\s*=\s*(?:"([^"]*)"|\'([^\']*)\'|([^\s>"\']+))~i';

    /**
     * The page with every marker replaced by its list and the headings it lists given ids, and how
     * many lists had anything in them.
     *
     * @param  callable(array{id: string, title: string, beside: bool, items: list<array{id: string, text: string, children: list<array{id: string, text: string}>}>}): string  $nav  Renders one list.
     * @return array{0: string, 1: int}
     */
    public static function fill(string $html, callable $nav, string $locale = 'en'): array
    {
        $filled = 0;

        while (preg_match(self::MARKER, $html, $marker, PREG_OFFSET_CAPTURE) === 1) {
            $settings = json_decode((string) base64_decode($marker[1][0], true), true);
            $edits = [];
            $items = [];

            if (is_array($settings)) {
                $raw = self::raw($html);
                $range = self::element($html, (string) ($settings['from'] ?? self::MAIN), $raw);

                if ($range !== null) {
                    [$items, $edits] = self::headings($html, $range, (int) ($settings['depth'] ?? 3), $raw, $locale);
                }
            }

            $list = $items === [] ? '' : $nav([
                'id' => (string) ($settings['id'] ?? 'webx-toc'),
                'title' => (string) ($settings['title'] ?? ''),
                'items' => $items,
                'beside' => (bool) ($settings['beside'] ?? false),
            ]);
            $filled += $list === '' ? 0 : 1;
            $edits[] = [$marker[0][1], strlen($marker[0][0]), $list];

            // From the end, so each offset is still where it was found.
            usort($edits, static fn (array $a, array $b): int => $b[0] <=> $a[0]);

            foreach ($edits as [$at, $length, $text]) {
                $html = substr($html, 0, $at).$text.substr($html, $at + $length);
            }
        }

        return [$html, $filled];
    }

    /**
     * The headings of the range as a tree two levels deep, and the ids to write into them.
     *
     * @param  array{0: int, 1: int}  $range
     * @param  list<array{0: int, 1: int}>  $raw
     * @return array{0: list<array{id: string, text: string, children: list<array{id: string, text: string}>}>, 1: list<array{0: int, 1: int, 2: string}>}
     */
    private static function headings(string $html, array $range, int $depth, array $raw, string $locale): array
    {
        $inside = substr($html, $range[0], $range[1] - $range[0]);

        if (preg_match_all(self::HEADING, $inside, $found, PREG_OFFSET_CAPTURE | PREG_SET_ORDER) === 0) {
            return [[], []];
        }

        $taken = self::ids($html);
        $items = [];
        $edits = [];

        foreach ($found as $heading) {
            $level = (int) $heading[1][0];
            $at = $range[0] + $heading[0][1];

            if ($level > max(2, min(3, $depth)) || self::within($at, $raw) || preg_match('~\sdata-webx-toc-skip\b~i', $heading[2][0]) === 1) {
                continue;
            }

            $text = trim((string) preg_replace('/\s+/u', ' ', html_entity_decode(strip_tags($heading[3][0]), ENT_QUOTES | ENT_HTML5, 'UTF-8')));

            if ($text === '') {
                continue;
            }

            if (preg_match(self::ID, $heading[2][0], $id) === 1 && ($given = html_entity_decode($id[1] !== '' ? $id[1] : ($id[2] ?? '').($id[3] ?? ''), ENT_QUOTES | ENT_HTML5, 'UTF-8')) !== '') {
                $anchor = $given;
            } else {
                $anchor = self::unique(Str::slug($text, '-', $locale) ?: 'section', $taken);
                $taken[$anchor] = true;
                // Right after `<hN`: the heading keeps every attribute it had.
                $edits[] = [$at + 3, 0, ' id="'.e($anchor).'"'];
            }

            $item = ['id' => $anchor, 'text' => $text, 'children' => []];

            if ($level === 3 && $items !== []) {
                $items[array_key_last($items)]['children'][] = ['id' => $anchor, 'text' => $text];
            } else {
                $items[] = $item;
            }
        }

        return [$items, $edits];
    }

    /**
     * Where the element the list is made of starts and ends: its inside, without its own tags.
     *
     * @param  list<array{0: int, 1: int}>  $raw
     * @return array{0: int, 1: int}|null
     */
    private static function element(string $html, string $from, array $raw): ?array
    {
        $pattern = in_array($from, [self::MAIN, '<body>'], true)
            ? '~<('.trim($from, '<>').')\b[^>]*>~i'
            : '~<([a-z][a-z0-9-]*)\b[^>]*\sid\s*=\s*(["\']?)'.preg_quote($from, '~').'\2(?=[\s>/])[^>]*>~i';

        if (preg_match_all($pattern, $html, $opens, PREG_OFFSET_CAPTURE | PREG_SET_ORDER) === 0) {
            // A layout without <main> still has a body: the whole page is what the list is of.
            return $from === self::MAIN ? self::element($html, '<body>', $raw) : null;
        }

        foreach ($opens as $open) {
            if (self::within($open[0][1], $raw)) {
                continue;
            }

            $name = strtolower($open[1][0]);
            $start = $open[0][1] + strlen($open[0][0]);
            preg_match_all('~<(/?)'.$name.'\b[^>]*>~i', $html, $tags, PREG_OFFSET_CAPTURE | PREG_SET_ORDER, $start);
            $depth = 1;

            foreach ($tags as $tag) {
                if (self::within($tag[0][1], $raw)) {
                    continue;
                }

                $depth += $tag[1][0] === '' ? 1 : -1;

                if ($depth === 0) {
                    return [$start, $tag[0][1]];
                }
            }

            // Never closed: the rest of the page.
            return [$start, strlen($html)];
        }

        return null;
    }

    /** @return array<string, true> Every id already on the page. */
    private static function ids(string $html): array
    {
        preg_match_all(self::ID, $html, $found, PREG_SET_ORDER);
        $ids = [];

        foreach ($found as $id) {
            $ids[html_entity_decode(($id[1] ?? '').($id[2] ?? '').($id[3] ?? ''), ENT_QUOTES | ENT_HTML5, 'UTF-8')] = true;
        }

        return $ids;
    }

    /** @param  array<string, true>  $taken */
    private static function unique(string $base, array $taken): string
    {
        $id = $base;

        for ($n = 2; isset($taken[$id]); $n++) {
            $id = "{$base}-{$n}";
        }

        return $id;
    }

    /** @return list<array{0: int, 1: int}> */
    private static function raw(string $html): array
    {
        $raw = [];

        if (preg_match_all(self::RAW, $html, $found, PREG_OFFSET_CAPTURE | PREG_SET_ORDER) > 0) {
            foreach ($found as $match) {
                $raw[] = [$match[0][1], $match[0][1] + strlen($match[0][0])];
            }
        }

        return $raw;
    }

    /** @param  list<array{0: int, 1: int}>  $raw */
    private static function within(int $at, array $raw): bool
    {
        foreach ($raw as [$from, $to]) {
            if ($at >= $from && $at < $to) {
                return true;
            }
        }

        return false;
    }
}
