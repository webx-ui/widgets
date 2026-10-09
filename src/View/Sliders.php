<?php

declare(strict_types=1);

namespace WebxUi\Widgets\View;

/**
 * The sliders open while a page renders, so that a slide knows where it stands (spec §7).
 *
 * Blade resolves a component's view before it renders the slot, and the view itself after it:
 * `<x-webx-slider>` opens a frame when it resolves, each `<x-webx-slide>` inside takes its number
 * from the innermost frame, and the slider's view closes the frame once its slides are drawn.
 * The number is what decides whether a slide's pictures load at once — the first ones in view —
 * or `loading="lazy"`. Scoped, like the claims: a long-lived worker starts every request empty.
 */
final class Sliders
{
    /** @var list<array{eager: int, slides: int}> */
    private array $open = [];

    /** @var array<string, int> */
    private array $ids = [];

    /** A slider begins; its first `$eager` slides are in view when the page opens. */
    public function open(int $eager): void
    {
        $this->open[] = ['eager' => $eager, 'slides' => 0];
    }

    /**
     * The next slide of the innermost slider: its number from 1, and whether it is in view when
     * the page opens. A slide outside any slider is in view.
     *
     * @return array{index: int, eager: bool}
     */
    public function slide(): array
    {
        $last = array_key_last($this->open);

        if ($last === null) {
            return ['index' => 1, 'eager' => true];
        }

        $index = ++$this->open[$last]['slides'];

        return ['index' => $index, 'eager' => $index <= $this->open[$last]['eager']];
    }

    /** The innermost slider is drawn: how many slides it had. */
    public function close(): int
    {
        return array_pop($this->open)['slides'] ?? 0;
    }

    /**
     * An id for a slider that was given none, the same for the same slides: its `<style>` and
     * its controls address it, and two sliders on one page must not share one.
     */
    public function id(string $content): string
    {
        $id = 'webx-slider-'.substr(md5($content), 0, 8);
        $this->ids[$id] = ($this->ids[$id] ?? 0) + 1;

        return $this->ids[$id] === 1 ? $id : $id.'-'.$this->ids[$id];
    }
}
