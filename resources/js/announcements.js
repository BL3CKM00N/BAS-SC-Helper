// Keeps the announcement banners current: refreshes them every minute and when the app comes back to the
// front, and dismisses one without leaving the page.
const box = document.getElementById('announcements');

if (box) {
    const csrf = () => document.querySelector('meta[name="csrf-token"]')?.content || '';

    async function refresh() {
        try {
            const response = await fetch(box.dataset.url, { headers: { 'X-Requested-With': 'XMLHttpRequest' }, cache: 'no-store' });
            if (response.ok) box.innerHTML = await response.text();
        } catch (error) {
            // Offline: keep what is shown.
        }
    }

    box.addEventListener('submit', async (event) => {
        const form = event.target.closest('[data-dismiss-announcement]');
        if (!form) return;

        event.preventDefault();
        form.closest('[role]')?.remove();

        try {
            await fetch(form.action, { method: 'POST', headers: { 'X-CSRF-TOKEN': csrf(), Accept: 'application/json' } });
        } catch (error) {
            // It comes back on the next refresh if this did not reach the server.
        }
    });

    setInterval(() => {
        if (!document.hidden) refresh();
    }, 60000);

    document.addEventListener('visibilitychange', () => {
        if (!document.hidden) refresh();
    });
}
