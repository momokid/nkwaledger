// who a saved offline record belongs to, kept as a plain field so it can be
// checked without decrypting anything

export function buildQueueRow<T extends object>(parts: T, owner: string | null) {
    return { ...parts, synced: false, ...(owner ? { owner } : {}) };
}

// no owner means saved before owners existed; no current user means send nothing
export function belongsTo(item: { owner?: string }, currentUser: string | null): boolean {
    if (currentUser === null) {
        return false;
    }

    return item.owner === undefined || item.owner === currentUser;
}

export function ownedBy<T extends { owner?: string }>(items: T[], currentUser: string | null): T[] {
    return items.filter((item) => belongsTo(item, currentUser));
}
