// The service worker's caches hold only the static shell, but they still belong to the phone, not to
// whoever signs in next. They are cleared after a sign-out and when a different user turns up.
// This touches the Cache Storage only: the record queue, stuck records and the PIN live in IndexedDB.

// the same name as SHELL_CACHE in public/sw.js
export const SHELL_CACHE = "nkwa-shell-v1";
export const OFFLINE_URL = "/offline.html";

const LAST_USER_KEY = "nkwa_last_user";

export async function clearWorkerCaches(): Promise<void> {
    if (typeof caches === "undefined") {
        return;
    }

    try {
        await Promise.all((await caches.keys()).map((name) => caches.delete(name)));

        // the plain offline page goes straight back, so a phone that drops offline next still has it
        await (await caches.open(SHELL_CACHE)).add(OFFLINE_URL);
    } catch {
        // a cache that cannot be cleared or refilled is not worth stopping a sign-in or sign-out for
    }
}

// compares the signed-in user with the last one recorded on this phone
export async function noteSignedInUser(userId: string): Promise<"first" | "same" | "switched"> {
    let last: string | null = null;

    try {
        last = localStorage.getItem(LAST_USER_KEY);
        localStorage.setItem(LAST_USER_KEY, userId);
    } catch {
        // without local storage a switch cannot be seen, and nothing is cleared
    }

    if (last === null) {
        return "first";
    }

    if (last === userId) {
        return "same";
    }

    await clearWorkerCaches();

    return "switched";
}
