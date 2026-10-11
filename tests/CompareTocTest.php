<?php

declare(strict_types=1);

namespace WebxUi\Widgets\Tests;

use Illuminate\Routing\Router;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Blade;
use Illuminate\View\ViewException;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\Test;
use Throwable;
use WebxUi\Widgets\Facades\Widgets;
use WebxUi\Widgets\Prose\Contents;

/**
 * Spec §14, W5.3: before and after — two pictures and a divider that is a range input, the frame
 * in the pictures' shape — and the table of contents, made by the server from the finished page,
 * which gives the headings their ids.
 */
final class CompareTocTest extends TestCase
{
    /**
     * @param  Router  $router
     */
    protected function defineRoutes($router): void
    {
        // A policy: the list beside the text, the headings with and without ids, a duplicate, a
        // heading that is text, one left out — and an id the page has elsewhere.
        $router->get('/policy', static fn (): string => Blade::render(<<<'BLADE'
            <x-layout><x-webx-toc><h2>Who we are</h2><p>…</p><h3>Our address</h3><h2 id="data" class="x">What we keep &amp; why</h2><h3>Cookies</h3><h4>Deeper</h4><h2>Who we are</h2><template><h2>In a template</h2></template><h2 data-webx-toc-skip>Contact</h2></x-webx-toc><p id="who-we-are-2">Taken</p></x-layout>
            BLADE));
        // The whole page: no slot, the list where the tag is, its headings in <main> after it.
        $router->get('/article', static fn (): string => Blade::render('<x-layout><h2>Before main</h2><main><x-webx-toc :depth="2" title="Contents" /><h2>One</h2><h3>One point one</h3><h2>Two</h2></main></x-layout>'));
        $router->get('/elsewhere', static fn (): string => Blade::render('<x-layout><x-webx-toc for="terms" fold="never" /><section id="terms"><div><h2>Terms</h2></div></section><h2>Not of the terms</h2></x-layout>'));
        $router->get('/empty', static fn (): string => Blade::render('<x-layout><x-webx-toc><p>No sections.</p></x-webx-toc></x-layout>'));
        $router->get('/russian', static fn (): string => Blade::render('<x-layout><x-webx-toc><h2>Политика конфиденциальности</h2></x-webx-toc></x-layout>'));
        $router->get('/compare', static fn (): string => Blade::render('<x-layout><x-webx-compare before="/a.webp" after="/b.webp" /></x-layout>'));
    }

    #[Test]
    public function compare_lays_the_after_picture_over_the_before_one_with_a_range_for_a_divider(): void
    {
        $html = Blade::render('<x-webx-compare :before="$before" :after="$after" :start="30" class="b-compare__compare">Two weeks apart</x-webx-compare>', [
            'before' => ['url' => '/media/old.webp', 'alt' => 'The kitchen, 2019', 'width' => 1800, 'height' => 1200],
            'after' => ['url' => '/media/new.webp', 'alt' => 'The kitchen, 2026', 'width' => 1800, 'height' => 1200],
        ]);

        // The shape of the pictures before they load, the divider where it starts.
        $this->assertStringContainsString('<figure style="--webx-compare-ratio: 1800 / 1200; --webx-compare-position: 30%" class="webx-compare b-compare__compare" data-webx-compare>', $html);
        $this->assertStringContainsString('<div class="webx-compare__frame" dir="ltr">', $html);
        $this->assertStringContainsString('<img class="webx-compare__picture" src="/media/old.webp" alt="The kitchen, 2019"  width="1800" height="1200"  loading="lazy" decoding="async" draggable="false">', $html);
        $this->assertStringContainsString('<span class="webx-compare__label webx-compare__label--before">Before</span>', $html);
        $this->assertStringContainsString('<span class="webx-compare__label webx-compare__label--after">After</span>', $html);
        // A slider for a screen reader and the keyboard, with its value as a percentage.
        $this->assertStringContainsString('<input class="webx-compare__range" type="range" min="0" max="100" step="any" value="30" aria-label="Divider between Before and After" aria-valuetext="30%">', $html);
        $this->assertStringContainsString('<span class="webx-compare__handle" aria-hidden="true"></span>', $html);
        $this->assertStringContainsString('<figcaption class="webx-compare__caption">Two weeks apart</figcaption>', $html);
        $this->assertSame(['compare'], Widgets::claimed());

        // No caption, no figcaption; the middle by default.
        $plain = Blade::render('<x-webx-compare before="/a.webp" after="/b.webp" />');
        $this->assertStringNotContainsString('figcaption', $plain);
        $this->assertStringContainsString('--webx-compare-position: 50%', $plain);
    }

    #[Test]
    public function compare_takes_the_shape_of_the_first_picture_with_sizes_or_the_one_it_is_told(): void
    {
        $shape = static fn (string $template, array $data = []): string => preg_match('~--webx-compare-ratio: ([^;]+);~', Blade::render($template, $data), $found) === 1 ? $found[1] : '';

        $this->assertSame('800 / 1000', $shape('<x-webx-compare before="/a.svg" :after="$after" />', ['after' => ['url' => '/b.webp', 'width' => 800, 'height' => 1000]]));
        $this->assertSame('4 / 3', $shape('<x-webx-compare before="/a.svg" after="/b.svg" />'));
        $this->assertSame('16 / 9', $shape('<x-webx-compare before="/a.webp" after="/b.webp" ratio="16:9" />'));
        $this->assertSame('1.5 / 1', $shape('<x-webx-compare before="/a.webp" after="/b.webp" :ratio="1.5" />'));

        // An object of the library, as a product's photo: url() and its sizes.
        $photo = new class
        {
            public int $width = 600;

            public int $height = 600;

            public function url(): string
            {
                return '/media/photo.webp';
            }
        };
        $html = Blade::render('<x-webx-compare :before="$photo" after="/b.webp" before-label="2019" after-label="2026" label="Drag to compare" />', ['photo' => $photo]);
        $this->assertStringContainsString('--webx-compare-ratio: 600 / 600', $html);
        $this->assertStringContainsString('src="/media/photo.webp" alt=""', $html);
        $this->assertStringContainsString('webx-compare__label--before">2019</span>', $html);
        $this->assertStringContainsString('aria-label="Drag to compare"', $html);
    }

    #[Test]
    public function compare_speaks_the_language_of_the_page(): void
    {
        App::setLocale('de');
        $html = Blade::render('<x-webx-compare before="/a.webp" after="/b.webp" start="25" />');

        $this->assertStringContainsString('>Vorher</span>', $html);
        $this->assertStringContainsString('>Nachher</span>', $html);
        $this->assertStringContainsString('aria-label="Trennlinie zwischen „Vorher“ und „Nachher“"', $html);
        $this->assertMatchesRegularExpression('~aria-valuetext="25\s%"~u', $html);

        App::setLocale('tr');
        $this->assertStringContainsString('aria-valuetext="%25"', Blade::render('<x-webx-compare before="/a.webp" after="/b.webp" start="25" />'));
    }

    #[Test]
    public function the_list_beside_a_policy_gives_its_headings_ids_that_work_without_javascript(): void
    {
        $html = (string) $this->get('/policy')->assertOk()->getContent();

        $this->assertStringContainsString('<div class="webx-toc" data-webx-toc >', $html);
        $this->assertStringContainsString('<div class="webx-toc__layout webx-toc__layout--end">', $html);
        $this->assertStringContainsString('<nav class="webx-toc__nav webx-toc__nav--beside" id="webx-toc-1" aria-labelledby="webx-toc-1-title">', $html);
        $this->assertStringContainsString('<p class="webx-toc__title" id="webx-toc-1-title">On this page</p>', $html);
        $this->assertStringContainsString('<button type="button" class="webx-toc__toggle" aria-expanded="false" aria-controls="webx-toc-1-panel">', $html);

        // Ids from the words; the one an editor gave kept, attributes and all; the second
        // "Who we are" past the id the page already has.
        $this->assertStringContainsString('<h2 id="who-we-are">Who we are</h2>', $html);
        $this->assertStringContainsString('<h3 id="our-address">Our address</h3>', $html);
        $this->assertStringContainsString('<h2 id="data" class="x">What we keep &amp; why</h2>', $html);
        $this->assertStringContainsString('<h2 id="who-we-are-3">Who we are</h2>', $html);
        $this->assertStringContainsString('<h4>Deeper</h4>', $html);
        $this->assertStringContainsString('<template><h2>In a template</h2></template>', $html);
        $this->assertStringContainsString('<h2 data-webx-toc-skip>Contact</h2>', $html);

        $list = $this->between($html, '<ol class="webx-toc__list">', '</nav>');
        $this->assertSame(
            ['#who-we-are' => 'Who we are', '#our-address' => 'Our address', '#data' => 'What we keep &amp; why', '#cookies' => 'Cookies', '#who-we-are-3' => 'Who we are'],
            $this->links($list),
        );
        // The h3 under the h2 before them.
        $this->assertSame(2, substr_count($list, '<ol class="webx-toc__list webx-toc__list--sub">'));
        $this->assertStringNotContainsString('<!--webx-toc', $html);
    }

    #[Test]
    public function without_a_slot_the_list_is_of_main_or_of_the_element_it_names(): void
    {
        $article = (string) $this->get('/article')->assertOk()->getContent();

        $this->assertStringContainsString('<div class="webx-toc__layout webx-toc__layout--alone">', $article);
        $this->assertStringContainsString('<nav class="webx-toc__nav" id="webx-toc-1"', $article);
        $this->assertStringContainsString('<p class="webx-toc__title" id="webx-toc-1-title">Contents</p>', $article);
        // Depth 2: the h3 keeps no id of the list's, and the h2 outside <main> is not listed.
        $this->assertSame(['#one' => 'One', '#two' => 'Two'], $this->links($article));
        $this->assertStringContainsString('<h3>One point one</h3>', $article);
        $this->assertStringContainsString('<h2>Before main</h2>', $article);

        $elsewhere = (string) $this->get('/elsewhere')->assertOk()->getContent();
        $this->assertStringContainsString('data-webx-toc  data-fold="never" >', $elsewhere);
        $this->assertSame(['#terms-2' => 'Terms'], $this->links($elsewhere), 'the id "terms" is the section\'s');
        $this->assertStringContainsString('<h2>Not of the terms</h2>', $elsewhere);
    }

    #[Test]
    public function a_list_with_nothing_to_list_is_not_printed(): void
    {
        $html = (string) $this->get('/empty')->assertOk()->getContent();

        $this->assertStringNotContainsString('webx-toc__nav', $html);
        $this->assertStringNotContainsString('<!--webx-toc', $html);
        $this->assertStringContainsString('<div class="webx-toc__content" id="webx-toc-1-content"><p>No sections.</p></div>', $html);
    }

    #[Test]
    public function ids_are_written_in_the_letters_of_an_address(): void
    {
        App::setLocale('ru');

        $html = (string) $this->get('/russian')->assertOk()->getContent();

        $this->assertStringContainsString('<h2 id="politika-konfidentsialnosti">Политика конфиденциальности</h2>', $html);
        $this->assertStringContainsString('<p class="webx-toc__title" id="webx-toc-1-title">На этой странице</p>', $html);
    }

    #[Test]
    public function the_contents_skip_what_is_text_and_reach_the_end_of_an_unclosed_element(): void
    {
        $marker = '<!--webx-toc:'.base64_encode((string) json_encode(['id' => 't', 'from' => 'a', 'depth' => 3, 'title' => 'T', 'beside' => false])).'-->';
        $html = $marker.'<div id="a"><script>"<h2>Script</h2>"</script><!-- <h2>Comment</h2> --><textarea><h2>Typed</h2></textarea><div><h2>Inner</h2></div><h2>  Spaced   <em>out</em> </h2><h2></h2>';
        $lists = [];

        [$filled, $count] = Contents::fill($html, static function (array $list) use (&$lists): string {
            $lists[] = $list;

            return '<nav>list</nav>';
        });

        $this->assertSame(1, $count);
        $this->assertStringStartsWith('<nav>list</nav><div id="a">', $filled);
        $this->assertSame([['id' => 'inner', 'text' => 'Inner', 'children' => []], ['id' => 'spaced-out', 'text' => 'Spaced out', 'children' => []]], $lists[0]['items']);
        $this->assertStringContainsString('<script>"<h2>Script</h2>"</script>', $filled);
        $this->assertStringContainsString('<h2 id="spaced-out">  Spaced   <em>out</em> </h2><h2></h2>', $filled);

        // Nowhere to find its headings: the marker goes, nothing in its place.
        $nowhere = '<!--webx-toc:'.base64_encode((string) json_encode(['id' => 't', 'from' => 'b'])).'-->';
        [$gone, $none] = Contents::fill($nowhere.'<h2>x</h2>', static fn (): string => '<nav>list</nav>');
        $this->assertSame([0, '<h2>x</h2>'], [$none, $gone]);
    }

    #[Test]
    public function the_page_loads_the_files_of_compare_and_toc_where_they_stand(): void
    {
        $this->artisan('webx:theme:sync')->assertSuccessful();

        foreach (['/compare' => 'compare', '/policy' => 'toc'] as $page => $widget) {
            $html = (string) $this->get($page)->assertOk()->getContent();

            $this->assertMatchesRegularExpression("~<link rel=\"stylesheet\" href=\"[^\"]+/{$widget}\.css\">~", $html);
            $this->assertMatchesRegularExpression("~<script type=\"module\" src=\"[^\"]+/{$widget}\.js\"></script>~", $html);
        }
    }

    #[Test]
    public function a_typo_says_what_it_takes(): void
    {
        foreach ([
            '<x-webx-compare after="/b.webp" />' => 'no `before` picture',
            '<x-webx-compare before="/a.webp" :after="[\'url\' => \'\']" />' => 'no `after` picture',
            '<x-webx-compare before="/a.webp" after="/b.webp" start="120" />' => '0 to 100',
            '<x-webx-compare before="/a.webp" after="/b.webp" ratio="wide" />' => 'a ratio is',
            '<x-webx-toc :depth="4" />' => '2 for the h2 only',
            '<x-webx-toc side="left" />' => 'start or end',
            '<x-webx-toc fold="always" />' => 'auto or never',
            '<x-webx-toc for="#terms" />' => 'without #',
        ] as $template => $message) {
            try {
                Blade::render($template);
                $this->fail("{$template} rendered.");
            } catch (Throwable $error) {
                $error = $error instanceof ViewException ? ($error->getPrevious() ?? $error) : $error;
                $this->assertInstanceOf(InvalidArgumentException::class, $error, $template);
                $this->assertStringContainsString($message, $error->getMessage(), $template);
            }
        }
    }

    /** @return array<string, string> The links of a list: address → words. */
    private function links(string $html): array
    {
        preg_match_all('~<a class="webx-toc__link(?: is-current)?" href="([^"]+)">(.*?)</a>~', $html, $found, PREG_SET_ORDER);

        return array_combine(array_column($found, 1), array_column($found, 2));
    }

    private function between(string $html, string $from, string $to): string
    {
        $start = (int) strpos($html, $from);

        return substr($html, $start, (int) strpos($html, $to, $start) - $start);
    }
}
