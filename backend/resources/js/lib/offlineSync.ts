// Replays whatever is sitting in the offline queue against the server, in the
// order it was recorded. Every request carries the item's idempotency key, so
// a retry after a partial success (response lost, tab closed mid-request) can
// never record the same thing twice — see PostingService::alreadyPosted.

import { listPending, markNeedsAttention, QueueItem, markSynced, recordFailedAttempt, remove } from "./offlineStore";
import { QueuedBatchRecord, QueuedHealthReport, QueuedSubmission } from "@/types/offlineQueue";

type BatchPayload = QueuedBatchRecord | QueuedHealthReport;

export interface SyncOutcome {
    synced: string[];
    needsAttention: Array<{ id: string; message: string }>;
    authExpired: boolean;
}

function csrfToken(): string {
    return (
        document.querySelector<HTMLMetaElement>('meta[name="csrf-token"]')
            ?.content ?? ""
    );
}

async function postQueuedItem(item: QueuedSubmission): Promise<Response> {
    return fetch(item.url, {
        method: "POST",
        // a redirect here only ever means "you are not properly signed in any
        // more" (see RecordTransactionController, which answers a JSON-expecting
        // request with a plain 200/422 and never a redirect) — treat it as such
        // instead of silently following it to whatever page it lands on
        redirect: "manual",
        headers: {
            Accept: "application/json",
            "Content-Type": "application/json",
            "X-CSRF-TOKEN": csrfToken(),
        },
        body: JSON.stringify(item.data),
    });
}

// a redirect, 401 or 419 all mean the same thing: sign in again
export function isSessionEnded(response: Pick<Response, "type" | "status">): boolean {
    return response.type === "opaqueredirect" || response.status === 419 || response.status === 401;
}

const SYNC_LOCK = "nkwa-offline-sync";
let running = false;

// any page that shows queued/needs-attention entries listens for this
export const OFFLINE_SYNC_RAN_EVENT = "nkwa:offline-sync-ran";

async function runAndAnnounce(currentUser: string | null): Promise<SyncOutcome> {
    try {
        return await runSync(currentUser);
    } finally {
        window.dispatchEvent(new Event(OFFLINE_SYNC_RAN_EVENT));
    }
}

// the one door every trigger uses: returns null at once if a run is already active
// (this tab via the flag, another tab via the Web Lock) instead of waiting or queueing
export async function syncOnce(currentUser: string | null): Promise<SyncOutcome | null> {
    if (running) {
        return null;
    }

    running = true;

    try {
        if (!navigator.locks) {
            return await runAndAnnounce(currentUser);
        }

        return await navigator.locks.request(
            SYNC_LOCK,
            { ifAvailable: true },
            async (lock) => (lock ? runAndAnnounce(currentUser) : null),
        );
    } finally {
        running = false;
    }
}

const BATCH_SIZE = 20;
const STORED_BY_SERVER = ["accepted", "needs_fixing", "held", "rejected", "superseded"];

interface BatchItem {
    id: string;
    payload: BatchPayload;
}

function isBatchItem(item: QueueItem<QueuedSubmission | BatchPayload>): item is QueueItem<BatchPayload> {
    return "shape" in item.payload && item.payload.shape === 2;
}

async function postBatch(records: BatchPayload[]): Promise<Response> {
    return fetch("/sync/submissions", {
        method: "POST",
        redirect: "manual",
        headers: {
            Accept: "application/json",
            "Content-Type": "application/json",
            "X-CSRF-TOKEN": csrfToken(),
        },
        body: JSON.stringify({ records: records.map(({ shape: _shape, ...record }) => {
            // a health report's photo and voice note stay on the phone until they are sent on their own
            const { media: _media, ...text } = record as Partial<QueuedHealthReport> & typeof record;

            return text;
        }) }),
    });
}

// a fetch that throws (offline, timeout), a session problem and a 429 leave the counters alone;
// the first two also stop the run (returns false)
async function sendBatch(items: BatchItem[], outcome: SyncOutcome): Promise<boolean> {
    let response: Response;

    try {
        response = await postBatch(items.map((item) => item.payload));
    } catch {
        return false;
    }

    if (isSessionEnded(response)) {
        outcome.authExpired = true;

        return false;
    }

    if (response.status === 422) {
        if (items.length === 1) {
            await recordFailedAttempt(items[0].id);

            return true;
        }

        for (const item of items) {
            if (!(await sendBatch([item], outcome))) {
                return false;
            }
        }

        return true;
    }

    if (response.status >= 400 && response.status !== 429) {
        for (const item of items) {
            await recordFailedAttempt(item.id);
        }

        return true;
    }

    if (!response.ok) {
        return true;
    }

    const body = await response.json().catch(() => null);
    const results: Array<{ uuid: string; status: string }> = Array.isArray(body?.results) ? body.results : [];

    for (const item of items) {
        const result = results.find((entry) => entry.uuid === item.payload.uuid);

        if (!result) {
            continue;
        }

        if (result.status === "error") {
            await recordFailedAttempt(item.id);
        } else if (STORED_BY_SERVER.includes(result.status)) {
            await remove(item.id);
            outcome.synced.push(item.id);
        }
    }

    return true;
}

export async function runSync(currentUser: string | null): Promise<SyncOutcome> {
    const outcome: SyncOutcome = { synced: [], needsAttention: [], authExpired: false };
    const everything = await listPending<QueuedSubmission | BatchPayload>(currentUser);
    const batchItems = everything.filter(isBatchItem);
    const pending = everything.filter((item) => !isBatchItem(item)) as Array<{ id: string; payload: QueuedSubmission }>;

    for (const item of pending) {
        let response: Response;

        try {
            response = await postQueuedItem(item.payload);
        } catch {
            // a network-level failure: leave it queued, the next sync will retry it
            continue;
        }

        if (isSessionEnded(response)) {
            outcome.authExpired = true;
            break;
        }

        if (response.ok) {
            await markSynced(item.id);
            await remove(item.id);
            outcome.synced.push(item.id);
            continue;
        }

        if (response.status === 422) {
            const body = await response.json().catch(() => null);
            const message =
                (body && typeof body === "object" && "message" in body
                    ? String((body as { message: unknown }).message)
                    : null) ?? "This entry needs your attention.";

            await markNeedsAttention(item.id, message);
            outcome.needsAttention.push({ id: item.id, message });
            continue;
        }

        // any other failure (server error, etc.): leave it queued, retry next time
    }

    for (let start = 0; start < batchItems.length && !outcome.authExpired; start += BATCH_SIZE) {
        if (!(await sendBatch(batchItems.slice(start, start + BATCH_SIZE), outcome))) {
            break;
        }
    }

    return outcome;
}
