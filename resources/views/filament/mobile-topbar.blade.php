{{-- A bar for the admin panel on phones, in the style of the app header: the logo on the left and the menu button on the right.
     On a computer the panel has no top bar (see the styles in AdminPanelProvider), the sidebar holds the logo and the profile menu. --}}
<header class="bas-topbar" x-data="{}" x-bind:inert="$store.sidebar.isOpenMobile">
    <a href="{{ url('/admin') }}" class="bas-topbar-logo">
        <img src="{{ asset(app()->environment('local') ? 'logo-dev.svg' : 'logo.svg') }}" alt="BAS World">
    </a>

    <button type="button" class="bas-topbar-button" aria-controls="fi-main-sidebar" x-bind:aria-expanded="$store.sidebar.isOpen" x-on:click="$store.sidebar.open($event.currentTarget)">
        <span class="bas-sr-only">Menu</span>
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" aria-hidden="true"><path d="M4 7h16M4 12h16M4 17h16" /></svg>
    </button>
</header>
