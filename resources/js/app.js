import './pwa';
import './menu';
import './confirm';
import './connection';
import './due-now';
import './push';
import './nudge';
import './announcements';

if ('serviceWorker' in navigator) {
    window.addEventListener('load', () => {
        navigator.serviceWorker.register('/sw.js').catch(() => {});
    });
}
