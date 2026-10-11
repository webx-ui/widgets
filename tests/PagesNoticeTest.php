<?php

declare(strict_types=1);

namespace WebxUi\Widgets\Tests;

use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Pagination\Paginator;
use Illuminate\Routing\Router;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Blade;
use Illuminate\View\ViewException;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\Test;
use Throwable;
use WebxUi\Widgets\Facades\Widgets;
use WebxUi\Widgets\View\Components\NoticeBar;

/**
 * Spec §14, W5.4: the links of the pages, "Show more" over them — the list and the links printed
 * by the server, what the script reads on the next page — and the announcement bar, remembered
 * closed by the version of its words, hidden by the head before the body is painted.
 */
final class PagesNoticeTest extends TestCase
{
    /**
     * @param  Router  $router
     */
    protected function defineRoutes($router): void
    {
        $router->get('/notice', static fn (): string => Blade::render('<x-layout><x-webx-notice-bar>Open on <a href="/hours">Saturdays</a></x-webx-notice-bar><p>Page</p></x-layout>'));
        $router->get('/quiet', static fn (): string => Blade::render('<x-layout><x-webx-notice-bar /><p>Page</p></x-layout>'));
        $router->get('/news', static fn (): string => Blade::render(
            '<x-layout><x-webx-load-more :paginator="$news">@foreach ($news as $item)<article>{{ $item }}</article>@endforeach</x-webx-load-more></x-layout>',
            ['news' => self::news(2)],
        ));
    }

    #[Test]
    public function the_pagination_prints_the_numbers_around_this_page_and_the_first_and_the_last(): void
    {
        $html = Blade::render('<x-webx-pagination :paginator="$p" />', ['p' => self::news(5, total: 120)]);

        $this->assertStringContainsString('<nav class="webx-pagination" aria-label="Pages">', $html);
        $this->assertStringContainsString('<a class="webx-pagination__link webx-pagination__link--prev" rel="prev" href="/news?page=4">Previous</a>', $html);
        $this->assertStringContainsString('<span class="webx-pagination__link is-current" aria-current="page" data-page="5">5</span>', $html);
        $this->assertStringContainsString('<a class="webx-pagination__link webx-pagination__link--next" rel="next" href="/news?page=6">Next</a>', $html);
        $this->assertStringContainsString('<a class="webx-pagination__link" href="/news" data-page="1">1</a>', $html, 'not ?page=1: one address for the first page');
        // 1, then 2 rather than a gap of one page, 3–7 around 5, a gap, the last.
        $this->assertSame(['1', '2', '3', '4', '5', '6', '7', '…', '10'], self::numbers($html));
        $this->assertSame(['pagination'], Widgets::claimed());

        $this->assertSame(['1', '2', '3', '…', '10'], self::numbers(Blade::render('<x-webx-pagination :paginator="$p" />', ['p' => self::news(1, total: 120)])));
        $this->assertSame(['1', '…', '9', '10'], self::numbers(Blade::render('<x-webx-pagination :paginator="$p" :around="1" />', ['p' => self::news(10, total: 120)])));

        // ->simplePaginate() knows no last page: previous and next only.
        $simple = Blade::render('<x-webx-pagination :paginator="$p" />', ['p' => new Paginator(range(1, 13), 12, 2, ['path' => '/news'])]);
        $this->assertSame([], self::numbers($simple));
        $this->assertStringContainsString('rel="prev" href="/news"', $simple, 'the first page is the address of the list');
        $this->assertStringContainsString('rel="next" href="/news?page=3"', $simple);

        // One page: nothing to lead to.
        $this->assertSame('', trim(Blade::render('<x-webx-pagination :paginator="$p" />', ['p' => self::news(1, total: 5)])));
    }

    #[Test]
    public function show_more_marks_the_list_the_next_page_and_covers_the_links_it_stands_for(): void
    {
        $html = Blade::render('<x-webx-load-more :paginator="$p" class="news">@foreach ($p as $item)<article>{{ $item }}</article>@endforeach</x-webx-load-more>', ['p' => self::news(2)]);

        $this->assertStringContainsString('<div data-webx-load-more="page" data-pages="covered" data-page="2" data-last="5" data-next="/news?page=3" data-words="', $html);
        $this->assertStringContainsString('}" class="webx-load-more news">', $html);
        $this->assertStringContainsString('<div class="webx-load-more__list" data-webx-load-more-list><article>13</article><article>14</article>', $html);
        // The button is the script's; the links are what a page without it and a search engine get.
        $this->assertStringContainsString('<button type="button" class="webx-load-more__button" hidden>Show more</button>', $html);
        $this->assertStringContainsString('<p class="webx-load-more__status" role="status"></p>', $html);
        $this->assertStringContainsString('<div class="webx-load-more__pages"><nav class="webx-pagination" aria-label="Pages">', $html);
        $this->assertStringContainsString('rel="next" href="/news?page=3"', $html);
        $this->assertSame(['load-more', 'pagination'], Widgets::claimed());

        preg_match('/data-words="([^"]+)"/', $html, $words);
        $this->assertSame([
            'loading' => 'Loading…',
            'loaded' => 'Page :page of :last loaded.',
            'end' => 'That is everything.',
            'failed' => 'The next page could not be loaded. Try again, or use the links to the pages.',
        ], json_decode(html_entity_decode($words[1]), true));

        // The last page: nothing to fetch, no button, the links lead back.
        $last = Blade::render('<x-webx-load-more :paginator="$p"><article>57</article></x-webx-load-more>', ['p' => self::news(5)]);
        $this->assertStringNotContainsString('data-next', $last);
        $this->assertStringNotContainsString('webx-load-more__button', $last);
        $this->assertStringContainsString('rel="prev"', $last);
    }

    #[Test]
    public function show_more_takes_a_list_of_the_slot_its_own_links_and_another_page_name(): void
    {
        $reviews = (new LengthAwarePaginator(range(1, 3), 9, 3, 1, ['path' => '/reviews']))->setPageName('reviews');

        $html = Blade::render(<<<'BLADE'
            <x-webx-load-more :paginator="$p" pages="shown" label="More reviews">
                <ul class="cards" data-webx-load-more-list>@foreach ($p as $item)<li>{{ $item }}</li>@endforeach</ul>
                <x-slot:links><nav class="theirs"><a rel="next" href="{{ $p->nextPageUrl() }}">On</a></nav></x-slot:links>
            </x-webx-load-more>
            BLADE, ['p' => $reviews]);

        $this->assertStringContainsString('<div data-webx-load-more="reviews" data-pages="shown" data-page="1" data-last="3" data-next="/reviews?reviews=2"', $html);
        $this->assertStringContainsString('class="webx-load-more webx-load-more--shown">', $html);
        // The slot's own list, not wrapped again.
        $this->assertStringNotContainsString('webx-load-more__list', $html);
        $this->assertStringContainsString('<ul class="cards" data-webx-load-more-list><li>1</li>', $html);
        $this->assertStringContainsString('<div class="webx-load-more__pages"><nav class="theirs">', $html);
        $this->assertStringNotContainsString('webx-pagination', $html);
        $this->assertStringContainsString('>More reviews</button>', $html);

        // A pagination that knows no last page says which page came, not "of".
        $simple = Blade::render('<x-webx-load-more :paginator="$p"><i>1</i></x-webx-load-more>', ['p' => new Paginator(range(1, 13), 12, 1, ['path' => '/news'])]);
        $this->assertStringNotContainsString('data-last', $simple);
        $this->assertStringContainsString('Page :page loaded.', html_entity_decode($simple));
    }

    #[Test]
    public function show_more_speaks_the_language_of_the_page(): void
    {
        App::setLocale('ru');

        $html = Blade::render('<x-webx-load-more :paginator="$p"><i>1</i></x-webx-load-more>', ['p' => self::news(1)]);

        $this->assertStringContainsString('>Показать ещё</button>', $html);
        $this->assertStringContainsString('aria-label="Страницы"', $html);
        $this->assertStringContainsString('>Вперёд</a>', $html);
    }

    #[Test]
    public function the_notice_bar_is_a_named_region_with_a_close_button_the_script_shows(): void
    {
        $html = Blade::render('<x-webx-notice-bar class="site-notice">Free delivery until <b>Sunday</b></x-webx-notice-bar>');
        $stamp = NoticeBar::stamp('Free delivery until <b>Sunday</b>');

        $this->assertMatchesRegularExpression('/^[0-9a-f]{12}$/', $stamp);
        $this->assertStringContainsString('<section class="webx-notice-bar site-notice" aria-label="Announcement" data-webx-notice-bar="'.$stamp.'">', $html);
        $this->assertStringContainsString('<div class="webx-notice-bar__content">', $html);
        $this->assertStringContainsString('Free delivery until <b>Sunday</b>', $html);
        $this->assertStringContainsString('<button type="button" class="webx-notice-bar__close" aria-label="Close the announcement" hidden>', $html);
        $this->assertSame(['notice-bar'], Widgets::claimed());

        // The same words indented anew are the same version; other words another one.
        $this->assertSame($stamp, NoticeBar::stamp("  Free delivery\n    until <b>Sunday</b> "));
        $this->assertNotSame($stamp, NoticeBar::stamp('Free delivery until <b>Monday</b>'));

        // A version of its own, no close button, a label of its own.
        $own = Blade::render('<x-webx-notice-bar version="sale-2026" :closable="false" label="Sale">Sale</x-webx-notice-bar>');
        $this->assertStringContainsString('aria-label="Sale" data-webx-notice-bar="sale-2026"', $own);
        $this->assertStringNotContainsString('webx-notice-bar__close', $own);
    }

    #[Test]
    public function a_notice_bar_with_nothing_to_say_is_not_there_at_all(): void
    {
        $this->assertSame('', trim(Blade::render('<x-webx-notice-bar />')));
        $this->assertSame('', trim(Blade::render('<x-webx-notice-bar :place="null">   </x-webx-notice-bar>')));
        $this->assertSame([], Widgets::claimed());

        App::setLocale('de');
        $this->assertStringContainsString('aria-label="Hinweis"', Blade::render('<x-webx-notice-bar>Neu</x-webx-notice-bar>'));
        $this->assertStringContainsString('aria-label="Hinweis schließen"', Blade::render('<x-webx-notice-bar>Neu</x-webx-notice-bar>'));
    }

    #[Test]
    public function the_head_hides_a_closed_notice_bar_before_the_body_is_painted(): void
    {
        $this->artisan('webx:theme:sync')->assertSuccessful();

        $html = (string) $this->get('/notice')->assertOk()->getContent();
        $stamp = NoticeBar::stamp('Open on <a href="/hours">Saturdays</a>');
        $head = substr($html, 0, (int) strpos($html, '</head>'));

        $this->assertStringContainsString('<script>try{var c=JSON.parse(localStorage.getItem("webx-notice-bar")||"[]"),h=["'.$stamp.'"]', $head);
        $this->assertStringContainsString('{display:none}', $head);
        $this->assertMatchesRegularExpression('~<link rel="stylesheet" href="[^"]+/notice-bar\.css">~', $head);
        $this->assertMatchesRegularExpression('~<script type="module" src="[^"]+/notice-bar\.js"></script>~', $html);

        // No bar, no lines in the head, no file.
        $quiet = (string) $this->get('/quiet')->assertOk()->getContent();
        $this->assertStringNotContainsString('webx-notice-bar', $quiet);

        // Only a version the bar could have made reaches the script.
        $this->assertSame('', NoticeBar::head(['"><script>']));
    }

    #[Test]
    public function the_page_loads_the_files_of_show_more_and_the_links(): void
    {
        $this->artisan('webx:theme:sync')->assertSuccessful();

        $html = (string) $this->get('/news')->assertOk()->getContent();

        foreach (['load-more', 'pagination'] as $widget) {
            $this->assertMatchesRegularExpression("~<link rel=\"stylesheet\" href=\"[^\"]+/{$widget}\.css\">~", $html);
        }

        $this->assertMatchesRegularExpression('~<script type="module" src="[^"]+/load-more\.js"></script>~', $html);
        $this->assertDoesNotMatchRegularExpression('~/pagination\.js~', $html);
    }

    #[Test]
    public function a_typo_says_what_it_takes(): void
    {
        foreach ([
            '<x-webx-load-more :paginator="$p" pages="hidden" />' => 'covered or shown',
            '<x-webx-pagination :paginator="$p" :around="9" />' => '0 to 5',
            '<x-webx-notice-bar version="a b">x</x-webx-notice-bar>' => 'letters, digits and dashes',
        ] as $template => $message) {
            try {
                Blade::render($template, ['p' => self::news(1)]);
                $this->fail("{$template} rendered.");
            } catch (Throwable $error) {
                $error = $error instanceof ViewException ? ($error->getPrevious() ?? $error) : $error;
                $this->assertInstanceOf(InvalidArgumentException::class, $error, $template);
                $this->assertStringContainsString($message, $error->getMessage(), $template);
            }
        }
    }

    /** @return LengthAwarePaginator<int, int> 12 a page of `$total`, as `->paginate(12)` on /news. */
    private static function news(int $page, int $total = 57): LengthAwarePaginator
    {
        return new LengthAwarePaginator(range(($page - 1) * 12 + 1, min($total, $page * 12)), $total, 12, $page, ['path' => '/news']);
    }

    /** @return list<string> The numbers of a pagination and its gaps, in order. */
    private static function numbers(string $html): array
    {
        preg_match_all('~<(?:a|span) class="webx-pagination__(?:link(?: is-current)?|gap)"[^>]*>([^<]+)</(?:a|span)>~', $html, $found);

        return array_values(array_filter($found[1], static fn (string $text): bool => $text === '…' || ctype_digit($text)));
    }
}
