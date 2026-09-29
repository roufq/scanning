import { router } from '@inertiajs/vue3';

/**
 * Inertia ignores asset version changes on background (async) requests such as
 * polling, which leaves an open page frozen after a rebuild or deploy. Reload
 * the page instead so it picks up the new assets and fresh data.
 */
export function initializeReloadOnNewVersion(): void {
    router.on('location', (event) => {
        if (event.detail.versionChange) {
            window.location.reload();
        }
    });
}
