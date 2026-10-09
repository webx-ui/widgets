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
