<?php

declare(strict_types=1);

namespace WebxUi\Widgets\Languages;

/**
 * One language of the switcher: where the page is in it and what the language calls itself.
 *
 * `fallback` — the page has no translation there, and the link leads to that language's home
 * page instead (spec §13): a theme may show it differently, a reader is told so.
 */
final readonly class LanguageLink
{
    public function __construct(
        public string $code,
        public string $name,
        public string $url,
        public bool $current = false,
        public bool $fallback = false,
    ) {}

    /** `pt_BR` as `hreflang` and `lang` want it: the same spelling module-seo prints in `<head>`. */
    public function tag(): string
    {
        return str_replace('_', '-', $this->code);
    }
}
