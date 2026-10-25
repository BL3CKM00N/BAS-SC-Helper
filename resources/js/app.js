import './pwa';
import './confirm';
import './connection';
import './due-now';
import './push';

if ('serviceWorker' in navigator) {
    window.addEventListener('load', () => {
        navigator.serviceWorker.register('/sw.js').catch(() => {});
    });
}
