<?php

declare(strict_types=1);

namespace WebxUi\Widgets\Tests;

use Illuminate\Support\Facades\Blade;
use Illuminate\View\ViewException;
use PHPUnit\Framework\Attributes\Test;
use WebxUi\Widgets\Facades\Widgets;
use WebxUi\Widgets\View\Components\Select;

/**
 * A list with a search (decision 36 of the delivery spec): a plain `<select>` — or a text field —
 * without JavaScript, the combobox's settings beside it for the script.
 */
final class SelectTest extends TestCase
{
    #[Test]
    public function without_javascript_it_is_a_plain_select_of_the_options_given(): void
    {
        $html = Blade::render('<x-webx-select name="point" :options="$options" value="2" placeholder="Choose a branch" searchable source="/api/points?method=a" />', [
            'options' => [
                ['value' => 1, 'label' => 'Branch 1', 'hint' => '1 Example street'],
                ['value' => 2, 'label' => 'Branch 2'],
                ['value' => 3, 'label' => 'Branch 3', 'disabled' => true],
            ],
        ]);

        $this->assertMatchesRegularExpression('~<div data-webx-select="/api/points\?method=a" data-param="q" data-min="2" data-delay="250" data-strict="true" data-words="[^"]+" class="webx-select webx-select--searchable">~', $html);
        $this->assertStringContainsString('&quot;searching&quot;:&quot;Searching…&quot;', $html);
        $this->assertStringContainsString('<label class="webx-select__label" for="select-1">Choose</label>', $html);
        $this->assertStringContainsString('<select class="webx-select__native" id="select-1" name="point"', $html);
        $this->assertStringContainsString('<option value="">Choose a branch</option>', $html);
        $this->assertMatchesRegularExpression('~<option value="1"\s+data-hint="1 Example street"\s*>Branch 1</option>~', $html);
        $this->assertMatchesRegularExpression('~<option value="2"\s+selected\s*>Branch 2</option>~', $html);
        $this->assertMatchesRegularExpression('~<option value="3"\s+disabled\s*>Branch 3</option>~', $html);
        $this->assertStringContainsString('<p class="webx-select__status" role="status"></p>', $html);
        $this->assertSame(['select'], Widgets::claimed());
    }

    #[Test]
    public function a_value_chosen_from_the_server_stays_chosen_though_the_page_does_not_hold_it(): void
    {
        $html = Blade::render('<x-webx-select name="point" :options="[1 => \'Branch 1\']" value="77" searchable />');

        $this->assertStringContainsString('<option value="77" selected>77</option>', $html);
        // Searchable without a server: the loaded options only, the attribute still marks it.
        $this->assertStringContainsString('data-webx-select=""', $html);
        $this->assertStringNotContainsString('data-inline', $html);

        // In a dialog: the list opens in the flow.
        $this->assertStringContainsString('data-inline="true"', Blade::render('<x-webx-select name="town" searchable inline />'));
    }

    #[Test]
    public function not_strict_it_is_a_text_field_with_the_suggestions_in_a_template(): void
    {
        $html = Blade::render('<x-webx-select name="address[street]" :strict="false" :options="[\'Example street\']" value="Elm" source="/api/streets" :min="0" label="Street" :show-label="true" />');

        $this->assertStringContainsString('data-min="0"', $html);
        $this->assertStringContainsString('data-strict="false"', $html);
        $this->assertStringContainsString('class="webx-select webx-select--searchable webx-select--free"', $html);
        $this->assertMatchesRegularExpression('~<input class="webx-select__native" type="text" id="select-1" name="address\[street\]" value="Elm"~', $html);
        $this->assertStringNotContainsString('<select', $html);
        $this->assertMatchesRegularExpression('~<template class="webx-select__options">\s*<option value="0"\s*>Example street</option>~', $html);
        $this->assertStringContainsString('<label class="webx-select__label webx-select__label--shown" for="select-1">Street</label>', $html);
    }

    #[Test]
    public function a_form_sent_back_keeps_what_was_chosen(): void
    {
        $this->app['session.store']->flashInput(['delivery' => ['point' => '5']]);
        $this->app['request']->setLaravelSession($this->app['session.store']);

        $html = Blade::render('<x-webx-select name="delivery[point]" :options="[5 => \'Branch 5\', 6 => \'Branch 6\']" value="6" />');

        $this->assertMatchesRegularExpression('~<option value="5"\s+selected\s*>Branch 5</option>~', $html);
        // Not searchable: a plain select, no combobox for the script to start.
        $this->assertStringNotContainsString('data-webx-select', $html);
    }

    #[Test]
    public function the_words_are_props_with_the_dictionary_behind_them(): void
    {
        $this->app->setLocale('pl');
        $html = Blade::render('<x-webx-select name="a" searchable more-label="Next 20" />');

        $this->assertStringContainsString('Wybierz…', $html);
        $words = json_decode(html_entity_decode((string) preg_replace('~^.*data-words="([^"]+)".*$~s', '$1', $html)), true);
        $this->assertSame('Next 20', $words['more']);
        $this->assertSame('Nic nie znaleziono', $words['nothing']);
    }

    #[Test]
    public function options_are_a_map_or_a_list_and_nothing_else(): void
    {
        $this->assertSame(
            [['value' => 'a', 'label' => 'A', 'hint' => null, 'disabled' => false], ['value' => '2', 'label' => 'Two', 'hint' => 'h', 'disabled' => false]],
            Select::normalise(['a' => 'A', ['2', 'Two', 'hint' => 'h']]),
        );

        $this->expectException(ViewException::class);
        Blade::render('<x-webx-select name="a" :options="[[\'value\' => []]]" />');
    }

    #[Test]
    public function a_wrong_name_or_parameter_is_refused(): void
    {
        $this->expectException(ViewException::class);
        Blade::render('<x-webx-select name="1 bad" />');
    }
}
