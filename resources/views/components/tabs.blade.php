{{--
    Tabs (spec §6): `<x-webx-tabs.panel>` inside. The runtime reads each panel's heading into a
    tab of a tab list it puts on top; without JavaScript the headings stay above their panels.

    Classes: webx-tabs, __list, __tab, __panel, __title; is-enhanced once the runtime took over.
--}}
<div {{ $attributes->class(['webx-tabs'])->merge(['data-webx-tabs' => '', 'data-webx-tabs-label' => $label]) }}>
    {{ $slot }}
</div>
