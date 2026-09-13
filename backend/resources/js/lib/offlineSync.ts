// Replays whatever is sitting in the offline queue against the server, in the
// order it was recorded. Every request carries the item's idempotency key, so
// a retry after a partial success (response lost, tab closed mid-request) can
// never record the same thing twice — see PostingService::alreadyPosted.

import { listPending, markNeedsAttention, markSynced, remove } from "./offlineStore";
import { QueuedSubmission } from "@/types/offlineQueue";

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

export async function runSync(): Promise<SyncOutcome> {
    const outcome: SyncOutcome = { synced: [], needsAttention: [], authExpired: false };
    const pending = await listPending<QueuedSubmission>();

    for (const item of pending) {
        let response: Response;

        try {
            response = await postQueuedItem(item.payload);
        } catch {
            // a network-level failure: leave it queued, the next sync will retry it
            continue;
        }

        if (response.type === "opaqueredirect" || response.status === 419 || response.status === 401) {
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

    return outcome;
}
