import warningText from "@/config/dataCostWarning.txt?raw";

// the wording is not approved yet, so the gate stays off and the text file stays empty;
// switch it on only together with the approved text and the screen that asks
export const DATA_COST_GATE_ENABLED = false;

let ask: ((text: string) => Promise<boolean>) | null = null;

export function setDataCostAsker(asker: ((text: string) => Promise<boolean>) | null): void {
    ask = asker;
}

// asked once before media starts to upload; a "no", or a gate with nothing to ask with, sends nothing and deletes nothing
export async function dataCostGateAllows(): Promise<boolean> {
    if (!DATA_COST_GATE_ENABLED) {
        return true;
    }

    const text = warningText.trim();

    return text !== "" && ask !== null ? ask(text) : false;
}
