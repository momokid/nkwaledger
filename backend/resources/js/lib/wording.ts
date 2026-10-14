import map from "../../wording.json";

const WORDING: Record<string, string> = map;

// the approved wording for a key, from the same file the server reads
export function wording(key: string): string {
    const text = WORDING[key];

    if (text === undefined) {
        throw new Error(`No wording for ${key}`);
    }

    return text;
}

export function fill(key: string, values: Record<string, string>): string {
    return wording(key).replace(/\{(\w+)\}/g, (placeholder, name) => (name in values ? values[name] : placeholder));
}
