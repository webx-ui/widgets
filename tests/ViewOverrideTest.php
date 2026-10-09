<?php

declare(strict_types=1);

namespace WebxUi\Widgets\Tests;

use Illuminate\Support\Facades\Blade;
use PHPUnit\Framework\Attributes\Test;

class ViewOverrideTest extends TestCase
{
    protected function theme(): string
    {
        return 'fixture/widgets-theme-override';
    }

    /** The fourth door (THEMES §10.5): the theme's views/vendor/webx-widgets wins over the package's view. */
    #[Test]
    public function a_theme_replaces_the_view_of_a_component(): void
    {
        $html = Blade::render('<x-webx-tabs><x-webx-tabs.panel title="A">a</x-webx-tabs.panel></x-webx-tabs>');

        $this->assertStringContainsString('<div class="theme-tabs" data-webx-tabs>', $html);
        $this->assertStringContainsString('<h3 class="webx-tabs__title">A</h3>', $html, 'the panel, which the theme left alone, is the package\'s');
    }
}
