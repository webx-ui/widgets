<?php

declare(strict_types=1);

namespace WebxUi\Widgets\View\Components;

use Illuminate\Contracts\View\View;
use Illuminate\View\Component;
use InvalidArgumentException;
use WebxUi\Widgets\Facades\Widgets;
use WebxUi\Widgets\Languages\LanguageLink;
use WebxUi\Widgets\Languages\LanguageLinks;

/**
 * `<x-webx-language-switcher />` — the same page in the site's other languages (spec §13).
 *
 *     <x-webx-language-switcher />                  the current language, the others in a dropdown
 *     <x-webx-language-switcher layout="list" />    every language in a row
 *     <x-webx-language-switcher codes />            "EN" beside "English"
 *
 * Each language is named in itself; a page with no translation leads to that language's home
 * page, marked `is-fallback`. On a site with one language it prints nothing. `:languages` hands
 * it others (`LanguageLink` objects).
 */
final class LanguageSwitcher extends Component
{
    public const array LAYOUTS = ['dropdown', 'list'];

    /** @var list<LanguageLink> */
    public array $links;

    /**
     * @param  list<LanguageLink>|null  $languages
     */
    public function __construct(
        public string $layout = 'dropdown',
        public bool $codes = false,
        public string $placement = 'bottom-end',
        ?array $languages = null,
        public ?string $label = null,
        public ?string $fallbackLabel = null,
    ) {
        if (! in_array($layout, self::LAYOUTS, true)) {
            throw new InvalidArgumentException("<x-webx-language-switcher layout=\"{$layout}\">: dropdown or list.");
        }

        $this->links = $languages ?? LanguageLinks::current();
    }

    public function shouldRender(): bool
    {
        return count($this->links) > 1;
    }

    public function render(): View
    {
        Widgets::need('language-switcher');

        $current = null;

        foreach ($this->links as $link) {
            $current ??= $link->current ? $link : null;
        }

        return view('webx-widgets::components.language-switcher', [
            'current' => $current ?? $this->links[0],
            'labelText' => $this->label ?? __('webx-widgets::widgets.language.label'),
            'fallbackText' => $this->fallbackLabel ?? __('webx-widgets::widgets.language.fallback'),
        ]);
    }
}
