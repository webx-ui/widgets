<?php

declare(strict_types=1);

namespace WebxUi\Widgets\Tests;

use Illuminate\Support\Facades\Blade;
use PHPUnit\Framework\Attributes\Test;
use WebxUi\Widgets\Overlap;
use WebxUi\Widgets\Widgets;

/**
 * Spec §14.1: a section that slides over the one above — the attribute on its root and the edge
 * picture over its top, printed by `@webxOverlap` and `@webxOverlapEdge`; off, nothing at all.
 */
final class OverlapTest extends TestCase
{
    private const array WIDE = ['url' => '/media/fog.png', 'width' => 1920, 'height' => 220];

    private const array NARROW = ['url' => '/media/fog-phone.png', 'width' => 720, 'height' => 140];

    #[Test]
    public function off_or_never_set_prints_nothing(): void
    {
        foreach ([false, null, 0, '0', '', 'off', 'no'] as $off) {
            $this->assertSame('', Overlap::attributes($off), var_export($off, true));
            $this->assertSame('', Overlap::edge($off, self::WIDE, self::NARROW), var_export($off, true));
        }

        $this->assertSame(
            '<section class="b-x" data-wx-block="x">',
            Blade::render('<section class="b-x" data-wx-block="x"@webxOverlap($overlap ?? false)>@webxOverlapEdge($overlap ?? false, $edge ?? null)', [], deleteCachedView: true),
        );
    }

    #[Test]
    public function on_the_root_is_marked_and_the_edge_stands_first_inside_it(): void
    {
        $html = Blade::render(
            '<section data-wx-block="x"@webxOverlap($overlap)>@webxOverlapEdge($overlap, $edge, $narrow)<p>Words</p></section>',
            ['overlap' => true, 'edge' => self::WIDE, 'narrow' => self::NARROW],
            deleteCachedView: true,
        );

        $this->assertSame(
            '<section data-wx-block="x" data-webx-overlap><picture class="webx-overlap__edge" aria-hidden="true">'
            .'<source media="(max-width: 40rem)" srcset="/media/fog-phone.png" width="720" height="140">'
            .'<img class="webx-overlap__image" src="/media/fog.png" alt="" width="1920" height="220" decoding="async"></picture><p>Words</p></section>',
            $html,
        );
    }

    #[Test]
    public function either_file_alone_is_the_edge_at_every_width_and_none_is_no_edge(): void
    {
        $phone = Overlap::edge(true, null, self::NARROW);
        $this->assertStringNotContainsString('<source', $phone);
        $this->assertStringContainsString('src="/media/fog-phone.png"', $phone);

        $wide = Overlap::edge('1', self::WIDE, ['url' => '']);
        $this->assertStringNotContainsString('<source', $wide);
        $this->assertStringContainsString('src="/media/fog.png"', $wide);

        // A file without its size: no width and height rather than zeros.
        $this->assertStringNotContainsString('width=', Overlap::edge(true, ['url' => '/media/fog.png']));

        $this->assertSame('', Overlap::edge(true, null, null));
        $this->assertSame('', Overlap::edge(true, 'fog.png', []));
        // The attribute stands without an edge: the section still slides, on its own top.
        $this->assertSame(' data-webx-overlap', Overlap::attributes(true));
    }

    #[Test]
    public function the_address_is_escaped(): void
    {
        $html = Overlap::edge(true, ['url' => '/media/a"b.png']);

        $this->assertStringContainsString('src="/media/a&quot;b.png"', $html);
    }

    #[Test]
    public function the_behaviour_is_in_the_runtime(): void
    {
        $this->assertContains('overlap', Widgets::RUNTIME);
    }
}
