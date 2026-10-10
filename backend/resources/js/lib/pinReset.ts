// Asking the server to text a code to the signed-in user's own number, and checking it. This needs a
// connection and a live session; anything that goes wrong on the way reads as "failed".

export type ResetSend = "sent" | "failed";
export type ResetConfirm = "ok" | "wrong" | "expired" | "too_many" | "failed";

const ANSWERS: ResetConfirm[] = ["wrong", "expired", "too_many"];

function post(url: string, body?: object): Promise<Response> {
    const token = document.querySelector<HTMLMetaElement>('meta[name="csrf-token"]')?.content ?? "";

    return fetch(url, {
        method: "POST",
        redirect: "manual",
        headers: {
            Accept: "application/json",
            "Content-Type": "application/json",
            "X-CSRF-TOKEN": token,
        },
        body: JSON.stringify(body ?? {}),
    });
}

export async function requestResetCode(): Promise<ResetSend> {
    try {
        return (await post("/pin-reset/send")).ok ? "sent" : "failed";
    } catch {
        return "failed";
    }
}

export async function confirmResetCode(code: string): Promise<ResetConfirm> {
    try {
        const response = await post("/pin-reset/confirm", { code });

        if (response.ok) {
            return "ok";
        }

        if (response.status === 429) {
            return "too_many";
        }

        if (response.status === 422) {
            const status = (await response.json().catch(() => null))?.status as ResetConfirm | undefined;

            return status && ANSWERS.includes(status) ? status : "wrong";
        }

        return "failed";
    } catch {
        return "failed";
    }
}
