export function csrfToken(): string {
    return document.querySelector<HTMLMetaElement>('meta[name="csrf-token"]')?.content ?? "";
}

// a redirect, 401 or 419 all mean the same thing: sign in again
export function isSessionEnded(response: Pick<Response, "type" | "status">): boolean {
    return response.type === "opaqueredirect" || response.status === 419 || response.status === 401;
}
