// the shape of anything this app queues while offline, so Stage 5's sync engine
// can replay the exact request without knowing about each feature that queues one
export interface QueuedSubmission {
    url: string;
    data: Record<string, string>;
}
