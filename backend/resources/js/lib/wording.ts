import map from "../../wording.json";

const WORDING: Record<string, string> = map;

export const FALLBACK = "Something went wrong. Please try again.";

const reported = new Set<string>();

// the approved wording for a key, from the same file the server reads
export function wording(key: string): string {
    const text = WORDING[key];

    if (text !== undefined) {
        return text;
    }

    // the page runs on: only a developer's own build stops here
    if (!import.meta.env.PROD) {
        throw new Error(`No wording for ${key}`);
    }

    if (!reported.has(key)) {
        reported.add(key);
        console.error(`Missing wording key: ${key}`);
    }

    return FALLBACK;
}

export function fill(key: string, values: Record<string, string>): string {
    return wording(key).replace(/\{(\w+)\}/g, (placeholder, name) => (name in values ? values[name] : placeholder));
}
