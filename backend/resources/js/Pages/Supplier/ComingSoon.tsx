import { router } from "@inertiajs/react";

export default function ComingSoon() {
    return (
        <div style={{ padding: "48px 16px", textAlign: "center" }}>
            <p>Marketplace coming soon</p>
            <button onClick={() => router.post(route("logout"))}>Log out</button>
        </div>
    );
}
