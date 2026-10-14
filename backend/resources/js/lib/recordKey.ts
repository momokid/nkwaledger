// one uuid per record: kept until a final answer (or a reset for a new record) renews it
export function createRecordKey() {
    let key: string | null = null;

    return {
        current: () => (key ??= crypto.randomUUID()),
        renew: () => {
            key = null;
        },
    };
}
