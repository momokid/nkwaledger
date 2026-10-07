import { usePage } from "@inertiajs/react";
import { useEffect, useState } from "react";
import Button from "@/Components/Button";
import { useTheme } from "@/Layouts/AuthenticatedLayout";
import { GENERIC_ERROR } from "@/lib/healthReport";
import { shortDate } from "@/lib/format";
import { deleteStuck, listStuckSummaries, retryStuck, StuckRow } from "@/lib/offlineStore";
import { OFFLINE_SYNC_RAN_EVENT, syncOnce } from "@/lib/offlineSync";
import { RECORDS_TEXT } from "@/lib/recordsText";
import { csrfToken } from "@/lib/syncHttp";

export interface FlaggedRow {
    uuid: string;
    kind: "record" | "health_report";
    status: "needs_fixing" | "held" | "rejected";
    reason: string | null;
    event_date: string;
    template: string | null;
    amount: string | null;
    description: string | null;
}

interface Props {
    flagged: FlaggedRow[];
    farmerId: string;
}

// records that are not through yet: stuck on this phone (Retry, Delete), needing a fix, or waiting for a check (read only)
export default function RecordsAttention({ flagged = [], farmerId }: Props) {
    const { dark } = useTheme();
    const { auth } = usePage().props as unknown as { auth?: { user?: { id: number } | null } };
    const currentUser = auth?.user ? String(auth.user.id) : null;

    const [stuck, setStuck] = useState<StuckRow[]>([]);
    const [asking, setAsking] = useState<string | null>(null);
    const [failed, setFailed] = useState(false);
    const [dismissed, setDismissed] = useState<string[]>([]);
    const [online, setOnline] = useState(typeof navigator === "undefined" ? true : navigator.onLine);

    useEffect(() => {
        const sync = () => setOnline(navigator.onLine);

        window.addEventListener("online", sync);
        window.addEventListener("offline", sync);

        return () => {
            window.removeEventListener("online", sync);
            window.removeEventListener("offline", sync);
        };
    }, []);

    const refresh = () => {
        void listStuckSummaries(currentUser)
            .then((rows) => setStuck(rows.filter((row) => row.summary === null || row.summary.farmer === farmerId)))
            .catch(() => {});
    };

    useEffect(() => {
        refresh();

        window.addEventListener(OFFLINE_SYNC_RAN_EVENT, refresh);

        return () => window.removeEventListener(OFFLINE_SYNC_RAN_EVENT, refresh);
    }, [currentUser, farmerId]);

    const retry = async (id: string) => {
        setFailed(false);

        try {
            await retryStuck(id);
        } catch {
            setFailed(true);

            return;
        }

        refresh();
        void syncOnce(currentUser);
    };

    const remove = async (id: string) => {
        setFailed(false);
        setAsking(null);

        try {
            await deleteStuck(id);
        } catch {
            setFailed(true);

            return;
        }

        refresh();
    };

    // saved on the server, so the record stays hidden on every device
    const dismiss = async (uuid: string) => {
        setFailed(false);

        try {
            const response = await fetch(`/my-records/rejected/${uuid}/dismiss`, {
                method: "POST",
                redirect: "manual",
                headers: { Accept: "application/json", "X-CSRF-TOKEN": csrfToken() },
            });

            if (!response.ok) throw new Error("not dismissed");
        } catch {
            setFailed(true);

            return;
        }

        setDismissed((all) => [...all, uuid]);
    };

    const needFix = flagged.filter((row) => row.status === "needs_fixing");
    const rejected = flagged.filter((row) => row.status === "rejected" && !dismissed.includes(row.uuid));
    const held = flagged.filter((row) => row.status === "held");

    if (stuck.length + needFix.length + held.length + rejected.length === 0 && !failed) return null;

    const surface = dark ? "#1F2937" : "#FFFFFF";
    const border = dark ? "#374151" : "#E5E7EB";
    const text = dark ? "#F9FAFB" : "#111827";
    const textSecondary = dark ? "#9CA3AF" : "#6B7280";
    const warnBg = dark ? "rgba(180,83,9,0.15)" : "#FEF3C7";

    const group = (heading: string, rows: React.ReactNode[]) =>
        rows.length === 0 ? null : (
            <section className="mb-4 p-4" style={{ background: surface, border: `1px solid ${border}` }}>
                <h2 style={{ fontSize: "1.125rem", fontWeight: 700, color: text, margin: 0 }}>{heading}</h2>
                {rows}
            </section>
        );

    const details = (parts: Array<string | null | undefined>) => (
        <p style={{ color: textSecondary, fontSize: "0.9375rem", marginTop: "4px" }}>{parts.filter(Boolean).join(" · ")}</p>
    );

    const flaggedDetails = (row: FlaggedRow) =>
        details([row.template, row.amount ? `Amount: ${row.amount}` : null, row.description, shortDate(row.event_date)]);

    const stuckDetails = (row: StuckRow) =>
        row.summary
            ? details([row.summary.description, row.summary.amount ? `Amount: ${row.summary.amount}` : null, shortDate(row.summary.event_date)])
            : null;

    return (
        <div className="mb-4">
            {failed && (
                <div className="mb-3 p-3" role="alert" style={{ background: warnBg, color: "#B45309", fontSize: "1.0625rem" }}>
                    {GENERIC_ERROR}
                </div>
            )}

            {group(
                RECORDS_TEXT.stuckHeading,
                stuck.map((row) => (
                    <div key={row.id} className="mt-3" style={{ borderTop: `1px solid ${border}`, paddingTop: "12px" }}>
                        <p style={{ color: "#B45309", margin: 0, fontSize: "1.0625rem" }}>{RECORDS_TEXT.stuck}</p>
                        {stuckDetails(row)}
                        <div className="flex mt-2" style={{ gap: "10px" }}>
                            <Button type="button" size="small" onClick={() => void retry(row.id)}>
                                {RECORDS_TEXT.retry}
                            </Button>
                            <Button type="button" size="small" look="danger" onClick={() => setAsking(row.id)}>
                                {RECORDS_TEXT.delete}
                            </Button>
                        </div>
                    </div>
                )),
            )}

            {group(
                RECORDS_TEXT.fixHeading,
                needFix.map((row) => (
                    <div key={row.uuid} className="mt-3" style={{ borderTop: `1px solid ${border}`, paddingTop: "12px" }}>
                        <p style={{ color: text, margin: 0, fontSize: "1.0625rem" }}>{RECORDS_TEXT.fix}</p>
                        {row.reason && <p style={{ color: textSecondary, marginTop: "4px", fontSize: "1rem" }}>{row.reason}</p>}
                        {flaggedDetails(row)}
                    </div>
                )),
            )}

            {group(
                RECORDS_TEXT.rejectedHeading,
                rejected.map((row) => (
                    <div key={row.uuid} className="mt-3" style={{ borderTop: `1px solid ${border}`, paddingTop: "12px" }}>
                        <p style={{ color: text, margin: 0, fontSize: "1.0625rem" }}>{RECORDS_TEXT.rejected}</p>
                        {row.reason && <p style={{ color: textSecondary, marginTop: "4px", fontSize: "1rem" }}>{row.reason}</p>}
                        {flaggedDetails(row)}
                        <div className="mt-2">
                            <Button type="button" size="small" look="secondary" disabled={!online} onClick={() => void dismiss(row.uuid)}>
                                {RECORDS_TEXT.dismiss}
                            </Button>
                        </div>
                    </div>
                )),
            )}

            {group(
                RECORDS_TEXT.heldHeading,
                held.map((row) => (
                    <div key={row.uuid} className="mt-3" style={{ borderTop: `1px solid ${border}`, paddingTop: "12px" }}>
                        <p style={{ color: text, margin: 0, fontSize: "1.0625rem" }}>{RECORDS_TEXT.held}</p>
                        {flaggedDetails(row)}
                    </div>
                )),
            )}

            {asking !== null && (
                <div
                    role="dialog"
                    aria-modal="true"
                    style={{ position: "fixed", inset: 0, zIndex: 60, display: "flex", alignItems: "center", justifyContent: "center", padding: "16px", background: "rgba(17,24,39,0.6)" }}
                >
                    <div style={{ width: "100%", maxWidth: "420px", padding: "24px", background: surface, border: `1px solid ${border}` }}>
                        <p style={{ fontSize: "1.125rem", color: text, margin: 0 }}>{RECORDS_TEXT.confirmDelete}</p>
                        <div className="flex mt-5" style={{ gap: "12px", flexWrap: "wrap" }}>
                            <Button type="button" look="danger" onClick={() => void remove(asking)}>
                                {RECORDS_TEXT.delete}
                            </Button>
                            <Button type="button" look="secondary" onClick={() => setAsking(null)}>
                                {RECORDS_TEXT.keep}
                            </Button>
                        </div>
                    </div>
                </div>
            )}
        </div>
    );
}
