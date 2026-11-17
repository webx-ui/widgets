<?php

declare(strict_types=1);

namespace WebxUi\Widgets\Tests;

use PHPUnit\Framework\Attributes\Test;
use WebxUi\Widgets\Widgets;

/**
 * The parallax of a picture (§14) has no file of its own: `data-webx-parallax` in any block — the
 * hero, the call to action, the text with tiles, a banner — is started by the runtime every themed
 * page loads, and a block drawn alone (`blocks_render`, the editor's stage) lists that runtime in
 * its assets. A picture the library's formats wrap in `<picture style="display: contents">` is the
 * same to it: the script measures the frame past the box-less wrapper (`behaviours/parallax.js`).
 */
final class ParallaxAssetsTest extends TestCase
{
    #[Test]
    public function a_block_with_a_moving_picture_comes_with_the_runtime_that_moves_it(): void
    {
        $this->artisan('webx:theme:sync')->assertSuccessful();

        $plain = '<section class="b-text-tiles"><img class="b-text-tiles__picture" data-webx-parallax src="/media/a.jpg" alt=""></section>';
        $formats = '<section class="b-text-tiles"><picture class="webx-picture" style="display: contents"><source type="image/avif" srcset="/media/a.avif"><img class="b-text-tiles__picture" data-webx-parallax="soft" src="/media/a.jpg" alt=""></picture></section>';

        foreach ([$plain, $formats] as $html) {
            $piece = app(Widgets::class)->piece($html);

            $this->assertStringContainsString('data-webx-parallax', $piece['html']);
            $this->assertCount(1, array_filter($piece['body'], static fn (string $tag): bool => preg_match('~<script type="module" src="[^"]+/runtime\.js"></script>~', $tag) === 1));
            $this->assertCount(1, array_filter($piece['head'], static fn (string $tag): bool => preg_match('~<link rel="stylesheet" href="[^"]+/runtime\.css">~', $tag) === 1));
        }

        // The runtime is what starts it: the behaviour on its attribute, the motion only for those who want it.
        $this->assertContains('parallax', Widgets::RUNTIME);
        $script = (string) file_get_contents(Widgets::path().'/dist/runtime.js');
        $this->assertStringContainsString('[data-webx-parallax]', $script);
        // The frame past a <picture> without a box.
        $this->assertMatchesRegularExpression('~["\']contents["\']~', $script);
        $styles = (string) file_get_contents(Widgets::path().'/dist/runtime.css');
        $this->assertStringContainsString('.webx-parallax', $styles);
        $this->assertMatchesRegularExpression('~prefers-reduced-motion:\s*no-preference~', $styles);
    }
}
