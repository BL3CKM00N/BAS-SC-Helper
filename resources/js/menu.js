// The hamburger menu of the app header on small screens.
const button = document.querySelector('[data-menu-button]');
const menu = button ? document.getElementById(button.getAttribute('aria-controls')) : null;

if (button && menu) {
    const isOpen = () => button.getAttribute('aria-expanded') === 'true';

    const set = (open) => {
        button.setAttribute('aria-expanded', String(open));
        menu.classList.toggle('hidden', !open);
    };

    button.addEventListener('click', () => set(!isOpen()));

    // Close with Escape (and give the focus back to the button), and when tapping outside or choosing a link.
    document.addEventListener('keydown', (event) => {
        if (event.key === 'Escape' && isOpen()) {
            set(false);
            button.focus();
        }
    });

    document.addEventListener('click', (event) => {
        if (isOpen() && !menu.contains(event.target) && !button.contains(event.target)) set(false);
    });

    menu.addEventListener('click', (event) => {
        if (event.target.closest('a')) set(false);
    });

    // The menu is always visible on wide screens, so start from a closed state when the screen grows.
    window.matchMedia('(min-width: 640px)').addEventListener('change', (event) => {
        if (event.matches) set(false);
    });
}
