<?php

declare(strict_types=1);

namespace WebxUi\Widgets\View\Components;

use Illuminate\Contracts\View\View;
use Illuminate\View\Component;
use InvalidArgumentException;
use WebxUi\Widgets\Facades\Widgets;
use WebxUi\Widgets\Prose\Contents;

/**
 * `<x-webx-toc>…the text…</x-webx-toc>` — the table of contents of a long page (spec §14): a
 * policy, a service, an article.
 *
 * With a slot, the list stands beside the text — at its `side`, sticky under the header
 * (`--webx-header-height`) — and folds into a dropdown above it in a narrow container (§6.3).
 * Without one, the list stands where the tag is and is made of the element `for` names by id, or
 * of `<main>`: a theme with a column of its own for it, or the `toc` block at the top of a page.
 *
 * The list is made by the server once the page is finished (`Prose\Contents`): the headings get
 * their ids there, so every link works without JavaScript. The script marks the section being
 * read and folds the list on a narrow screen. `depth` is 2 for the `h2` only, 3 with the `h3`;
 * `fold="never"` keeps the list open in any width — for a column too narrow to be read as a phone.
 */
final class Toc extends Component
{
    public const array SIDES = ['start', 'end'];

    public const array FOLDS = ['auto', 'never'];

    public string $id;

    public string $heading;

    public function __construct(
        public ?string $for = null,
        public int $depth = 3,
        ?string $title = null,
        public string $side = 'end',
        public string $fold = 'auto',
    ) {
        if ($depth !== 2 && $depth !== 3) {
            throw new InvalidArgumentException("<x-webx-toc :depth=\"{$depth}\">: 2 for the h2 only, 3 with the h3.");
        }

        if (! in_array($side, self::SIDES, true)) {
            throw new InvalidArgumentException("<x-webx-toc side=\"{$side}\">: start or end.");
        }

        if (! in_array($fold, self::FOLDS, true)) {
            throw new InvalidArgumentException("<x-webx-toc fold=\"{$fold}\">: auto or never.");
        }

        if ($for !== null && preg_match('/^[A-Za-z][\w:.-]*$/', $for) !== 1) {
            throw new InvalidArgumentException("<x-webx-toc for=\"{$for}\">: the id of the element, without #.");
        }

        $this->id = 'webx-toc-'.Widgets::sequence('toc');
        $this->heading = $title !== null && trim($title) !== '' ? $title : (string) __('webx-widgets::widgets.toc.title');
    }

    public function render(): View
    {
        Widgets::need('toc');

        return view('webx-widgets::components.toc');
    }

    /**
     * Where the list goes: `Prose\Contents` replaces it once the whole page is known. `$from` is
     * the id of the element the headings are in; null is `<main>`.
     */
    public function marker(?string $from, bool $beside): string
    {
        return '<!--webx-toc:'.base64_encode((string) json_encode([
            'id' => $this->id,
            'from' => $from ?? Contents::MAIN,
            'depth' => $this->depth,
            'title' => $this->heading,
            'beside' => $beside,
        ], JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR)).'-->';
    }
}
