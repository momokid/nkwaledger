import { Link, usePage } from "@inertiajs/react";
import { useEffect, useState } from "react";
import { useTheme } from "@/Layouts/AuthenticatedLayout";
import { stuckCount } from "@/lib/offlineStore";
import { OFFLINE_SYNC_RAN_EVENT } from "@/lib/offlineSync";
import { RECORDS_TEXT } from "@/lib/recordsText";

// how many of this farmer's records are stuck on the phone; hidden when there are none
export default function UnsentRecordsCount() {
    const { dark } = useTheme();
    const { auth } = usePage().props as unknown as { auth?: { user?: { id: number } | null } };
    const currentUser = auth?.user ? String(auth.user.id) : null;
    const [count, setCount] = useState(0);

    useEffect(() => {
        const refresh = () => void stuckCount(currentUser).then(setCount).catch(() => {});

        refresh();

        window.addEventListener(OFFLINE_SYNC_RAN_EVENT, refresh);

        return () => window.removeEventListener(OFFLINE_SYNC_RAN_EVENT, refresh);
    }, [currentUser]);

    if (count === 0) return null;

    return (
        <Link
            href="/my-records"
            className="block mb-4 p-4"
            style={{
                background: dark ? "rgba(180,83,9,0.15)" : "#FEF3C7",
                border: `1px solid ${dark ? "#374151" : "#E5E7EB"}`,
                color: dark ? "#F9FAFB" : "#111827",
                fontSize: "1.0625rem",
            }}
        >
            <span>{RECORDS_TEXT.unsentCount}</span>{" "}
            <strong data-testid="unsent-count" style={{ color: "#B45309" }}>
                {count}
            </strong>
        </Link>
    );
}
