{{--
    The site's numbers (spec §12.2). The main one is a tel: link in E.164 and shows as typed;
    with more than one, the others are in a dropdown (§6.3) beside it. `compact` is a handset
    that opens the same dropdown with every number in it; `list` is every number, one under the
    other. `callback` adds a link to a form in a dialog (§6.4) under the number.

    Classes: webx-phones, --dropdown, --list, --compact, __primary, __number, __label, __list,
    __item, __messengers, __messenger, __toggle, __callback.
--}}
<div {{ $attributes->class(['webx-phones', 'webx-phones--'.$layout, 'webx-phones--compact' => $compact && $layout === 'dropdown']) }}>
    @if ($layout === 'list')
        <ul class="webx-phones__list" aria-label="{{ $labelText }}">
            @foreach ([$primary, ...$others] as $phone)
                @include('webx-widgets::partials.phone', ['phone' => $phone])
            @endforeach
        </ul>
    @elseif ($compact)
        <x-webx-dropdown class="webx-phones__dropdown" :placement="$placement" :label="$labelText">
            <x-slot:trigger class="webx-phones__toggle"><x-webx-icon name="phone" /></x-slot:trigger>
            <ul class="webx-phones__list">
                @foreach ([$primary, ...$others] as $phone)
                    @include('webx-widgets::partials.phone', ['phone' => $phone])
                @endforeach
            </ul>
        </x-webx-dropdown>
    @else
        <div class="webx-phones__primary">
            <a class="webx-phones__number" href="{{ $primary->href }}">{{ $primary->number }}</a>
            @include('webx-widgets::partials.phone-messengers', ['phone' => $primary])
            @if ($others !== [])
                <x-webx-dropdown class="webx-phones__dropdown" :placement="$placement" :label="$moreText">
                    <x-slot:trigger class="webx-phones__toggle">
                    </x-slot:trigger>
                    <ul class="webx-phones__list">
                        @foreach ($others as $phone)
                            @include('webx-widgets::partials.phone', ['phone' => $phone])
                        @endforeach
                    </ul>
                </x-webx-dropdown>
            @endif
        </div>
    @endif

    @if ($callback !== null)
        <a class="webx-phones__callback" href="#webx-form-{{ $callback }}">{{ $callbackText }}</a>
    @endif
</div>
