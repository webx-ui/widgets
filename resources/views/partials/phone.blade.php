<li class="webx-phones__item">
    <a class="webx-phones__number" href="{{ $phone->href }}">{{ $phone->number }}</a>
    @if ($phone->label !== null)
        <span class="webx-phones__label">{{ $phone->label }}</span>
    @endif
    @include('webx-widgets::partials.phone-messengers', ['phone' => $phone])
</li>
