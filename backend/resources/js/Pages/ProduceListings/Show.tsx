import AuthenticatedLayout, { useTheme } from "@/Layouts/AuthenticatedLayout";
import BackLink from "@/Components/BackLink";
import { router } from "@inertiajs/react";
import { useState } from "react";

interface ListingDetail {
    uuid: string;
    product_name: string | null;
    quantity_remaining: number;
    unit_of_measure: string | null;
    photo_url: string | null;
    expires_at: string | null;
}

interface Props {
    listing: ListingDetail;
}

export default function Show({ listing }: Props) {
    return (
        <AuthenticatedLayout title={listing.product_name ?? "Produce Listing"}>
            <BackLink fallbackHref="/produce-listings" />
            <ShowContent listing={listing} />
        </AuthenticatedLayout>
    );
}

function ShowContent({ listing }: Props) {
    const { dark } = useTheme();
    const [showOffer, setShowOffer] = useState(false);
    const [quantity, setQuantity] = useState("");
    const [amount, setAmount] = useState("");
    const [paymentMethod, setPaymentMethod] = useState("bank");
    const [message, setMessage] = useState("");
    const [submitting, setSubmitting] = useState(false);

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
        width: "100%",
    };

    const sendInterest = () => {
        setSubmitting(true);
        router.post(
            `/produce-listings/${listing.uuid}/interest`,
            { message: message || null },
            { preserveScroll: true, onFinish: () => setSubmitting(false) },
        );
    };

    const sendOffer = () => {
        if (!quantity || !amount) return;
        setSubmitting(true);
        router.post(
            `/produce-listings/${listing.uuid}/sales`,
            { quantity, amount, payment_method: paymentMethod },
            {
                preserveScroll: true,
                onFinish: () => setSubmitting(false),
                onSuccess: () => {
                    setShowOffer(false);
                    setQuantity("");
                    setAmount("");
                },
            },
        );
    };

    return (
        <div style={{ background: surface, border: `1px solid ${border}`, overflow: "hidden", maxWidth: "480px" }}>
            {listing.photo_url ? (
                <img src={listing.photo_url} alt={listing.product_name ?? ""} style={{ width: "100%", height: "220px", objectFit: "cover" }} />
            ) : (
                <div
                    style={{
                        width: "100%",
                        height: "220px",
                        background: placeholderBg,
                        display: "flex",
                        alignItems: "center",
                        justifyContent: "center",
                        color: textSecondary,
                    }}
                >
                    No photo
                </div>
            )}

            <div className="p-4">
                <h1 style={{ color: text, fontSize: "1.375rem", fontWeight: 700, margin: 0 }}>{listing.product_name}</h1>
                <p style={{ color: primary, fontWeight: 600, fontSize: "1.0625rem", margin: "6px 0" }}>
                    {listing.quantity_remaining} {listing.unit_of_measure} available
                </p>
                {listing.expires_at && (
                    <p style={{ color: textSecondary, fontSize: "0.9375rem", margin: "0 0 10px" }}>Expires {listing.expires_at}</p>
                )}

                <div className="flex gap-2 mt-3">
                    <button
                        onClick={sendInterest}
                        disabled={submitting}
                        style={{
                            flex: 1,
                            background: "transparent",
                            color: primary,
                            border: `1px solid ${primary}`,
                            fontWeight: 600,
                            fontSize: "0.9375rem",
                            padding: "8px 0",
                            cursor: submitting ? "not-allowed" : "pointer",
                            fontFamily: "inherit",
                        }}
                    >
                        I'm Interested
                    </button>
                    <button
                        onClick={() => setShowOffer((prev) => !prev)}
                        style={{
                            flex: 1,
                            background: primary,
                            color: "#FFFFFF",
                            fontWeight: 600,
                            fontSize: "0.9375rem",
                            padding: "8px 0",
                            border: "none",
                            cursor: "pointer",
                            fontFamily: "inherit",
                        }}
                    >
                        Make an offer
                    </button>
                </div>

                {showOffer && (
                    <div className="flex flex-col gap-2 mt-3" style={{ borderTop: `1px solid ${border}`, paddingTop: "10px" }}>
                        <div>
                            <label style={{ display: "block", fontSize: "0.8125rem", color: textSecondary, marginBottom: "2px" }}>
                                Quantity
                            </label>
                            <input type="number" min={0} value={quantity} onChange={(e) => setQuantity(e.target.value)} style={inputStyle} />
                        </div>
                        <div>
                            <label style={{ display: "block", fontSize: "0.8125rem", color: textSecondary, marginBottom: "2px" }}>
                                Your offer (cedis)
                            </label>
                            <input type="number" min={0} value={amount} onChange={(e) => setAmount(e.target.value)} style={inputStyle} />
                        </div>
                        <div>
                            <label style={{ display: "block", fontSize: "0.8125rem", color: textSecondary, marginBottom: "2px" }}>
                                Payment
                            </label>
                            <select value={paymentMethod} onChange={(e) => setPaymentMethod(e.target.value)} style={inputStyle}>
                                <option value="bank">Bank payment</option>
                                <option value="cod">Cash on delivery</option>
                            </select>
                        </div>
                        <button
                            onClick={sendOffer}
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
                            Send offer
                        </button>
                    </div>
                )}
            </div>
        </div>
    );
}
