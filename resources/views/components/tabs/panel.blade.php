<section {{ $attributes->class(['webx-tabs__panel'])->merge(['data-webx-tab' => '', 'data-webx-tab-selected' => $selected ? '' : null]) }}>
    <{{ $heading }} class="webx-tabs__title">{{ $title }}</{{ $heading }}>
    {{ $slot }}
</section>
