// A confirmation dialog in the style of the app, instead of the browser's own prompt.
// Put data-confirm="Question?" on a form (optionally data-confirm-title, data-confirm-button and
// data-confirm-tone="danger") and the form is only submitted after the person confirms.
let dialog = null;
let titleEl = null;
let textEl = null;
let confirmBtn = null;
let cancelBtn = null;
let resolveAnswer = null;

function build() {
    dialog = document.createElement('dialog');
    dialog.className = 'app-dialog';
    dialog.setAttribute('aria-labelledby', 'app-dialog-title');
    dialog.setAttribute('aria-describedby', 'app-dialog-text');
    dialog.innerHTML = `
        <form method="dialog" class="app-dialog-body">
            <h2 id="app-dialog-title" class="app-dialog-title"></h2>
            <p id="app-dialog-text" class="app-dialog-text"></p>
            <div class="app-dialog-actions">
                <button type="button" class="app-dialog-cancel">Cancel</button>
                <button type="submit" class="app-dialog-confirm"></button>
            </div>
        </form>`;

    titleEl = dialog.querySelector('.app-dialog-title');
    textEl = dialog.querySelector('.app-dialog-text');
    cancelBtn = dialog.querySelector('.app-dialog-cancel');
    confirmBtn = dialog.querySelector('.app-dialog-confirm');

    const finish = (answer) => {
        const resolve = resolveAnswer;
        resolveAnswer = null;

        if (dialog.open) dialog.close();
        resolve?.(answer);
    };

    cancelBtn.addEventListener('click', () => finish(false));
    dialog.querySelector('form').addEventListener('submit', (event) => {
        event.preventDefault();
        finish(true);
    });
    // Escape, and a click on the dark area around the dialog, both mean "no".
    dialog.addEventListener('cancel', (event) => {
        event.preventDefault();
        finish(false);
    });
    dialog.addEventListener('click', (event) => {
        if (event.target === dialog) finish(false);
    });

    document.body.appendChild(dialog);
}

function ask({ title, message, button, tone }) {
    if (!dialog) build();

    titleEl.textContent = title;
    textEl.textContent = message;
    confirmBtn.textContent = button;
    confirmBtn.classList.toggle('is-danger', tone === 'danger');

    return new Promise((resolve) => {
        resolveAnswer?.(false);
        resolveAnswer = resolve;
        dialog.showModal();
        // The safe choice has focus, so pressing Enter by accident does not confirm.
        cancelBtn.focus();
    });
}

// Captured on the document so it runs before any other submit handler (like the due-now list).
document.addEventListener('submit', async (event) => {
    const form = event.target;

    if (!(form instanceof HTMLFormElement) || !form.dataset.confirm) return;

    if (form.dataset.confirmed === '1') {
        delete form.dataset.confirmed;

        return;
    }

    event.preventDefault();
    event.stopImmediatePropagation();

    const submitter = event.submitter;
    const confirmed = await ask({
        title: form.dataset.confirmTitle || 'Are you sure?',
        message: form.dataset.confirm,
        button: form.dataset.confirmButton || 'Yes, continue',
        tone: form.dataset.confirmTone,
    });

    if (confirmed) {
        form.dataset.confirmed = '1';
        form.requestSubmit(submitter ?? undefined);
    }
}, true);
