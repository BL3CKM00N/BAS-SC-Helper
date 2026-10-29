// Reminder folders are closed by default. A folder the person opens stays open after the page reloads,
// and moving a reminder to another folder is saved as soon as the choice is made.
const KEY = 'reminder-folders-open';

function read() {
    try {
        return JSON.parse(localStorage.getItem(KEY)) || [];
    } catch (error) {
        return [];
    }
}

function write(ids) {
    try {
        localStorage.setItem(KEY, JSON.stringify(ids));
    } catch (error) {
        // Private mode or blocked storage: folders simply start closed every time.
    }
}

const open = new Set(read());

document.querySelectorAll('details[data-folder]').forEach((details) => {
    const id = details.dataset.folder;

    details.open = open.has(id);
    details.addEventListener('toggle', () => {
        details.open ? open.add(id) : open.delete(id);
        write([...open]);
    });
});

document.querySelectorAll('select[data-auto-submit]').forEach((select) => {
    select.addEventListener('change', () => select.form.requestSubmit());
});

// Drag a reminder onto a folder (or onto the loose lists to take it out of its folder). A mouse starts
// dragging after a few pixels, a finger after a short press so scrolling still works. A closed folder
// opens when you hold a reminder over it. Dropping submits the reminder's own folder selector.
const LONG_PRESS_MS = 350;
const MOVE_THRESHOLD = 6;
const OPEN_DELAY_MS = 500;

let pending = null;
let drag = null;

function folderSelect(card) {
    return card.querySelector('select[data-auto-submit]');
}

function startDrag(card, x, y) {
    const ghost = document.createElement('div');

    ghost.className = 'drag-ghost';
    ghost.textContent = card.querySelector('h3')?.textContent.trim() || 'Reminder';
    document.body.appendChild(ghost);
    card.classList.add('is-dragging');
    document.body.classList.add('is-dragging-page');

    drag = { card, ghost, target: null, openTimer: null };
    moveDrag(x, y);
}

function targetAt(x, y) {
    return document.elementFromPoint(x, y)?.closest('[data-drop-folder]') || null;
}

function moveDrag(x, y) {
    drag.ghost.style.left = `${x}px`;
    drag.ghost.style.top = `${y}px`;

    const target = targetAt(x, y);

    if (target === drag.target) return;

    drag.target?.classList.remove('drop-ready');
    clearTimeout(drag.openTimer);
    drag.target = target;
    target?.classList.add('drop-ready');

    if (target?.matches('details') && !target.open) {
        drag.openTimer = setTimeout(() => { target.open = true; }, OPEN_DELAY_MS);
    }
}

function endDrag(drop) {
    const { card, ghost, target, openTimer } = drag;

    clearTimeout(openTimer);
    ghost.remove();
    card.classList.remove('is-dragging');
    target?.classList.remove('drop-ready');
    document.body.classList.remove('is-dragging-page');
    drag = null;

    const select = folderSelect(card);

    if (drop && target && select && select.value !== target.dataset.dropFolder) {
        select.value = target.dataset.dropFolder;
        select.form.requestSubmit();
    }
}

function cancelPending() {
    clearTimeout(pending?.timer);
    pending = null;
}

document.addEventListener('pointerdown', (event) => {
    const card = event.target.closest('[data-reminder-card]');

    if (!card || !folderSelect(card) || event.button > 0 || event.target.closest('a, button, select, input, textarea, label, summary')) return;

    const touch = event.pointerType !== 'mouse';

    pending = {
        card, touch, x: event.clientX, y: event.clientY, x0: event.clientX, y0: event.clientY,
        timer: touch ? setTimeout(() => {
            const { card: target, x, y } = pending;

            pending = null;
            startDrag(target, x, y);
        }, LONG_PRESS_MS) : null,
    };
});

document.addEventListener('pointermove', (event) => {
    if (drag) {
        moveDrag(event.clientX, event.clientY);

        return;
    }

    if (!pending) return;

    pending.x = event.clientX;
    pending.y = event.clientY;

    const moved = Math.hypot(event.clientX - pending.x0, event.clientY - pending.y0);

    if (moved <= MOVE_THRESHOLD) return;

    if (pending.touch) {
        cancelPending();
    } else {
        const { card } = pending;

        cancelPending();
        startDrag(card, event.clientX, event.clientY);
    }
});

document.addEventListener('pointerup', () => {
    cancelPending();
    if (drag) endDrag(true);
});

document.addEventListener('pointercancel', () => {
    cancelPending();
    if (drag) endDrag(false);
});

// Once a finger is dragging a reminder the page must not scroll with it.
document.addEventListener('touchmove', (event) => {
    if (drag) event.preventDefault();
}, { passive: false });

document.addEventListener('selectstart', (event) => {
    if (drag) event.preventDefault();
});

document.addEventListener('keydown', (event) => {
    if (event.key === 'Escape' && drag) endDrag(false);
});

// Small popovers (the "New folder" form): focus the field when one opens, close on a click elsewhere or Escape.
document.querySelectorAll('details[data-popover]').forEach((popover) => {
    popover.addEventListener('toggle', () => {
        if (popover.open) popover.querySelector('input')?.focus();
    });
});

document.addEventListener('click', (event) => {
    document.querySelectorAll('details[data-popover][open]').forEach((popover) => {
        if (!popover.contains(event.target)) popover.open = false;
    });
});

document.addEventListener('keydown', (event) => {
    if (event.key !== 'Escape') return;

    document.querySelectorAll('details[data-popover][open]').forEach((popover) => { popover.open = false; });
});
