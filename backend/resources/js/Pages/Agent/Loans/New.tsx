import { router } from "@inertiajs/react";
import { useEffect, useState } from "react";
import Button from "@/Components/Button";
import AuthenticatedLayout, { useTheme } from "@/Layouts/AuthenticatedLayout";
import { csrfToken } from "@/lib/syncHttp";

interface Found {
    id: string;
    name: string;
    community: string | null;
}

function NewContent() {
    const { dark } = useTheme();
    const text = dark ? "#F9FAFB" : "#111827";
    const border = dark ? "#374151" : "#E5E7EB";
    const inputBorder = dark ? "#4B5563" : "#9CA3AF";
    const inputBg = dark ? "#111827" : "#FFFFFF";

    const [query, setQuery] = useState("");
    const [found, setFound] = useState<Found[] | null>(null);
    const [picked, setPicked] = useState<Found | null>(null);

    // only what the agent types is searched; the server returns the agent's own farmers alone
    useEffect(() => {
        setFound(null);

        if (picked !== null || query.trim().length < 2) {
            return;
        }

        const controller = new AbortController();
        const timer = setTimeout(async () => {
            try {
                const response = await fetch("/agent/loans/farmer-search", {
                    method: "POST",
                    signal: controller.signal,
                    headers: { Accept: "application/json", "Content-Type": "application/json", "X-CSRF-TOKEN": csrfToken() },
                    body: JSON.stringify({ q: query }),
                });

                setFound(response.ok ? await response.json() : null);
            } catch {
                setFound(null);
            }
        }, 250);

        return () => {
            clearTimeout(timer);
            controller.abort();
        };
    }, [query, picked]);

    return (
        <div className="p-6" style={{ background: dark ? "#1F2937" : "#FFFFFF", border: `1px solid ${border}`, maxWidth: "560px" }}>
            <h1 style={{ fontSize: "1.375rem", fontWeight: 700, color: text, marginBottom: "16px" }}>New Loan</h1>
            <label htmlFor="farmer_search" style={{ display: "block", fontSize: "1.0625rem", fontWeight: 600, color: text, marginBottom: "6px" }}>
                Farmer
            </label>
            <input
                id="farmer_search"
                type="text"
                autoComplete="off"
                value={query}
                onChange={(event) => {
                    setQuery(event.target.value);
                    setPicked(null);
                }}
                style={{ width: "100%", padding: "10px 12px", border: `1px solid ${inputBorder}`, background: inputBg, color: text, fontSize: "1.125rem" }}
            />
            {found !== null && found.length > 0 && (
                <ul role="listbox" style={{ border: `1px solid ${inputBorder}`, background: inputBg }}>
                    {found.map((farmer) => (
                        <li key={farmer.id}>
                            <button
                                type="button"
                                role="option"
                                aria-selected={false}
                                onClick={() => {
                                    setPicked(farmer);
                                    setQuery(farmer.name);
                                    setFound(null);
                                }}
                                style={{ display: "flex", justifyContent: "space-between", width: "100%", minHeight: "48px", textAlign: "left", padding: "12px 16px", fontSize: "1.0625rem", color: text }}
                            >
                                <span>{farmer.name}</span>
                                <span style={{ fontSize: "0.9375rem", opacity: 0.75 }}>{farmer.community}</span>
                            </button>
                        </li>
                    ))}
                </ul>
            )}
            {found !== null && found.length === 0 && <p style={{ fontSize: "0.9375rem", color: "#B91C1C", marginTop: "4px" }}>No farmer found</p>}
            {picked !== null && (
                <div className="mt-4 flex flex-wrap gap-3">
                    <Button type="button" onClick={() => router.visit(`/agent/farmers/${picked.id}/loans/create`)}>
                        Add a loan
                    </Button>
                    <Button type="button" look="secondary" onClick={() => router.visit(`/agent/farmers/${picked.id}/loans/existing/create`)}>
                        Add an existing loan for the farmer
                    </Button>
                </div>
            )}
        </div>
    );
}

export default function New() {
    return (
        <AuthenticatedLayout title="New Loan">
            <NewContent />
        </AuthenticatedLayout>
    );
}
