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
