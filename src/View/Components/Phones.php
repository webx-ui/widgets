<?php

declare(strict_types=1);

namespace WebxUi\Widgets\View\Components;

use Illuminate\Contracts\View\View;
use Illuminate\View\Component;
use InvalidArgumentException;
use WebxUi\Settings\Contacts\Phone;
use WebxUi\Widgets\Contacts\ContactsSource;
use WebxUi\Widgets\Facades\Widgets;

/**
 * `<x-webx-phones />` — the site's numbers (spec §12.2): the main one a `tel:` link, the others
 * in a dropdown beside it, each with its words and the messengers it is on.
 *
 *     <x-webx-phones />                       main number, the rest in the dropdown
 *     <x-webx-phones callback="callback" />   and "Request a call" under it — a form in a dialog
 *     <x-webx-phones compact />               a handset that opens the dropdown with every number
 *     <x-webx-phones layout="list" />         every number in a list: the footer, the contacts page
 *
 * The numbers are the Contacts tab's; `:phones` hands it others (`Phone` objects). With none it
 * prints nothing.
 */
final class Phones extends Component
{
    public const array LAYOUTS = ['dropdown', 'list'];

    /** @var list<Phone> */
    public array $numbers;

    /**
     * @param  list<Phone>|null  $phones
     */
    public function __construct(
        public string $layout = 'dropdown',
        public ?string $callback = null,
        public bool $compact = false,
        public string $placement = 'bottom-end',
        ?array $phones = null,
        public ?string $label = null,
        public ?string $more = null,
        public ?string $callbackLabel = null,
    ) {
        if (! in_array($layout, self::LAYOUTS, true)) {
            throw new InvalidArgumentException("<x-webx-phones layout=\"{$layout}\">: dropdown or list.");
        }

        $this->numbers = $phones ?? ContactsSource::get()?->phones() ?? [];
    }

    public function shouldRender(): bool
    {
        return $this->numbers !== [];
    }

    public function render(): View
    {
        Widgets::need('contacts');

        if ($this->callback !== null) {
            Widgets::form($this->callback);
        }

        $primary = null;

        foreach ($this->numbers as $phone) {
            $primary ??= $phone->primary ? $phone : null;
        }

        $primary ??= $this->numbers[0];

        return view('webx-widgets::components.phones', [
            'primary' => $primary,
            'others' => array_values(array_filter($this->numbers, static fn (Phone $phone): bool => $phone !== $primary)),
            'labelText' => $this->label ?? __('webx-widgets::widgets.phones.label'),
            'moreText' => $this->more ?? __('webx-widgets::widgets.phones.more'),
            'callbackText' => $this->callbackLabel ?? __('webx-widgets::widgets.phones.callback'),
        ]);
    }
}
