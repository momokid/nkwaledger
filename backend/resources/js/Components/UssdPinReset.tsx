import { useState } from "react";
import Button from "@/Components/Button";
import { useTheme } from "@/Layouts/AuthenticatedLayout";
import { csrfToken } from "@/lib/syncHttp";

interface Props {
    // where the temporary PIN is asked for
    url: string;
    // who is on the other end of the phone
    person?: string;
}

// the farmer's own agent, or an admin for an agent, sets a temporary USSD PIN once they are sure who is asking.
// The PIN is shown here once, in place
export default function UssdPinReset({ url, person = "farmer" }: Props) {
    const { dark } = useTheme();
    const text = dark ? "#F9FAFB" : "#111827";
    const textSecondary = dark ? "#9CA3AF" : "#6B7280";

    const [asking, setAsking] = useState(false);
    const [busy, setBusy] = useState(false);
    const [pin, setPin] = useState<string | null>(null);
    const [failed, setFailed] = useState(false);

    const set = async () => {
        setBusy(true);
        setFailed(false);

        try {
            const response = await fetch(url, {
                method: "POST",
                headers: { Accept: "application/json", "X-CSRF-TOKEN": csrfToken() },
            });

            if (response.ok) {
                setPin((await response.json()).pin);
                setAsking(false);
            } else {
                setFailed(true);
            }
        } catch {
            setFailed(true);
        } finally {
            setBusy(false);
        }
    };

    return (
        <div className="mt-4">
            {pin === null && !asking && (
                <Button type="button" look="secondary" onClick={() => setAsking(true)}>
                    Set a new USSD PIN
                </Button>
            )}

            {asking && (
                <div role="alertdialog" aria-label="Set a new USSD PIN?">
                    <p style={{ color: text, fontSize: "1.0625rem" }}>Have you checked that you are speaking to the {person}?</p>
                    <div className="mt-2 flex gap-3">
                        <Button type="button" busy={busy} busyLabel="Setting..." onClick={set}>
                            Yes, set it
                        </Button>
                        <Button type="button" look="secondary" onClick={() => setAsking(false)}>
                            Cancel
                        </Button>
                    </div>
                </div>
            )}

            {failed && <p style={{ color: "#B91C1C", fontSize: "0.9375rem", marginTop: "8px" }}>The PIN could not be set. Please try again.</p>}

            {pin !== null && (
                <div>
                    <p style={{ color: text, fontSize: "1.25rem", fontWeight: 700 }}>Temporary PIN: {pin}</p>
                    <p style={{ color: textSecondary, fontSize: "1rem" }}>
                        Give it to the {person} only after checking who they are. They must set a new PIN the next time they dial.
                    </p>
                </div>
            )}
        </div>
    );
}
