{{-- A password: never printed back, autocomplete="current-password" unless told "new-password".
     "Show" is the script's — hidden without it — and keeps one name; aria-pressed says the state. --}}
<span class="webx-field__password">
    <input type="password" class="webx-field__control webx-field__control--password" id="{{ $fieldId }}" name="{{ $inputName }}" {{ $control($attributes) }}>
    <button type="button" class="webx-field__show" aria-controls="{{ $fieldId }}" aria-pressed="false" aria-label="{{ $words['show_password'] }}" data-webx-password hidden @disabled($disabled)>{{ $words['show'] }}</button>
</span>
