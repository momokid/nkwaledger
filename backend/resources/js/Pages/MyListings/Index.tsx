import AuthenticatedLayout, { useTheme } from "@/Layouts/AuthenticatedLayout";
import { router } from "@inertiajs/react";
import { useState } from "react";

interface Listing {
    uuid: string;
    status: string;
    product_name: string | null;
    quantity_listed: number;
    quantity_remaining: number;
    unit_of_measure: string | null;
    photo_url: string | null;
    expires_at: string | null;
    farmer_agreed_at: string | null;
    is_agent_posted_draft: boolean;
}

interface StockBatch {
    id: number;
    label: string | null;
    current_quantity: number;
    unit_of_measure: string | null;
}

interface SettlementAccount {
    id: number;
    name: string;
}

interface Props {
    farmer: { id: string };
    listings: Listing[];
    stockBatches: StockBatch[];
    settlementAccounts: SettlementAccount[];
}

export default function Index(props: Props) {
    return (
        <AuthenticatedLayout title="My Listings">
            <IndexContent {...props} />
        </AuthenticatedLayout>
    );
}

function IndexContent({ listings, stockBatches, settlementAccounts }: Props) {
    const { dark } = useTheme();
    const [showCreate, setShowCreate] = useState(false);
    const [stockId, setStockId] = useState(stockBatches[0] ? String(stockBatches[0].id) : "");
    const [quantity, setQuantity] = useState("");
    const [cropExpiryDays, setCropExpiryDays] = useState("");
    const [submitting, setSubmitting] = useState(false);

    const [saleFor, setSaleFor] = useState<string | null>(null);
    const [saleAmount, setSaleAmount] = useState("");
    const [saleQuantity, setSaleQuantity] = useState("");
    const [settlementAccountId, setSettlementAccountId] = useState(
        settlementAccounts[0] ? String(settlementAccounts[0].id) : "",
    );

    const surface = dark ? "#1F2937" : "#FFFFFF";
    const border = dark ? "#374151" : "#E5E7EB";
    const text = dark ? "#F9FAFB" : "#111827";
    const textSecondary = dark ? "#9CA3AF" : "#6B7280";
    const placeholderBg = dark ? "#111827" : "#F3F4F6";
    const primary = "#1D9E75";

    const inputStyle = {
        border: `1px solid ${dark ? "#4B5563" : "#9CA3AF"}`,
        background: dark ? "#111827" : "#FFFFFF",
        color: text,
        padding: "8px 10px",
        fontSize: "0.9375rem",
        outline: "none",
        fontFamily: "inherit",
    };

    const createListing = () => {
        if (!stockId || !quantity) return;
        setSubmitting(true);
        router.post(
            "/my-listings",
            {
                farm_unit_stock_id: Number(stockId),
                quantity,
                crop_expiry_days: cropExpiryDays || null,
            },
            {
                preserveScroll: true,
                onFinish: () => setSubmitting(false),
                onSuccess: () => {
                    setShowCreate(false);
                    setQuantity("");
                    setCropExpiryDays("");
                },
            },
        );
    };

    const agree = (uuid: string) => router.post(`/my-listings/${uuid}/agree`, {}, { preserveScroll: true });
    const withdraw = (uuid: string) => router.post(`/my-listings/${uuid}/withdraw`, {}, { preserveScroll: true });

    const markSold = (uuid: string) => {
        if (!saleAmount || !saleQuantity || !settlementAccountId) return;
        setSubmitting(true);
        router.post(
            `/my-listings/${uuid}/mark-sold`,
            { amount: saleAmount, quantity: saleQuantity, settlement_account_id: Number(settlementAccountId) },
            {
                preserveScroll: true,
                onFinish: () => setSubmitting(false),
                onSuccess: () => {
                    setSaleFor(null);
                    setSaleAmount("");
                    setSaleQuantity("");
                },
            },
        );
    };

    return (
        <>
            <div className="flex items-center justify-between mb-5">
                <h1 style={{ color: text, fontSize: "1.375rem", fontWeight: 700, margin: 0 }}>My Listings</h1>
                <button
                    onClick={() => setShowCreate((prev) => !prev)}
                    style={{
                        background: primary,
                        color: "#FFFFFF",
                        fontWeight: 600,
                        fontSize: "0.9375rem",
                        padding: "8px 16px",
                        border: "none",
                        cursor: "pointer",
                        fontFamily: "inherit",
                    }}
                >
                    Post a listing
                </button>
            </div>

            {showCreate && (
                <div style={{ background: surface, border: `1px solid ${border}`, padding: "16px", marginBottom: "20px" }}>
                    <div className="flex flex-col gap-2" style={{ maxWidth: "360px" }}>
                        <div>
                            <label style={{ display: "block", fontSize: "0.8125rem", color: textSecondary, marginBottom: "2px" }}>
                                Which batch
                            </label>
                            <select value={stockId} onChange={(e) => setStockId(e.target.value)} style={{ ...inputStyle, width: "100%" }}>
                                {stockBatches.map((batch) => (
                                    <option key={batch.id} value={batch.id}>
                                        {batch.label} - {batch.current_quantity} {batch.unit_of_measure} on hand
                                    </option>
                                ))}
                            </select>
                        </div>
                        <div>
                            <label style={{ display: "block", fontSize: "0.8125rem", color: textSecondary, marginBottom: "2px" }}>
                                Quantity to list
                            </label>
                            <input
                                type="number"
                                min={0}
                                value={quantity}
                                onChange={(e) => setQuantity(e.target.value)}
                                style={{ ...inputStyle, width: "100%" }}
                            />
                        </div>
                        <div>
                            <label style={{ display: "block", fontSize: "0.8125rem", color: textSecondary, marginBottom: "2px" }}>
                                Expires after (days) - crops only
                            </label>
                            <input
                                type="number"
                                min={1}
                                value={cropExpiryDays}
                                onChange={(e) => setCropExpiryDays(e.target.value)}
                                style={{ ...inputStyle, width: "100%" }}
                            />
                        </div>
                        <button
                            onClick={createListing}
                            disabled={submitting}
                            style={{
                                background: primary,
                                color: "#FFFFFF",
                                fontWeight: 600,
                                fontSize: "0.9375rem",
                                padding: "8px 0",
                                border: "none",
                                cursor: submitting ? "not-allowed" : "pointer",
                                fontFamily: "inherit",
                            }}
                        >
                            Post
                        </button>
                    </div>
                </div>
            )}

            {listings.length === 0 && (
                <div
                    className="px-6 py-10 text-center"
                    style={{ background: surface, border: `1px solid ${border}`, color: textSecondary, fontSize: "1.125rem" }}
                >
                    You have not listed any produce yet.
                </div>
            )}

            <div className="grid gap-4" style={{ gridTemplateColumns: "repeat(auto-fill, minmax(240px, 1fr))" }}>
                {listings.map((listing) => (
                    <div key={listing.uuid} style={{ background: surface, border: `1px solid ${border}`, overflow: "hidden" }}>
                        {listing.photo_url ? (
                            <img
                                src={listing.photo_url}
                                alt={listing.product_name ?? ""}
                                style={{ width: "100%", height: "140px", objectFit: "cover", display: "block" }}
                            />
                        ) : (
                            <div
                                style={{
                                    width: "100%",
                                    height: "140px",
                                    background: placeholderBg,
                                    display: "flex",
                                    alignItems: "center",
                                    justifyContent: "center",
                                    color: textSecondary,
                                    fontSize: "0.875rem",
                                }}
                            >
                                No photo
                            </div>
                        )}

                        <div className="p-3">
                            <h3 style={{ color: text, fontSize: "1.0625rem", fontWeight: 700, margin: 0 }}>{listing.product_name}</h3>
                            <p style={{ color: textSecondary, fontSize: "0.9375rem", margin: "4px 0" }}>
                                {listing.quantity_remaining} of {listing.quantity_listed} {listing.unit_of_measure} left
                            </p>
                            <p style={{ color: textSecondary, fontSize: "0.8125rem", margin: "0 0 8px" }}>
                                {listing.status}
                                {listing.expires_at && ` - expires ${listing.expires_at}`}
                            </p>

                            {listing.is_agent_posted_draft && (
                                <button
                                    onClick={() => agree(listing.uuid)}
                                    style={{
                                        width: "100%",
                                        background: primary,
                                        color: "#FFFFFF",
                                        fontWeight: 600,
                                        fontSize: "0.9375rem",
                                        padding: "8px 0",
                                        border: "none",
                                        cursor: "pointer",
                                        fontFamily: "inherit",
                                        marginBottom: "6px",
                                    }}
                                >
                                    Agree and go live
                                </button>
                            )}

                            {listing.status === "active" && (
                                <div className="flex gap-2 mb-2">
                                    <button
                                        onClick={() => {
                                            setSaleFor(listing.uuid);
                                            setSaleAmount("");
                                            setSaleQuantity("");
                                        }}
                                        style={{
                                            flex: 1,
                                            background: primary,
                                            color: "#FFFFFF",
                                            fontWeight: 600,
                                            fontSize: "0.875rem",
                                            padding: "6px 0",
                                            border: "none",
                                            cursor: "pointer",
                                            fontFamily: "inherit",
                                        }}
                                    >
                                        Mark Sold
                                    </button>
                                    <button
                                        onClick={() => withdraw(listing.uuid)}
                                        style={{
                                            flex: 1,
                                            background: "transparent",
                                            color: textSecondary,
                                            border: `1px solid ${border}`,
                                            fontSize: "0.875rem",
                                            padding: "6px 0",
                                            cursor: "pointer",
                                            fontFamily: "inherit",
                                        }}
                                    >
                                        Withdraw
                                    </button>
                                </div>
                            )}

                            {saleFor === listing.uuid && (
                                <div className="flex flex-col gap-2" style={{ borderTop: `1px solid ${border}`, paddingTop: "8px" }}>
                                    <input
                                        type="number"
                                        min={0}
                                        placeholder="Quantity sold"
                                        value={saleQuantity}
                                        onChange={(e) => setSaleQuantity(e.target.value)}
                                        style={inputStyle}
                                    />
                                    <input
                                        type="number"
                                        min={0}
                                        placeholder="Amount (cedis)"
                                        value={saleAmount}
                                        onChange={(e) => setSaleAmount(e.target.value)}
                                        style={inputStyle}
                                    />
                                    <select
                                        value={settlementAccountId}
                                        onChange={(e) => setSettlementAccountId(e.target.value)}
                                        style={inputStyle}
                                    >
                                        {settlementAccounts.map((account) => (
                                            <option key={account.id} value={account.id}>
                                                {account.name}
                                            </option>
                                        ))}
                                    </select>
                                    <div className="flex gap-2">
                                        <button
                                            onClick={() => markSold(listing.uuid)}
                                            disabled={submitting}
                                            style={{
                                                flex: 1,
                                                background: primary,
                                                color: "#FFFFFF",
                                                fontWeight: 600,
                                                fontSize: "0.875rem",
                                                padding: "6px 0",
                                                border: "none",
                                                cursor: submitting ? "not-allowed" : "pointer",
                                                fontFamily: "inherit",
                                            }}
                                        >
                                            Save sale
                                        </button>
                                        <button
                                            onClick={() => setSaleFor(null)}
                                            style={{
                                                background: "transparent",
                                                color: textSecondary,
                                                border: "none",
                                                fontSize: "0.875rem",
                                                cursor: "pointer",
                                                fontFamily: "inherit",
                                            }}
                                        >
                                            Cancel
                                        </button>
                                    </div>
                                </div>
                            )}
                        </div>
                    </div>
                ))}
            </div>
        </>
    );
}
