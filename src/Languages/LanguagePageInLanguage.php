<?php

declare(strict_types=1);

namespace WebxUi\Widgets\Languages;

use Illuminate\Http\Request;
use WebxUi\Localization\Contracts\PageInLanguage;

/**
 * Where the language of the first visit sends somebody (ROUTING §18.1, 10): the switcher's own
 * link of that language, so the two never disagree. A link that leads only to the language's home
 * page (`fallback`) is no page of this one — the visitor came for this product, not for a home
 * page — and neither is the language the page is already in.
 */
final class LanguagePageInLanguage implements PageInLanguage
{
    public function __construct(private readonly LanguageLinks $links) {}

    public function url(Request $request, string $locale): ?string
    {
        foreach ($this->links->for($request) as $link) {
            if ($link->code === $locale) {
                return $link->current || $link->fallback ? null : $link->url;
            }
        }

        return null;
    }
}
