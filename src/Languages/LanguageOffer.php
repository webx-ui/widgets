<?php

declare(strict_types=1);

namespace WebxUi\Widgets\Languages;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Lang;
use WebxUi\Localization\Contracts\PageInLanguage;
use WebxUi\Localization\FirstVisit;

/**
 * The offer of the first visit's language beside the switcher (ROUTING §18.6): "Switch to …?" for
 * somebody who was sent once already and is now on a page of another language. A suggestion,
 * never a redirect; declining it is kept in a cookie the offer's script writes.
 *
 * Who gets it is `FirstVisit::offer()` — the cookie of the first visit, no refusal, no robot, a
 * page of another language. Where it leads is `PageInLanguage`, the very address the redirect
 * would have used: a page that has only a home page in that language gets no offer, as it got no
 * redirect.
 *
 * The words are the target language's own — the question is asked in the language offered, as a
 * reader of it looks for it. Each of the package's languages names itself in its line; a theme
 * says it differently in `theme::theme.language_offer.<key>`, and a language the package has no
 * words for is asked in English with its own name.
 */
final readonly class LanguageOffer
{
    public const array WORDS = ['question', 'accept', 'stay', 'close'];

    /**
     * @param  array{question: string, accept: string, stay: string, close: string}  $words
     */
    public function __construct(
        public string $code,
        public string $url,
        public string $cookie,
        public int $lifetime,
        public array $words,
    ) {}

    /**
     * The offer on this page, or `null`.
     *
     * @param  list<LanguageLink>  $links  the switcher's, for the language's own name
     */
    public static function for(Request $request, array $links): ?self
    {
        if (! class_exists(FirstVisit::class) || ! interface_exists(PageInLanguage::class)) {
            return null;
        }

        $visit = app(FirstVisit::class);
        $target = $visit->offer($request);

        if ($target === null) {
            return null;
        }

        $url = app(PageInLanguage::class)->url($request, $target);

        if ($url === null || $url === '') {
            return null;
        }

        $name = $target;

        foreach ($links as $link) {
            $name = $link->code === $target ? $link->name : $name;
        }

        $words = [];

        foreach (self::WORDS as $key) {
            $words[$key] = self::word($key, $target, $name);
        }

        /** @var array{question: string, accept: string, stay: string, close: string} $words */
        return new self(
            code: $target,
            url: $url,
            cookie: $visit->offerCookieName(),
            lifetime: max(1, (int) config('webx-localization.first_visit.lifetime', 60 * 24 * 365)) * 60,
            words: $words,
        );
    }

    /** `pt_BR` as `lang` wants it, the spelling of `LanguageLink::tag()`. */
    public function tag(): string
    {
        return str_replace('_', '-', $this->code);
    }

    private static function word(string $key, string $locale, string $name): string
    {
        foreach (['theme::theme.language_offer.'.$key, 'webx-widgets::widgets.language.offer.'.$key] as $line) {
            if (Lang::hasForLocale($line, $locale)) {
                return (string) __($line, ['language' => $name], $locale);
            }
        }

        return (string) __('webx-widgets::widgets.language.offer.'.$key, ['language' => $name], 'en');
    }
}
