<?php

declare(strict_types=1);

namespace WebxUi\Widgets\Prose;

/**
 * Tables in prose (spec §14): each one gets a scroller of its own, so a table wider than the
 * column scrolls inside itself — and its caption stays where it is while it does.
 *
 * A table of prose is one an editor wrote: the rich text field stores `<table>` with no attribute
 * at all (`module-admin`'s sanitiser drops them), whichever module prints it — a block, a page,
 * an article, an answer of the FAQ. A table a template wrote has a class of its own and a layout
 * of its own, and is left as it is. So the rule is the plain one: a `<table>` without a class,
 * not inside another table, outside `<script>`, `<style>`, `<template>`, `<textarea>` and comments.
 *
 *     <div class="webx-table" data-webx-table>
 *         <div class="webx-table__caption" id="webx-table-1">…the caption…</div>
 *         <div class="webx-table__frame">
 *             <div class="webx-table__scroller" tabindex="0" role="region" aria-labelledby="webx-table-1">
 *                 <table class="webx-table__table" aria-labelledby="webx-table-1">…</table>
 *
 * The caption leaves the table — inside the scroller it would scroll with it — and still names
 * it, and the scroller, by `aria-labelledby`. Without a caption the scroller is called "Table".
 * The scroller can be focused, so a keyboard scrolls it; the script of `table` takes that off a
 * table that fits and draws the shadows at the edge there is more to see behind.
 */
final class Tables
{
    /** What the browser reads as text, not markup: a `<table>` written there is not a table. */
    private const string RAW = '~<!--.*?-->|<(script|style|template|textarea)\b[^>]*>.*?</\1\s*>~is';

    private const string TAG = '~<(/?)table\b([^>]*)>~i';

    private const string CAPTION = '~^\s*<caption\b[^>]*>(.*?)</caption\s*>~is';

    /**
     * The page with every table of prose in its scroller, and how many there were.
     *
     * @param  string  $label  The scroller's name when the table has no caption.
     * @return array{0: string, 1: int}
     */
    public static function wrap(string $html, string $label): array
    {
        if (stripos($html, '<table') === false) {
            return [$html, 0];
        }

        $out = '';
        $done = 0;
        $count = 0;

        foreach (self::find($html) as [$start, $inside, $attributes, $close, $end]) {
            $id = 'webx-table-'.(++$count);
            $body = substr($html, $inside, $close - $inside);
            $caption = '';

            // The caption is the table's first child, if it has one.
            if (preg_match(self::CAPTION, $body, $match) === 1) {
                $caption = '<div class="webx-table__caption" id="'.$id.'">'.$match[1].'</div>';
                $body = substr($body, strlen($match[0]));
            }

            $out .= substr($html, $done, $start - $done)
                .'<div class="webx-table" data-webx-table style="--webx-table-columns: '.self::columns($body).'">'.$caption
                .'<div class="webx-table__frame"><div class="webx-table__scroller" tabindex="0" role="region" '
                .($caption !== '' ? 'aria-labelledby="'.$id.'"' : 'aria-label="'.e($label).'"').'>'
                .'<table class="webx-table__table"'.($caption !== '' ? ' aria-labelledby="'.$id.'"' : '').$attributes.'>'
                .$body.substr($html, $close, $end - $close)
                .'</div></div></div>';

            $done = $end;
        }

        return [$out.substr($html, $done), $count];
    }

    /**
     * How many columns the first row spans. The stylesheet keeps each at least
     * `--webx-table-column-min` wide: squeezed into a phone, an automatic table would rather wrap
     * every cell a word a line than scroll.
     */
    private static function columns(string $body): int
    {
        if (preg_match('~<tr\b[^>]*>(.*?)</tr\s*>~is', $body, $row) !== 1) {
            return 1;
        }

        preg_match_all('~<t[hd]\b([^>]*)>~i', $row[1], $cells);
        $count = 0;

        foreach ($cells[1] as $attributes) {
            $count += preg_match('~\bcolspan\s*=\s*["\']?(\d+)~i', $attributes, $span) === 1 ? max(1, (int) $span[1]) : 1;
        }

        return max(1, min($count, 50));
    }

    /**
     * The outermost tables without a class, in the order of the page: where each opens, where its
     * opening tag ends, its attributes, where its `</table>` starts and where it ends.
     *
     * @return list<array{0: int, 1: int, 2: string, 3: int, 4: int}>
     */
    private static function find(string $html): array
    {
        $raw = [];

        if (preg_match_all(self::RAW, $html, $found, PREG_OFFSET_CAPTURE | PREG_SET_ORDER) > 0) {
            foreach ($found as $match) {
                $raw[] = [$match[0][1], $match[0][1] + strlen($match[0][0])];
            }
        }

        preg_match_all(self::TAG, $html, $tags, PREG_OFFSET_CAPTURE | PREG_SET_ORDER);

        $tables = [];
        $depth = 0;
        $open = null;

        foreach ($tags as $tag) {
            $at = $tag[0][1];

            foreach ($raw as [$from, $to]) {
                if ($at >= $from && $at < $to) {
                    continue 2;
                }
            }

            if ($tag[1][0] === '') {
                if ($depth++ === 0 && preg_match('/\sclass\s*=/i', $tag[2][0]) !== 1) {
                    $open = [$at, $at + strlen($tag[0][0]), $tag[2][0]];
                }

                continue;
            }

            if ($depth > 0 && --$depth === 0 && $open !== null) {
                $tables[] = [...$open, $at, $at + strlen($tag[0][0])];
                $open = null;
            }
        }

        return $tables;
    }
}
