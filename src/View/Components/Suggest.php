<?php

declare(strict_types=1);

namespace WebxUi\Widgets\View\Components;

use Illuminate\Contracts\View\View;
use Illuminate\View\Component;
use InvalidArgumentException;
use WebxUi\Widgets\Facades\Widgets;

/**
 * `<x-webx-suggest :action="route('search')" :source="route('search.suggest')" />` — a search
 * box that suggests while typing (spec §18, W6.5).
 *
 * With `action` the server prints an ordinary GET form to the results page: without JavaScript it
 * is a search like any other. Without `action` it prints no form of its own — a field with
 * suggestions to stand inside another form (HTML has no form in a form): a city of a delivery, a
 * product of an order line. The script makes the field an ARIA combobox and asks `source`
 * (`?q=…`, or `param`) as the visitor types — after a pause, the stale request cancelled — and
 * puts the HTML it answers into the panel under the field.
 *
 * The content of the panel is the site's: sections, headings, counts, cards with a price, «see
 * all». The shell needs only `data-webx-option` on what can be chosen — a link, followed, or an
 * element whose attribute holds a value: searched for in a search box, put into the field inside
 * another form — and `data-webx-section` with a heading on a group of them. Recent searches are
 * kept in the visitor's browser when `recent` is above 0.
 *
 * Not a type of `<x-webx-field>`: a field's frame (hint, error, the checks of `FormRules`) belongs
 * to `<x-webx-form>`, and a search is a form of its own whose answer is a page.
 */
final class Suggest extends Component
{
    public string $key;

    public string $term;

    public string $query;

    public function __construct(
        public string $source,
        public ?string $action = null,
        public string $name = 'q',
        ?string $param = null,
        ?string $value = null,
        public ?string $id = null,
        public int $min = 2,
        public int $delay = 250,
        public int $recent = 0,
        public bool $showLabel = false,
        public ?string $label = null,
        public ?string $placeholder = null,
        public ?string $submitLabel = null,
        public ?string $nothingLabel = null,
        public ?string $recentLabel = null,
        public ?string $clearLabel = null,
        public ?string $countLabel = null,
        public ?string $failedLabel = null,
    ) {
        if (trim($source) === '' || ($action !== null && trim($action) === '')) {
            throw new InvalidArgumentException('<x-webx-suggest>: `source` (the suggestions) is needed, and `action`, when given, is the results page.');
        }

        // A search's name is its query parameter; a field inside another form is named as any field.
        $plain = '/^[A-Za-z_][A-Za-z0-9_-]*$/';
        if (preg_match($action === null ? '/^[A-Za-z_][A-Za-z0-9_.\-\[\]]*$/' : $plain, $name) !== 1) {
            throw new InvalidArgumentException("<x-webx-suggest name=\"{$name}\">: ".($action === null ? 'the name of a field.' : 'one plain name, it is the query parameter.'));
        }

        $this->query = $param ?? ($action === null ? 'q' : $name);
        if (preg_match($plain, $this->query) !== 1) {
            throw new InvalidArgumentException("<x-webx-suggest param=\"{$this->query}\">: one plain name.");
        }

        if ($min < 1 || $min > 10) {
            throw new InvalidArgumentException("<x-webx-suggest min=\"{$min}\">: 1 to 10 characters before asking.");
        }

        if ($delay < 0 || $delay > 2000) {
            throw new InvalidArgumentException("<x-webx-suggest delay=\"{$delay}\">: 0 to 2000 milliseconds.");
        }

        if ($recent < 0 || $recent > 20) {
            throw new InvalidArgumentException("<x-webx-suggest recent=\"{$recent}\">: 0 (none kept) to 20 recent searches.");
        }

        $this->key = $id ?? 'suggest-'.Widgets::sequence('suggest');

        if ($action !== null) {
            // The results page fills the box with what was searched; a value given wins.
            $asked = request()->query($name);
            $this->term = $value ?? (is_string($asked) ? $asked : '');
        } else {
            // A form sent back without JavaScript keeps what was typed, as a field does.
            $old = old(Field::keyOf($name));
            $this->term = is_string($old) ? $old : ($value ?? '');
        }
    }

    public function render(): View
    {
        Widgets::need('suggest');

        $label = $this->label ?? (string) __('webx-widgets::widgets.suggest.label');

        return view('webx-widgets::components.suggest', [
            'labelText' => $label,
            'placeholderText' => $this->placeholder ?? (string) __('webx-widgets::widgets.suggest.placeholder'),
            'submitText' => $this->submitLabel ?? (string) __('webx-widgets::widgets.suggest.submit'),
            'words' => [
                'label' => $label,
                'nothing' => $this->nothingLabel ?? __('webx-widgets::widgets.suggest.nothing'),
                'recent' => $this->recentLabel ?? __('webx-widgets::widgets.suggest.recent'),
                'clear' => $this->clearLabel ?? __('webx-widgets::widgets.suggest.clear'),
                'count' => $this->countLabel ?? __('webx-widgets::widgets.suggest.count'),
                'failed' => $this->failedLabel ?? __('webx-widgets::widgets.suggest.failed'),
            ],
        ]);
    }
}
