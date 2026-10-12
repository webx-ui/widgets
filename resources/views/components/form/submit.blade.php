{{-- The button of a form (spec §17.3). Class: webx-form__submit. --}}
<button {{ $attributes->class(['webx-form__submit'])->merge(['type' => 'submit']) }}>{{ $slot }}</button>
