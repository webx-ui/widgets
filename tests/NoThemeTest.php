<?php

declare(strict_types=1);

namespace WebxUi\Widgets\Tests;

use Illuminate\Foundation\Application;
use Illuminate\Support\Facades\Blade;
use PHPUnit\Framework\Attributes\Test;

/** A site that has not adopted themes keeps the head it had — the widgets add nothing to it. */
class NoThemeTest extends TestCase
{
    /**
     * @param  Application  $app
     */
    protected function defineEnvironment($app): void
    {
        parent::defineEnvironment($app);

        $app['config']->set('webx-themes.theme', '');
    }

    #[Test]
    public function the_head_is_untouched(): void
    {
        $this->assertSame('', Blade::render('@webxTheme'));

        $this->artisan('webx:theme:sync')->assertSuccessful();
        $this->assertDirectoryDoesNotExist($this->public.'/themes');
    }
}
