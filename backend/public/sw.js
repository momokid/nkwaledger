// Minimal service worker whose only job is Background Sync: when the browser
// fires a deferred "sync" event (e.g. connectivity returned while this tab was
// closed or backgrounded), it wakes any open tab and asks it to flush the
// offline queue using the already-tested logic in resources/js/lib/offlineSync.ts.
// This worker does not itself decrypt or POST anything — Background Sync has no
// support on iOS Safari at all, so it is a progressive enhancement layered on
// top of the foreground/reconnect sync in useOfflineSync, never a replacement for it.

const SYNC_TAG = "nkwa-offline-sync";

self.addEventListener("install", () => {
    self.skipWaiting();
});

self.addEventListener("activate", (event) => {
    event.waitUntil(self.clients.claim());
});

self.addEventListener("sync", (event) => {
    if (event.tag !== SYNC_TAG) {
        return;
    }

    event.waitUntil(
        self.clients.matchAll({ type: "window" }).then((clients) => {
            clients.forEach((client) => client.postMessage({ type: "NKWA_RUN_OFFLINE_SYNC" }));
        }),
    );
});
