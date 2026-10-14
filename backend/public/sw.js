// The app's service worker. It does two jobs and nothing else:
//
// 1. Static shell only. It keeps built scripts and styles, icons, font files and one plain offline
//    page. It never stores a page that carries someone's data, a JSON or API answer, or anything
//    that is not a GET. Those always go straight to the network.
// 2. Background Sync. When the browser says the connection is back it wakes any open tab and asks
//    it to flush the offline queue using the already-tested logic in resources/js/lib/offlineSync.ts.
//    This worker does not itself read, decrypt or send anything from the queue. Background Sync has
//    no support on iOS Safari at all, so it only layers on top of the foreground/reconnect sync in
//    useOfflineSync, never replacing it.
//
// Bump SHELL_CACHE (and the same name in resources/js/lib/workerCaches.ts) to retire old caches.

const SHELL_CACHE = "nkwa-shell-v1";
const CACHE_PREFIX = "nkwa-shell-";
const OFFLINE_URL = "/offline.html";
const SYNC_TAG = "nkwa-offline-sync";

const ICONS = ["/favicon.ico", "/favicon.svg", "/favicon-32.png", "/apple-touch-icon.png"];
const FONT_HOST = "fonts.gstatic.com";

// a new worker starts working for pages opened after it installs: it never claims a page that is
// already open, so nothing is reloaded under someone filling in a form
self.addEventListener("install", (event) => {
    event.waitUntil(
        caches
            .open(SHELL_CACHE)
            .then((cache) => cache.addAll([OFFLINE_URL]))
            .catch(() => {}),
    );
    self.skipWaiting();
});

self.addEventListener("activate", (event) => {
    event.waitUntil(
        caches
            .keys()
            .then((names) => Promise.all(names.filter((name) => name.startsWith(CACHE_PREFIX) && name !== SHELL_CACHE).map((name) => caches.delete(name)))),
    );
});

function isShellAsset(request) {
    if (request.headers.has("X-Inertia") || request.destination === "document") {
        return false;
    }

    const url = new URL(request.url);

    if (url.hostname === FONT_HOST) {
        return true;
    }

    return url.origin === self.location.origin && (url.pathname.startsWith("/build/assets/") || ICONS.includes(url.pathname));
}

// an answer that failed, or that cannot be read (opaque), is never kept; neither is JSON or a page
function isStorable(response) {
    const type = response.headers.get("Content-Type") || "";

    return response.ok && !/json|html/i.test(type);
}

async function fromStoreOrNetwork(request) {
    const cache = await caches.open(SHELL_CACHE);
    const hit = await cache.match(request);

    if (hit) {
        return hit;
    }

    const response = await fetch(request);

    if (isStorable(response)) {
        await cache.put(request, response.clone());
    }

    return response;
}

self.addEventListener("fetch", (event) => {
    const request = event.request;

    if (request.method !== "GET") {
        return;
    }

    // a page load: always the live page, and only if the network fails, the plain offline page
    if (request.mode === "navigate") {
        event.respondWith(
            fetch(request).catch(async () => {
                const cache = await caches.open(SHELL_CACHE);

                return (await cache.match(OFFLINE_URL)) || Response.error();
            }),
        );

        return;
    }

    if (isShellAsset(request)) {
        event.respondWith(fromStoreOrNetwork(request));
    }
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
