<?php

declare(strict_types=1);

namespace WebxUi\Widgets\Tests;

use Illuminate\Routing\Router;
use Illuminate\Support\Facades\Blade;
use Illuminate\View\ViewException;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use Throwable;
use WebxUi\Widgets\Facades\Widgets;

/**
 * W6.5: suggestions while typing — a GET search to the results page that the script makes a
 * combobox; the panel's content is the site's HTML.
 */
final class SuggestTest extends TestCase
{
    /**
     * @param  Router  $router
     */
    protected function defineRoutes($router): void
    {
        $router->get('/with-search', static fn (): string => Blade::render('<x-layout><x-webx-suggest action="/search" source="/search/suggest" /></x-layout>'));
        $router->get('/search', static fn (): string => Blade::render('<x-webx-suggest action="/search" source="/search/suggest" />'));
        $router->get('/without', static fn (): string => Blade::render('<x-layout><p>No search here.</p></x-layout>'));
    }

    #[Test]
    public function without_javascript_it_is_a_get_search_to_the_results_page(): void
    {
        $html = Blade::render('<x-webx-suggest action="/catalog/search" source="/catalog/suggest" class="site-search" />');

        $this->assertStringContainsString('<form method="get" action="/catalog/search" role="search" data-webx-suggest="/catalog/suggest" data-param="q" data-min="2" data-delay="250" data-recent="0" data-words="', $html);
        $this->assertStringContainsString('}" class="webx-suggest site-search">', $html);
        $this->assertStringContainsString('<label class="webx-suggest__label" for="suggest-1">Search</label>', $html);
        $this->assertMatchesRegularExpression('~<input class="webx-suggest__input" type="search" id="suggest-1" name="q" value=""\s+placeholder="Search" enterkeyhint="search" spellcheck="false" autocapitalize="none">~', $html);
        $this->assertMatchesRegularExpression('~<button type="submit" class="webx-suggest__submit">\s*<svg class="webx-icon"[^>]*aria-hidden="true"[^>]*>.*?</svg>\s*<span class="webx-suggest__submit-text">Search</span>\s*</button>~s', $html);
        // The panel waits empty and hidden; the status line is there before anything is said.
        $this->assertStringContainsString('<div class="webx-suggest__panel" id="suggest-1-panel" hidden></div>', $html);
        $this->assertStringContainsString('<p class="webx-suggest__status" role="status"></p>', $html);
        $this->assertSame(['suggest'], Widgets::claimed());
    }

    #[Test]
    public function without_an_action_it_is_a_field_that_stands_inside_another_form(): void
    {
        $html = Blade::render('<x-webx-form action="/order"><x-webx-suggest source="/cities" name="delivery[city]" param="city" label="City" placeholder="Start typing" :show-label="true" /></x-webx-form>');

        // No form inside the form: one <form>, and the field is the outer form's.
        $this->assertSame(1, substr_count($html, '<form'));
        $this->assertMatchesRegularExpression('~<div data-webx-suggest="/cities" data-param="city" data-min="2" data-delay="250" data-recent="0" data-words="[^"]+" class="webx-suggest webx-suggest--field">~', $html);
        $this->assertMatchesRegularExpression('~<input class="webx-suggest__input webx-suggest__input--alone" type="text" id="suggest-1" name="delivery\[city\]" value=""\s+placeholder="Start typing"\s+spellcheck="false"~', $html);
        $this->assertStringNotContainsString('webx-suggest__submit', $html);
        $this->assertStringNotContainsString('role="search"', $html);
        $this->assertStringContainsString('<label class="webx-suggest__label webx-suggest__label--shown" for="suggest-1">City</label>', $html);
    }

    #[Test]
    public function a_field_sent_back_without_javascript_keeps_what_was_typed(): void
    {
        $this->app['session.store']->flashInput(['delivery' => ['city' => 'Kraków']]);
        $this->app['request']->setLaravelSession($this->app['session.store']);

        $html = Blade::render('<x-webx-suggest source="/cities" name="delivery[city]" value="Warszawa" />');

        $this->assertStringContainsString('name="delivery[city]" value="Kraków"', $html);
        $this->assertStringContainsString('value="Warszawa"', Blade::render('<x-webx-suggest source="/cities" name="street" value="Warszawa" />'));
    }

    #[Test]
    public function the_combobox_roles_are_the_scripts_to_give(): void
    {
        $html = Blade::render('<x-webx-suggest action="/s" source="/s/suggest" />');

        // Without the script the field is a plain search: no popup to announce, no list to control.
        foreach (['role="combobox"', 'aria-expanded', 'aria-controls', 'role="listbox"', 'autocomplete="off"', '<script'] as $absent) {
            $this->assertStringNotContainsString($absent, $html);
        }
    }

    #[Test]
    public function the_results_page_fills_the_box_with_what_was_searched(): void
    {
        $html = (string) $this->get('/search?q=%22red%22+%3Cshoes%3E')->assertOk()->getContent();

        $this->assertStringContainsString('value="&quot;red&quot; &lt;shoes&gt;"', $html);

        $given = Blade::render('<x-webx-suggest action="/s" source="/s/suggest" name="term" value="lamp" />');
        $this->assertStringContainsString('name="term" value="lamp"', $given);
    }

    #[Test]
    public function the_settings_reach_the_script(): void
    {
        $html = Blade::render('<x-webx-suggest action="/s" source="/s/suggest" id="header-search" :min="3" :delay="400" :recent="5" :show-label="true" />');

        $this->assertStringContainsString('data-min="3" data-delay="400" data-recent="5"', $html);
        $this->assertStringContainsString('<label class="webx-suggest__label webx-suggest__label--shown" for="header-search">', $html);
        $this->assertStringContainsString('id="header-search-panel"', $html);
    }

    #[Test]
    public function two_on_a_page_do_not_share_an_id(): void
    {
        $html = Blade::render('<x-webx-suggest action="/s" source="/s/x" /><x-webx-suggest action="/s" source="/s/x" />');

        $this->assertStringContainsString('id="suggest-1"', $html);
        $this->assertStringContainsString('id="suggest-2"', $html);
    }

    #[Test]
    public function the_words_are_in_the_language_of_the_page_and_each_is_a_prop(): void
    {
        app()->setLocale('pl');
        $html = Blade::render('<x-webx-suggest action="/s" source="/s/suggest" />');

        $this->assertStringContainsString('placeholder="Szukaj"', $html);
        $words = self::words($html);
        $this->assertSame(['label', 'nothing', 'recent', 'clear', 'count', 'failed'], array_keys($words));
        $this->assertSame('Nic nie znaleziono', $words['nothing']);
        $this->assertSame('Podpowiedzi: :count', $words['count']);

        app()->setLocale('en');
        $told = Blade::render('<x-webx-suggest action="/s" source="/s/suggest" label="Find a product" placeholder="Name or SKU" submit-label="Go" nothing-label="No such product" recent-label="Lately" clear-label="Forget" count-label="Found: :count" failed-label="Broken" />');
        $this->assertStringContainsString('>Find a product</label>', $told);
        $this->assertStringContainsString('placeholder="Name or SKU"', $told);
        $this->assertStringContainsString('<span class="webx-suggest__submit-text">Go</span>', $told);
        $this->assertSame(['label' => 'Find a product', 'nothing' => 'No such product', 'recent' => 'Lately', 'clear' => 'Forget', 'count' => 'Found: :count', 'failed' => 'Broken'], self::words($told));
    }

    #[Test]
    public function the_page_gets_its_files_only_where_a_box_is(): void
    {
        $this->artisan('webx:theme:sync')->assertSuccessful();

        $html = (string) $this->get('/with-search')->assertOk()->getContent();
        $this->assertMatchesRegularExpression('~<link rel="stylesheet" href="[^"]+/suggest\.css">~', $html);
        $this->assertMatchesRegularExpression('~<script type="module" src="[^"]+/suggest\.js"></script>~', $html);
        // A search box is not a form of the shell: form.js stays away.
        $this->assertStringNotContainsString('form.js', $html);

        $plain = (string) $this->get('/without')->assertOk()->getContent();
        $this->assertStringNotContainsString('suggest.js', $plain);
        $this->assertStringNotContainsString('suggest.css', $plain);
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function wrong(): iterable
    {
        yield 'no source' => ['<x-webx-suggest action="/s" source="" />'];
        yield 'an empty action' => ['<x-webx-suggest action=" " source="/s/x" />'];
        yield 'a name with brackets' => ['<x-webx-suggest action="/s" source="/s/x" name="filter[q]" />'];
        yield 'min 0' => ['<x-webx-suggest action="/s" source="/s/x" :min="0" />'];
        yield 'a delay too long' => ['<x-webx-suggest action="/s" source="/s/x" :delay="5000" />'];
        yield 'too many recent' => ['<x-webx-suggest action="/s" source="/s/x" :recent="50" />'];
    }

    #[Test]
    #[DataProvider('wrong')]
    public function a_mistake_in_the_template_says_so(string $template): void
    {
        try {
            Blade::render($template);
            $this->fail('No exception.');
        } catch (Throwable $exception) {
            $previous = $exception instanceof ViewException ? $exception->getPrevious() : $exception;
            $this->assertInstanceOf(InvalidArgumentException::class, $previous);
            $this->assertStringStartsWith('<x-webx-suggest', $previous->getMessage());
        }
    }

    /** @return array<string, string> */
    private static function words(string $html): array
    {
        preg_match('/data-words="([^"]+)"/', $html, $match);

        return (array) json_decode(html_entity_decode($match[1] ?? '{}', ENT_QUOTES), true);
    }
}
