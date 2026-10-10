import { DATA_COST_TEXT } from "./dataCostText";

// what the farmer did with the dialog; "dismissed" is the page going away under it, not a choice
export type DataCostAnswer = "send" | "wait" | "dismissed";

let ask: ((text: string) => Promise<DataCostAnswer>) | null = null;
let waiting = false;

// "Wait" holds until the farmer comes back to the app; opening the app starts clear
if (typeof document !== "undefined") {
    document.addEventListener("visibilitychange", () => {
        if (document.visibilityState === "visible") {
            waiting = false;
        }
    });
}

export function setDataCostAsker(asker: ((text: string) => Promise<DataCostAnswer>) | null): void {
    ask = asker;
    waiting = false;
}

// asked once per report, before its first file is sent; anything but "Send now", or nothing to ask with,
// sends nothing and deletes nothing
export async function dataCostGateAllows(): Promise<boolean> {
    if (waiting || ask === null) {
        return false;
    }

    const answer = await ask(DATA_COST_TEXT.warning);

    waiting = answer === "wait";

    return answer === "send";
}
