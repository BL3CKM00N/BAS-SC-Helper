// The header menus: the hamburger menu on small screens and the profile dropdown on larger ones.
// Both close with Escape (the focus goes back to the button), a tap outside or choosing a link,
// and opening one closes the other.
const menus = [];

function bind(button, panel, apply) {
    if (!button || !panel) return;

    const menu = {
        button,
        panel,
        isOpen: () => button.getAttribute('aria-expanded') === 'true',
        set(open) {
            button.setAttribute('aria-expanded', String(open));
            apply(open);
        },
    };

    menus.push(menu);

    button.addEventListener('click', () => {
        const open = !menu.isOpen();

        menus.forEach((other) => other !== menu && other.set(false));
        menu.set(open);
    });

    panel.addEventListener('click', (event) => {
        if (event.target.closest('a')) menu.set(false);
    });
}

const menuButton = document.querySelector('[data-menu-button]');
const profileButton = document.querySelector('[data-profile-button]');

bind(menuButton, menuButton && document.getElementById(menuButton.getAttribute('aria-controls')), function (open) {
    // On a phone the menu is hidden until opened, on wider screens CSS keeps it visible.
    document.getElementById(menuButton.getAttribute('aria-controls')).classList.toggle('hidden', !open);
});

bind(profileButton, profileButton && document.getElementById(profileButton.getAttribute('aria-controls')), (open) => {
    document.getElementById(profileButton.getAttribute('aria-controls')).hidden = !open;
});

if (menus.length) {
    document.addEventListener('keydown', (event) => {
        if (event.key !== 'Escape') return;

        const open = menus.find((menu) => menu.isOpen());

        if (open) {
            open.set(false);
            open.button.focus();
        }
    });

    document.addEventListener('click', (event) => {
        menus.forEach((menu) => {
            if (menu.isOpen() && !menu.panel.contains(event.target) && !menu.button.contains(event.target)) menu.set(false);
        });
    });

    // The menu is always visible on wide screens, so start from a closed state when the screen grows.
    window.matchMedia('(min-width: 640px)').addEventListener('change', (event) => {
        if (event.matches) menus.forEach((menu) => menu.set(false));
    });
}
