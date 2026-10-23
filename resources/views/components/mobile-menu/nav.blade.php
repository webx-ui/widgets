{{--
    A menu tree in the mobile menu (spec §6.1). Every branch is a <details>, so without
    JavaScript it opens in place in both modes; in `drill` the runtime lays an open branch over
    its parent, with Back and the branch's name on top. The visitor's branch is open. With
    `parent="split"` an accordion's branch that is a link is the link and an arrow beside it.

    Classes: webx-mobile-nav, --accordion, --drill, __list, __item, __row, __link (+ --split), __group (+ --split),
    __toggle (+ --split), __level, __level--drill, __back, __title, __heading; is-current on the
    visitor's item.
--}}
<nav {{ $attributes->class(['webx-mobile-nav', 'webx-mobile-nav--'.$mode])->merge(['aria-label' => $navLabel, 'data-webx-mobile-nav' => $mode]) }}>
    <ul class="webx-mobile-nav__list">
        @include('webx-widgets::components.mobile-menu.nav-items', ['items' => $tree])
    </ul>
</nav>
