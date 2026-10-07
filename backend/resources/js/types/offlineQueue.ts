// the shape of anything this app queues while offline, so Stage 5's sync engine
// can replay the exact request without knowing about each feature that queues one
export interface QueuedSubmission {
    url: string;
    data: Record<string, string>;
}

// what /sync/submissions takes; `shape` tells it apart from the older {url, data} items
// and is never sent to the server
export interface QueuedBatchRecord {
    shape: 2;
    uuid: string;
    template: number;
    farmer: string;
    amount: string;
    event_date: string;
    device_created_at: string;
    farm_unit_id?: number;
    settlement_account_id?: number;
    is_credit?: boolean;
    quantity?: string;
    narration?: string;
}

// a photo or voice note kept on the phone as base64, so it is encrypted with the rest of the queue
export interface QueuedMedia {
    name: string;
    type: string;
    data: string;
}

// a health report saved offline: the text goes to /sync/submissions, `media` never does
export interface QueuedHealthReport {
    shape: 2;
    type: "health_report";
    uuid: string;
    farmer: string;
    farm_unit_id: number;
    description: string;
    event_date: string;
    device_created_at: string;
    media: { photo: QueuedMedia; audio?: QueuedMedia };
}

export function isHealthReport(payload: unknown): payload is QueuedHealthReport {
    return typeof payload === "object" && payload !== null && (payload as { type?: unknown }).type === "health_report";
}
