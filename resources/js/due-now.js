// Keeps the "due now" list live: it refreshes every 30 seconds, when the tab becomes visible again and
// after each action, and draws attention when a reminder shows up that was not there before.
const container = document.querySelector('[data-due-now]');

if (container) {
    const POLL_MS = 30000;
    const csrf = document.querySelector('meta[name="csrf-token"]')?.content ?? '';
    const reloadAfterAction = container.hasAttribute('data-reload-after-action');
    const baseTitle = document.title;
    let known = new Set([...container.querySelectorAll('[data-reminder-id]')].map((el) => el.dataset.reminderId));
    let timer = null;

    function alertUser() {
        try {
            navigator.vibrate?.([200, 100, 200]);
        } catch (error) {
            // Vibration is not available everywhere.
        }

        try {
            const context = new (window.AudioContext || window.webkitAudioContext)();
            const oscillator = context.createOscillator();
            const gain = context.createGain();

            oscillator.frequency.value = 880;
            gain.gain.value = 0.05;
            oscillator.connect(gain).connect(context.destination);
            oscillator.start();
            oscillator.stop(context.currentTime + 0.25);
        } catch (error) {
            // Browsers only allow sound after the person has interacted with the page.
        }
    }

    function showCount(count) {
        document.title = count > 0 ? `(${count}) ${baseTitle}` : baseTitle;
    }

    async function refresh() {
        clearTimeout(timer);

        try {
            const response = await fetch('/reminders/due', { headers: { Accept: 'application/json' }, credentials: 'same-origin' });

            if (response.ok) {
                const data = await response.json();
                const ids = new Set(data.ids.map(String));
                const fresh = [...ids].some((id) => !known.has(id));

                if (!reloadAfterAction || fresh || ids.size !== known.size) {
                    container.innerHTML = data.html;
                }

                if (fresh) alertUser();

                known = ids;
                showCount(ids.size);
            }
        } catch (error) {
            // The connection overlay tells the person when the server cannot be reached.
        }

        timer = setTimeout(refresh, POLL_MS);
    }

    container.addEventListener('submit', async (event) => {
        event.preventDefault();

        const form = event.target;

        try {
            const response = await fetch(form.action, {
                method: 'POST',
                body: new FormData(form, event.submitter),
                headers: { Accept: 'application/json', 'X-CSRF-TOKEN': csrf },
                credentials: 'same-origin',
            });

            if (!response.ok) throw new Error('failed');
        } catch (error) {
            form.submit();

            return;
        }

        if (reloadAfterAction) {
            window.location.reload();

            return;
        }

        refresh();
    });

    document.addEventListener('visibilitychange', () => {
        if (document.visibilityState === 'visible') refresh();
    });

    showCount(known.size);
    timer = setTimeout(refresh, POLL_MS);
}
