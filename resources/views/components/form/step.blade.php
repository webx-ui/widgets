{{--
    One step of <x-webx-form steps> (FORM_STEPS §3): a fieldset with its title in the legend, the
    fields in __fields, then the buttons steps.js shows — hidden without it, when every step stands
    on the page and the one button of the form sends them all. __count («Step 2 of 3») is written by
    the script too; the title takes the focus when the step comes (tabindex="-1").

    A step with `:when` / `:unless` carries `data-webx-when` / `data-webx-unless`: form.js disables
    its fields while the answers before it do not call for it, steps.js counts it out. __condition is
    the line for the page without the script, hidden by it.

    Override: <layer>/views/vendor/webx-widgets/components/form/step.blade.php — keep
    `data-webx-step`, `data-webx-step-label`, `data-webx-step-back`, `data-webx-step-next`.

    Classes: webx-form-step, __legend, __count, __title, __hint, __condition, __fields, __nav,
    __back, __next.
--}}
<fieldset {{ $attributes->class(['webx-form-step'])->merge(['id' => $stepId]) }} data-webx-step="{{ $name }}"@if ($short !== null) data-webx-step-label="{{ $short }}"@endif{!! $conditionAttributes() !!}>
    <legend class="webx-form-step__legend">
        <span class="webx-form-step__count" hidden></span>
        @if ($heading !== null)
            <span class="webx-form-step__title" id="{{ $stepId }}-title" role="heading" aria-level="{{ $level }}" tabindex="-1">{{ $heading }}</span>
        @endif
    </legend>
    @if ($hint !== null && trim($hint) !== '')
        <p class="webx-form-step__hint">{{ $hint }}</p>
    @endif
    @if ($conditional() && $condition !== null && trim($condition) !== '')
        <p class="webx-form-step__condition" data-webx-condition>{{ $condition }}</p>
    @endif
    <div class="webx-form-step__fields">
        {{ $slot }}
    </div>
    <div class="webx-form-step__nav" hidden>
        <button type="button" class="webx-form-step__back" data-webx-step-back>{{ $words['back'] }}</button>
        <button type="button" class="webx-form-step__next" data-webx-step-next>{{ $words['next'] }}</button>
    </div>
</fieldset>
