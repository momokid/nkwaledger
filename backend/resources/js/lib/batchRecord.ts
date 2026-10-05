import { QueuedBatchRecord } from "@/types/offlineQueue";

// the uuid is made here, once, and is the retry key for the life of the item
export function buildBatchRecord(
    farmer: string,
    data: Record<string, string>,
    quantity: string,
): QueuedBatchRecord {
    return {
        shape: 2,
        uuid: crypto.randomUUID(),
        template: Number(data.transaction_template_id),
        farmer,
        amount: data.amount,
        event_date: data.transaction_date,
        device_created_at: new Date().toISOString(),
        ...(data.farm_unit_id ? { farm_unit_id: Number(data.farm_unit_id) } : {}),
        ...(data.settlement_account_id ? { settlement_account_id: Number(data.settlement_account_id) } : {}),
        is_credit: data.is_credit === "1",
        ...(quantity ? { quantity } : {}),
        ...(data.narration ? { narration: data.narration } : {}),
    };
}
