<?php

declare(strict_types=1);

namespace WebxUi\Widgets\Audit;

use Dom\Element;
use Dom\HTMLDocument;
use WebxUi\Audit\Contracts\AuditPageReader;
use WebxUi\Audit\Crawl\PageParser;

/**
 * What the widgets' checks (spec §15.2) need of a crawled page, read while the audit's parser
 * has it — the page's HTML is not kept (audit spec §12):
 *
 *     loads              a third party the page asks for at once: an iframe with its `src`, a
 *                        script that runs (by `src` or by a known address in its text)
 *     waits              what waits for consent, by category — `data-webx-consent` on a script
 *                        of `text/plain`, an iframe with `data-src`, a template, a video or a map
 *     lightbox_unsized   `a[data-webx-lightbox]` without `data-width` and `data-height`
 *     slider_unpaused    a slider that moves by itself with no pause button in it
 *     video_unpaused     a background video with no pause button in it
 *     counter_empty      a counter whose markup does not hold its number
 *     compare_rangeless  before and after without its range input: no keyboard, no screen reader
 *     toc_dangling       a link of a table of contents to a section the page does not have
 *     load_more_linkless "Show more" with a next page and no link to it: a button only
 *     zoom_linkless      a zoom that is no link and holds none: no keyboard, no screen reader
 *     contact_both       the quick-contact button and the bottom bar on one page
 *
 * Only what is there: a page with none of it stores nothing.
 */
final class WidgetsPageReader implements AuditPageReader
{
    /** Third parties kept of one page — a finding each, and a page with more has a bigger problem. */
    private const LOADS = 20;

    private const EXCERPTS = 5;

    public function id(): string
    {
        return 'widgets';
    }

    public function read(HTMLDocument $document, string $url): array
    {
        $facts = array_filter([
            'loads' => $this->loads($document),
            'waits' => $this->waits($document),
            'lightbox_unsized' => $this->unsized($document),
            'slider_unpaused' => $this->unpaused($document),
            'video_unpaused' => $this->stillless($document),
            'counter_empty' => $this->numberless($document),
            'compare_rangeless' => $this->rangeless($document),
            'toc_dangling' => $this->dangling($document),
            'load_more_linkless' => $this->linkless($document, $url),
            'zoom_linkless' => $this->unlinked($document),
        ]);

        if ($document->querySelector('.webx-contact-button') !== null && $document->querySelector('.webx-contact-bar') !== null) {
            $facts['contact_both'] = true;
        }

        return $facts;
    }

    /**
     * @return list<array{kind: string, url: string, category: string, markup: string}>
     */
    private function loads(HTMLDocument $document): array
    {
        $loads = [];

        foreach ($document->querySelectorAll('iframe[src], script') as $element) {
            $url = $this->asksFor($element);
            $category = $url === null ? null : ThirdParty::category($url);

            if ($url !== null && $category !== null) {
                $loads[] = [
                    'kind' => strtolower($element->localName),
                    'url' => mb_substr($url, 0, 500),
                    'category' => $category,
                    'markup' => PageParser::quote($document, $element),
                ];
            }

            if (count($loads) === self::LOADS) {
                break;
            }
        }

        return $loads;
    }

    /** The address an iframe or a running script asks for as the page loads; null for one that waits. */
    private function asksFor(Element $element): ?string
    {
        if (strtolower($element->localName) === 'iframe') {
            $src = trim((string) $element->getAttribute('src'));

            return $src === '' || str_starts_with(strtolower($src), 'about:') ? null : $src;
        }

        if (! self::runs($element)) {
            return null;
        }

        $src = trim((string) $element->getAttribute('src'));

        return $src !== '' ? $src : ThirdParty::inText((string) $element->textContent);
    }

    /** A classic script or a module — not `text/plain` waiting for consent, not JSON. */
    private static function runs(Element $script): bool
    {
        $type = strtolower(trim((string) $script->getAttribute('type')));

        return $type === '' || $type === 'module' || str_contains($type, 'javascript') || str_contains($type, 'ecmascript');
    }

    /** @return array<string, int> */
    private function waits(HTMLDocument $document): array
    {
        $waits = [];

        foreach ($document->querySelectorAll('[data-webx-consent]') as $element) {
            $category = trim((string) $element->getAttribute('data-webx-consent'));
            $name = strtolower($element->localName);

            // Marked, and asking anyway: that one is in `loads`, not here.
            $asks = ($name === 'iframe' && $this->asksFor($element) !== null) || ($name === 'script' && self::runs($element));

            if ($category !== '' && $category !== 'necessary' && ! $asks) {
                $waits[$category] = ($waits[$category] ?? 0) + 1;
            }
        }

        return $waits;
    }

    /** @return array{count: int, markup: list<string>}|array{} */
    private function unsized(HTMLDocument $document): array
    {
        $found = ['count' => 0, 'markup' => []];

        foreach ($document->querySelectorAll('a[data-webx-lightbox]') as $link) {
            if (self::size($link, 'data-width') && self::size($link, 'data-height')) {
                continue;
            }

            $found['count']++;

            if (count($found['markup']) < self::EXCERPTS) {
                $found['markup'][] = PageParser::quote($document, $link);
            }
        }

        return $found['count'] === 0 ? [] : $found;
    }

    private static function size(Element $element, string $attribute): bool
    {
        $value = trim((string) $element->getAttribute($attribute));

        return ctype_digit($value) && (int) $value > 0;
    }

    /** @return array{count: int, markup: list<string>}|array{} */
    private function unpaused(HTMLDocument $document): array
    {
        $found = ['count' => 0, 'markup' => []];

        foreach ($document->querySelectorAll('[data-webx-slider]') as $slider) {
            $config = json_decode((string) $slider->getAttribute('data-webx-slider'), true);
            $moves = is_array($config) && ((int) ($config['autoplay'] ?? 0) > 0 || ($config['continuous'] ?? false) === true);

            if (! $moves || $slider->querySelectorAll('.webx-slider__slide')->length < 2 || $slider->querySelector('.webx-slider__pause') !== null) {
                continue;
            }

            $found['count']++;

            if (count($found['markup']) < self::EXCERPTS) {
                $found['markup'][] = self::opening($slider);
            }
        }

        return $found['count'] === 0 ? [] : $found;
    }

    /**
     * Background videos with no pause button (WCAG 2.2.2).
     *
     * @return array{count: int, markup: list<string>}|array{}
     */
    private function stillless(HTMLDocument $document): array
    {
        $found = ['count' => 0, 'markup' => []];

        foreach ($document->querySelectorAll('[data-webx-video-background]') as $video) {
            if ($video->querySelector('.webx-video__pause') !== null) {
                continue;
            }

            $found['count']++;

            if (count($found['markup']) < self::EXCERPTS) {
                $found['markup'][] = self::opening($video);
            }
        }

        return $found['count'] === 0 ? [] : $found;
    }

    /**
     * Counters whose markup does not hold the number they count to: the digits of the number
     * printed against those of the value in `data-webx-counter`, so `3,000`, `3 000` and `3.000`
     * are all 3000 and the language's way of writing it does not matter.
     *
     * @return array{count: int, markup: list<string>}|array{}
     */
    private function numberless(HTMLDocument $document): array
    {
        $found = ['count' => 0, 'markup' => []];

        foreach ($document->querySelectorAll('[data-webx-counter]') as $counter) {
            $config = json_decode((string) $counter->getAttribute('data-webx-counter'), true);

            if (! is_array($config) || ! is_numeric($config['value'] ?? null)) {
                continue;
            }

            $places = max(0, min(6, (int) ($config['decimals'] ?? 0)));
            $expected = ltrim((string) preg_replace('/\D/', '', number_format(abs((float) $config['value']), $places, '.', '')), '0');
            $printed = $counter->querySelector('.webx-counter__number')->textContent ?? $counter->textContent;
            $digits = ltrim((string) preg_replace('/\D/', '', (string) $printed), '0');

            if ($digits === $expected) {
                continue;
            }

            $found['count']++;

            if (count($found['markup']) < self::EXCERPTS) {
                $found['markup'][] = PageParser::quote($document, $counter);
            }
        }

        return $found['count'] === 0 ? [] : $found;
    }

    /**
     * Before and after with no range input in it: the divider then moves for a mouse and a finger
     * only — no keyboard, nothing a screen reader can name.
     *
     * @return array{count: int, markup: list<string>}|array{}
     */
    private function rangeless(HTMLDocument $document): array
    {
        $found = ['count' => 0, 'markup' => []];

        foreach ($document->querySelectorAll('[data-webx-compare]') as $compare) {
            if ($compare->querySelector('input[type="range"]') !== null) {
                continue;
            }

            $found['count']++;

            if (count($found['markup']) < self::EXCERPTS) {
                $found['markup'][] = self::opening($compare);
            }
        }

        return $found['count'] === 0 ? [] : $found;
    }

    /**
     * Zooms a keyboard cannot reach: `data-webx-zoom` on an element that is no link to the
     * picture and holds none — the lightbox is their way in (§14).
     *
     * @return array{count: int, markup: list<string>}|array{}
     */
    private function unlinked(HTMLDocument $document): array
    {
        $found = ['count' => 0, 'markup' => []];

        foreach ($document->querySelectorAll('[data-webx-zoom]') as $zoom) {
            if ($zoom->matches('a[href]') || $zoom->querySelector('a[href]') !== null) {
                continue;
            }

            $found['count']++;

            if (count($found['markup']) < self::EXCERPTS) {
                $found['markup'][] = self::opening($zoom);
            }
        }

        return $found['count'] === 0 ? [] : $found;
    }

    /**
     * Links of a table of contents to a section the page does not have: the server gives the
     * headings their ids, so a link to nowhere is a list written or overridden by hand.
     *
     * @return array{count: int, markup: list<string>}|array{}
     */
    private function dangling(HTMLDocument $document): array
    {
        $found = ['count' => 0, 'markup' => []];

        foreach ($document->querySelectorAll('[data-webx-toc] a[href^="#"]') as $link) {
            $id = rawurldecode(substr((string) $link->getAttribute('href'), 1));

            if ($id !== '' && $document->getElementById($id) !== null) {
                continue;
            }

            $found['count']++;

            if (count($found['markup']) < self::EXCERPTS) {
                $found['markup'][] = PageParser::quote($document, $link);
            }
        }

        return $found['count'] === 0 ? [] : $found;
    }

    /**
     * "Show more" with a next page and no link to it inside: the items past this page are then
     * behind a button only — no search engine presses it. The link is compared as an address, so
     * `/news?page=2` and `https://site/news?page=2` are one.
     *
     * @return array{count: int, markup: list<string>}|array{}
     */
    private function linkless(HTMLDocument $document, string $url): array
    {
        $found = ['count' => 0, 'markup' => []];
        $address = static fn (string $href): string => self::address($href, $url);

        foreach ($document->querySelectorAll('[data-webx-load-more][data-next]') as $list) {
            $next = $address((string) $list->getAttribute('data-next'));

            foreach ($list->querySelectorAll('a[href]') as $link) {
                if ($address((string) $link->getAttribute('href')) === $next) {
                    continue 2;
                }
            }

            $found['count']++;

            if (count($found['markup']) < self::EXCERPTS) {
                $found['markup'][] = self::opening($list);
            }
        }

        return $found['count'] === 0 ? [] : $found;
    }

    /** A link as the path and query it leads to on this site; another site's stays whole. */
    private static function address(string $href, string $base): string
    {
        $href = html_entity_decode(trim($href));
        $parts = parse_url($href);
        $here = parse_url($base);

        if ($parts === false || (isset($parts['host']) && strcasecmp((string) $parts['host'], (string) ($here['host'] ?? '')) !== 0)) {
            return $href;
        }

        $path = $parts['path'] ?? '';

        if ($path === '') {
            $path = (string) ($here['path'] ?? '/');
        } elseif (! str_starts_with($path, '/')) {
            $path = rtrim(dirname((string) ($here['path'] ?? '/').'x'), '/').'/'.$path;
        }

        return $path.(isset($parts['query']) ? '?'.$parts['query'] : '');
    }

    /** The opening tag alone: a slider quoted whole is its first slide, not the place to look. */
    private static function opening(Element $element): string
    {
        $tag = '<'.strtolower($element->localName);

        foreach ($element->attributes as $attribute) {
            if ($attribute->name !== 'data-webx-slider') {
                $tag .= ' '.$attribute->name.'="'.htmlspecialchars($attribute->value, ENT_QUOTES).'"';
            }
        }

        return mb_substr($tag.'>', 0, 200);
    }
}
