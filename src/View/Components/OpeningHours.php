<?php

declare(strict_types=1);

namespace WebxUi\Widgets\View\Components;

use Illuminate\Contracts\View\View;
use Illuminate\View\Component;
use InvalidArgumentException;
use WebxUi\Settings\Contacts\Hours;
use WebxUi\Widgets\Contacts\ContactsSource;
use WebxUi\Widgets\Contacts\HoursView;
use WebxUi\Widgets\Facades\Widgets;

/**
 * `<x-webx-hours />` — "Open until 19:00", the week in a dropdown under it; `layout="table"` —
 * the week as a table, for the contacts page (spec §12.3).
 *
 * The status is the site's time zone's, never the visitor's. The server writes it as of the
 * response; the script writes it again in the browser and each minute after, because a page
 * may lie in a cache for hours. Consecutive days with the same hours fold into one row, and the
 * special dates of the next month are listed under the week.
 */
final class OpeningHours extends Component
{
    public const array LAYOUTS = ['status', 'table'];

    public ?HoursView $view = null;

    /**
     * @param  array<string, string>  $words  sentences in place of the dictionary's, by kind
     */
    public function __construct(
        public string $layout = 'status',
        public string $placement = 'bottom-start',
        ?Hours $hours = null,
        public ?string $label = null,
        array $words = [],
    ) {
        if (! in_array($layout, self::LAYOUTS, true)) {
            throw new InvalidArgumentException("<x-webx-hours layout=\"{$layout}\">: status or table.");
        }

        $hours ??= ContactsSource::get()?->hours();

        if ($hours !== null && ! $hours->isEmpty()) {
            $this->view = new HoursView($hours, app()->getLocale(), $words);
        }
    }

    public function shouldRender(): bool
    {
        return $this->view !== null;
    }

    public function render(): View
    {
        Widgets::need('contacts');

        /** @var HoursView $view */
        $view = $this->view;

        return view('webx-widgets::components.hours', [
            'status' => $view->status(),
            'rows' => $view->rows(),
            'special' => $view->special(),
            'script' => $view->script(),
            'labelText' => $this->label ?? __('webx-widgets::widgets.hours.label'),
            'specialText' => __('webx-widgets::widgets.hours.special'),
        ]);
    }
}
