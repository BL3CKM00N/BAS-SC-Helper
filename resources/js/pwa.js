// When running as an installed app (standalone), lock the viewport so pinch and double-tap
// zooming can't make the app jump around. Regular browser tabs keep normal zoom.
const standalone = window.matchMedia('(display-mode: standalone)').matches || window.navigator.standalone === true;

if (standalone) {
    const viewport = document.querySelector('meta[name="viewport"]');

    if (viewport) {
        viewport.setAttribute('content', 'width=device-width, initial-scale=1, maximum-scale=1, user-scalable=no');
    }

    // iOS ignores user-scalable, so block its pinch gesture events as well.
    ['gesturestart', 'gesturechange', 'gestureend'].forEach((name) => {
        document.addEventListener(name, (event) => event.preventDefault());
    });
}
