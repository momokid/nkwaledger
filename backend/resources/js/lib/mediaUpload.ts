// Sends the photo and voice note of health reports whose text the server already has, in fixed chunks.
// The server is the authority on how much of a file it holds: this asks, then sends from there. A row is
// deleted only when the server has confirmed every file; every other outcome keeps the row and its media.

import { listMediaIds, markDataApproved, markStuck, readItem, recordFailedAttempt, remove } from "./offlineStore";
import { dataCostGateAllows } from "./dataCostGate";
import { csrfToken, isSessionEnded } from "./syncHttp";
import { isHealthReport, QueuedHealthReport, QueuedMedia } from "@/types/offlineQueue";
import type { SyncOutcome } from "./offlineSync";

type Step = "done" | "gone" | "ended" | "offline" | "later" | "failed";

const BASE = "/sync/health-reports";
const MAX_ROUNDS = 3;
const MAX_REFUSED_OFFSETS = 5;

async function call(url: string, method: string, headers: Record<string, string> = {}, body?: BodyInit): Promise<Response | null> {
    try {
        return await fetch(url, {
            method,
            redirect: "manual",
            headers: { Accept: "application/json", "X-CSRF-TOKEN": csrfToken(), ...headers },
            body,
        });
    } catch {
        return null;
    }
}

// what a failed answer means for this row, or null when it is fine to read on
function stopFor(response: Response | null): Step | null {
    if (response === null) return "offline";
    if (isSessionEnded(response)) return "ended";
    if (response.status === 404) return "gone";
    if (response.status === 429) return "later";

    return response.ok ? null : "failed";
}

function bytesOf(media: QueuedMedia): Uint8Array<ArrayBuffer> {
    return Uint8Array.from(atob(media.data), (char) => char.charCodeAt(0));
}

async function sha256Hex(bytes: Uint8Array<ArrayBuffer>): Promise<string> {
    const digest = new Uint8Array(await crypto.subtle.digest("SHA-256", bytes));

    return Array.from(digest, (byte) => byte.toString(16).padStart(2, "0")).join("");
}

async function sendFile(uuid: string, kind: "photo" | "audio", media: QueuedMedia): Promise<Step> {
    const bytes = bytesOf(media);
    const url = `${BASE}/${uuid}/${kind}`;
    let refused = 0;

    for (let round = 0; round < MAX_ROUNDS; round++) {
        let response = await call(url, "GET");
        let stop = stopFor(response);

        if (stop) return stop;

        let state = await response!.json().catch(() => null);

        if (state?.state === "complete") return "done";

        if (typeof state?.offset !== "number") return "failed";

        if (state.state === "none") {
            response = await call(url, "POST", { "Content-Type": "application/json" }, JSON.stringify({ size: bytes.length, sha256: await sha256Hex(bytes) }));
            stop = stopFor(response);

            if (stop) return stop;

            state = await response!.json().catch(() => null);

            if (typeof state?.offset !== "number") return "failed";
        }

        let offset: number = state.offset;
        const chunk: number = state.chunk_size;

        while (offset < bytes.length) {
            const end = Math.min(offset + chunk, bytes.length);
            response = await call(url, "PUT", { "Content-Type": "application/octet-stream", "X-Upload-Offset": String(offset) }, bytes.slice(offset, end));

            if (response === null) return "offline";

            if (isSessionEnded(response)) return "ended";

            if (response.status === 409 && refused++ < MAX_REFUSED_OFFSETS) {
                const held = await response.json().catch(() => null);

                if (typeof held?.offset !== "number") return "failed";

                offset = held.offset;

                continue;
            }

            // the server dropped the upload (expired): ask again from the top
            if (response.status === 404) break;

            if (response.status === 429) return "later";

            if (!response.ok) return "failed";

            if ((await response.json().catch(() => null))?.state === "complete") return "done";

            offset = end;
        }
    }

    return "failed";
}

async function sendReport(report: QueuedHealthReport): Promise<Step> {
    const photo = report.media.photo ? await sendFile(report.uuid, "photo", report.media.photo) : "done";

    if (photo !== "done" || !report.media.audio) return photo;

    return sendFile(report.uuid, "audio", report.media.audio);
}

const hasMedia = (report: QueuedHealthReport) => Boolean(report.media?.photo || report.media?.audio);

// an upload begun before the farmer was ever asked is not interrupted by a question
async function alreadyStarted(report: QueuedHealthReport): Promise<boolean> {
    const response = await call(`${BASE}/${report.uuid}/${report.media.photo ? "photo" : "audio"}`, "GET");

    if (response === null || !response.ok) return false;

    const state = await response.json().catch(() => null);

    return state?.state === "complete" || (state?.state === "open" && state.offset > 0);
}

// one queue item is opened at a time, and let go before the next
export async function sendMedia(currentUser: string | null, outcome: SyncOutcome): Promise<void> {
    for (const { id, approved } of await listMediaIds(currentUser)) {
        const item = await readItem<unknown>(id);

        if (!isHealthReport(item)) continue;

        // nothing to send, so nothing to warn about
        if (!hasMedia(item)) {
            await remove(id);
            outcome.synced.push(id);

            continue;
        }

        if (!approved) {
            // "Wait" (or no way to ask) sends nothing: the row and its media stay as they are, and it is no failure
            if (!(await alreadyStarted(item)) && !(await dataCostGateAllows())) return;

            await markDataApproved(id);
        }

        const step = await sendReport(item);

        if (step === "done") {
            await remove(id);
            outcome.synced.push(id);
        } else if (step === "gone") {
            await markStuck(id);
        } else if (step === "failed") {
            await recordFailedAttempt(id);
        } else {
            if (step === "ended") outcome.authExpired = true;

            return;
        }
    }
}
