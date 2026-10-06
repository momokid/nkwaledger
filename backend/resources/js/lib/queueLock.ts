const QUEUE_LOCK = "nkwa-queue";

// ponytail: fallback is one chain per tab, so it does not cover a second tab
let tail: Promise<unknown> = Promise.resolve();

// one exclusive turn at the queue and its key: writes and sign-out take it, so a record is never
// encrypted with a key that sign-out is deleting. It is released when the work settles, even on failure.
export async function withQueueLock<T>(work: () => Promise<T>): Promise<T> {
    if (typeof navigator !== "undefined" && navigator.locks) {
        return await navigator.locks.request(QUEUE_LOCK, {}, work);
    }

    const run = tail.then(work);
    tail = run.catch(() => {});

    return run;
}
