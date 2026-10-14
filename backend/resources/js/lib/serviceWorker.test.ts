import { readFileSync } from "node:fs";
import { beforeEach, describe, expect, it, vi } from "vitest";

// public/sw.js is plain script, so it is run here against a small stand-in for the worker's world

const ORIGIN = "https://nkwa.test";
const SHELL = "nkwa-shell-v1";

const OFFLINE_BODY = "<html>offline</html>";

class FakeCache {
    store = new Map<string, Response>();

    constructor(private readonly fetcher: () => (request: unknown) => Promise<Response>) {}

    private key(request: unknown): string {
        return new URL(typeof request === "string" ? request : (request as { url: string }).url, ORIGIN).href;
    }

    async match(request: unknown) {
        return this.store.get(this.key(request))?.clone();
    }

    async put(request: unknown, response: Response) {
        this.store.set(this.key(request), response);
    }

    async add(request: unknown) {
        const asRequest = typeof request === "string" ? { url: new URL(request, ORIGIN).href } : request;

        await this.put(request, await this.fetcher()(asRequest));
    }

    async addAll(requests: unknown[]) {
        for (const request of requests) {
            await this.add(request);
        }
    }
}

class FakeCaches {
    map = new Map<string, FakeCache>();

    constructor(private readonly fetcher: () => (request: unknown) => Promise<Response>) {}

    async open(name: string) {
        if (!this.map.has(name)) {
            this.map.set(name, new FakeCache(this.fetcher));
        }

        return this.map.get(name)!;
    }

    async keys() {
        return Array.from(this.map.keys());
    }

    async delete(name: string) {
        return this.map.delete(name);
    }

    async match(request: unknown) {
        for (const cache of this.map.values()) {
            const hit = await cache.match(request);

            if (hit) {
                return hit;
            }
        }

        return undefined;
    }

    stored(): string[] {
        return Array.from(this.map.values()).flatMap((cache) => Array.from(cache.store.keys()));
    }
}

let listeners: Record<string, (event: unknown) => void>;
let caches: FakeCaches;
let network: (request: unknown) => Promise<Response>;
let selfStub: { skipWaiting: ReturnType<typeof vi.fn>; clients: { claim: ReturnType<typeof vi.fn>; matchAll: ReturnType<typeof vi.fn> } };

function load() {
    listeners = {};
    selfStub = {
        skipWaiting: vi.fn(),
        clients: { claim: vi.fn(), matchAll: vi.fn(async () => []) },
        addEventListener: (type: string, listener: (event: unknown) => void) => {
            listeners[type] = listener;
        },
        location: { origin: ORIGIN },
    } as never;

    new Function("self", "caches", "fetch", readFileSync("public/sw.js", "utf8"))(selfStub, caches, (request: unknown) => network(request));
}

function request(path: string, init: { method?: string; mode?: string; headers?: Record<string, string>; destination?: string } = {}) {
    return {
        method: init.method ?? "GET",
        url: path.startsWith("http") ? path : ORIGIN + path,
        mode: init.mode ?? "cors",
        destination: init.destination ?? "",
        headers: new Headers(init.headers ?? {}),
    };
}

// runs a fetch event the way the browser would; says whether the worker answered it
async function dispatch(req: ReturnType<typeof request>) {
    let answer: Promise<Response> | undefined;

    listeners.fetch({ request: req, respondWith: (promise: Promise<Response>) => (answer = promise) });

    return answer ? { answered: true as const, response: await answer.catch((error: unknown) => error) } : { answered: false as const };
}

const ok = (body: string, type: string) => new Response(body, { status: 200, headers: { "Content-Type": type } });

beforeEach(async () => {
    network = async (req) => {
        const url = (req as { url: string }).url;

        if (url.endsWith("/offline.html")) {
            return ok(OFFLINE_BODY, "text/html");
        }

        return ok("body", "text/plain");
    };
    caches = new FakeCaches(() => (req) => network(req));
    load();
    await Promise.all(
        (() => {
            const waits: Promise<unknown>[] = [];
            listeners.install({ waitUntil: (promise: Promise<unknown>) => waits.push(promise) });

            return waits;
        })(),
    );
});

describe("what the worker stores", () => {
    it("keeps only the offline page at install", () => {
        expect(caches.stored()).toEqual([`${ORIGIN}/offline.html`]);
    });

    it("never touches a request that is not a GET", async () => {
        for (const method of ["POST", "PUT", "PATCH", "DELETE"]) {
            expect((await dispatch(request("/sync/submissions", { method }))).answered).toBe(false);
        }

        expect((await dispatch(request("/build/assets/app-1.js", { method: "POST" }))).answered).toBe(false);
        expect(caches.stored()).toEqual([`${ORIGIN}/offline.html`]);
    });

    it("leaves JSON, API and page-data requests to the network, and stores none of them", async () => {
        network = async () => ok('{"user":"ama"}', "application/json");

        for (const path of ["/sync/submissions", "/auth/check", "/api/anything", "/my-records", "/pin-reset/send", "/agent/farmers"]) {
            expect((await dispatch(request(path, { headers: { Accept: "application/json" } }))).answered).toBe(false);
        }

        expect((await dispatch(request("/my-records", { headers: { "X-Inertia": "true" } }))).answered).toBe(false);
        expect(caches.stored()).toEqual([`${ORIGIN}/offline.html`]);
    });

    it("does not store a page the browser navigated to, only passes it through", async () => {
        network = async () => ok("<html>Ama's records</html>", "text/html");

        const result = await dispatch(request("/my-records", { mode: "navigate", destination: "document" }));

        expect(result.answered).toBe(true);
        expect(caches.stored()).toEqual([`${ORIGIN}/offline.html`]);
    });

    it("stores built scripts, styles, icons and font files, and serves them from the store afterwards", async () => {
        let hits = 0;
        network = async () => {
            hits += 1;

            return ok("asset", "text/javascript");
        };

        for (const path of ["/build/assets/app-abc.js", "/build/assets/app-abc.css", "/favicon.ico", "/apple-touch-icon.png"]) {
            await dispatch(request(path));
            await dispatch(request(path));
        }

        expect(hits).toBe(4);
        expect(caches.stored()).toEqual(
            expect.arrayContaining([`${ORIGIN}/build/assets/app-abc.js`, `${ORIGIN}/build/assets/app-abc.css`, `${ORIGIN}/favicon.ico`]),
        );

        await dispatch(request("https://fonts.gstatic.com/s/inter/v1/font.woff2"));
        expect(caches.stored()).toContain("https://fonts.gstatic.com/s/inter/v1/font.woff2");
    });

    it("does not store a JSON or HTML answer even from a static path, nor a failed one", async () => {
        network = async () => ok("{}", "application/json");
        await dispatch(request("/build/assets/data.js"));
        network = async () => ok("<html>", "text/html");
        await dispatch(request("/build/assets/page.js"));
        network = async () => new Response("no", { status: 404 });
        await dispatch(request("/build/assets/missing.js"));

        expect(caches.stored()).toEqual([`${ORIGIN}/offline.html`]);
    });

    it("leaves offline record saving and sync to the network, as before", async () => {
        const post = await dispatch(request("/sync/submissions", { method: "POST" }));
        const oldPost = await dispatch(request("/my-records", { method: "POST" }));
        const check = await dispatch(request("/auth/check"));

        expect([post.answered, oldPost.answered, check.answered]).toEqual([false, false, false]);
    });
});

describe("a page load that fails", () => {
    it("shows the offline page, the same one for everybody", async () => {
        network = async () => {
            throw new TypeError("offline");
        };

        const result = await dispatch(request("/my-records", { mode: "navigate", destination: "document" }));
        const other = await dispatch(request("/admin/farmers", { mode: "navigate", destination: "document" }));

        expect(await (result.response as Response).text()).toBe(OFFLINE_BODY);
        expect(await (other.response as Response).text()).toBe(OFFLINE_BODY);
    });

    it("goes to the network first, so an online page load shows the live page", async () => {
        network = async () => ok("live page", "text/html");

        const result = await dispatch(request("/my-records", { mode: "navigate", destination: "document" }));

        expect(await (result.response as Response).text()).toBe("live page");
    });
});

describe("updating the worker", () => {
    it("removes the caches of older versions and keeps the current one", async () => {
        await caches.open("nkwa-shell-v0");
        await caches.open("nkwa-shell-v-ancient");

        const waits: Promise<unknown>[] = [];
        listeners.activate({ waitUntil: (promise: Promise<unknown>) => waits.push(promise) });
        await Promise.all(waits);

        expect(await caches.keys()).toEqual([SHELL]);
    });

    it("takes over on the next page load: it does not grab the pages already open or reload them", async () => {
        const waits: Promise<unknown>[] = [];
        listeners.activate({ waitUntil: (promise: Promise<unknown>) => waits.push(promise) });
        await Promise.all(waits);

        expect(selfStub.clients.claim).not.toHaveBeenCalled();
    });

    it("still wakes an open page to send the saved records when the browser says the connection is back", async () => {
        const postMessage = vi.fn();
        selfStub.clients.matchAll.mockResolvedValue([{ postMessage }]);
        const waits: Promise<unknown>[] = [];

        listeners.sync({ tag: "nkwa-offline-sync", waitUntil: (promise: Promise<unknown>) => waits.push(promise) });
        await Promise.all(waits);

        expect(postMessage).toHaveBeenCalledWith({ type: "NKWA_RUN_OFFLINE_SYNC" });
    });
});

describe("the offline page", () => {
    const page = readFileSync("public/offline.html", "utf8");

    it("says exactly the approved words", () => {
        expect(page).toContain("<title>You are offline</title>");
        expect(page).toContain("You are offline");
        expect(page).toContain("This page needs the internet. Connect and try again.");
        expect(page).toContain("Try again");
    });

    it("reloads the page from its button", () => {
        expect(page).toMatch(/<button[^>]*onclick="location\.reload\(\)"[^>]*>\s*Try again\s*<\/button>/);
    });

    it("holds no user data and nothing that could read it", () => {
        expect(page).not.toMatch(/<script/i);
        expect(page).not.toMatch(/indexedDB|localStorage|sessionStorage|document\.cookie|fetch\(|XMLHttpRequest|csrf|data-page|inertia/i);
        expect(page).not.toMatch(/<(link|img|iframe)[^>]+(src|href)=/i);
    });
});
