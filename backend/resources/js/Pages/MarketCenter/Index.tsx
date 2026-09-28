import AuthenticatedLayout, { useTheme } from "@/Layouts/AuthenticatedLayout";
import { Link, router } from "@inertiajs/react";
import { IconBuildingStore, IconPlant2 } from "@tabler/icons-react";
import { FormEvent, useState } from "react";

interface Item {
    kind: "kiosk_product" | "produce_listing";
    id: number | string;
    name: string | null;
    price_minor: number | null;
    unit: string | null;
    photo_url: string | null;
    link: string;
    seller_label: string | null;
    distance_label: string | null;
}

interface Row {
    category: { slug: string; name: string };
    items: Item[];
    has_more: boolean;
}

interface Announcement {
    title: string | null;
    message: string;
    link: string | null;
}

interface Props {
    announcement: Announcement | null;
    query: string | null;
    searchResults: Item[] | null;
    rows: Row[] | null;
}

export default function Index(props: Props) {
    return (
        <AuthenticatedLayout title="Market Center">
            <IndexContent {...props} />
        </AuthenticatedLayout>
    );
}

function IndexContent({ announcement, query, searchResults, rows }: Props) {
    const { dark } = useTheme();
    const [term, setTerm] = useState(query ?? "");

    const surface = dark ? "#1F2937" : "#FFFFFF";
    const border = dark ? "#374151" : "#E5E7EB";
    const text = dark ? "#F9FAFB" : "#111827";
    const textSecondary = dark ? "#9CA3AF" : "#6B7280";
    const placeholderBg = dark ? "#111827" : "#F3F4F6";
    const primary = "#1D9E75";
    const announceBg = dark ? "rgba(29,158,117,0.15)" : "#EAF5F0";

    const cedis = (minor: number) => `GHS ${(minor / 100).toFixed(2)}`;

    const submitSearch = (event: FormEvent) => {
        event.preventDefault();
        router.get("/market-center", term.trim() ? { q: term.trim() } : {}, { preserveScroll: true });
    };

    const ItemCard = ({ item }: { item: Item }) => (
        <Link href={item.link} style={{ background: surface, border: `1px solid ${border}`, overflow: "hidden", display: "block" }}>
            <div style={{ position: "relative" }}>
                {item.photo_url ? (
                    <img src={item.photo_url} alt={item.name ?? ""} style={{ width: "100%", height: "140px", objectFit: "cover", display: "block" }} />
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
                <div
                    title={item.kind === "kiosk_product" ? "Kiosk" : "Farmer produce"}
                    style={{
                        position: "absolute",
                        top: "8px",
                        left: "8px",
                        background: "rgba(0,0,0,0.55)",
                        borderRadius: "999px",
                        padding: "4px",
                        display: "flex",
                    }}
                >
                    {item.kind === "kiosk_product" ? (
                        <IconBuildingStore size={16} color="#FFFFFF" />
                    ) : (
                        <IconPlant2 size={16} color="#FFFFFF" />
                    )}
                </div>
            </div>
            <div className="p-3">
                <h3 style={{ color: text, fontSize: "1.0625rem", fontWeight: 700, margin: 0 }}>{item.name}</h3>
                {item.price_minor !== null ? (
                    <p style={{ color: primary, fontWeight: 700, fontSize: "1.0625rem", margin: "4px 0" }}>
                        {cedis(item.price_minor)}
                        {item.unit && <span style={{ color: textSecondary, fontWeight: 400 }}> / {item.unit}</span>}
                    </p>
                ) : (
                    <p style={{ color: textSecondary, fontSize: "0.9375rem", margin: "4px 0" }}>{item.unit ?? "Contact to buy"}</p>
                )}
                {item.seller_label && (
                    <p style={{ color: textSecondary, fontSize: "0.875rem", margin: 0 }}>
                        {item.seller_label}
                        {item.distance_label && ` · ${item.distance_label}`}
                    </p>
                )}
            </div>
        </Link>
    );

    return (
        <>
            {announcement && (
                <div style={{ background: announceBg, border: `1px solid ${primary}`, padding: "14px 16px", marginBottom: "20px" }}>
                    {announcement.title && (
                        <p style={{ color: primary, fontWeight: 700, fontSize: "1rem", margin: "0 0 4px" }}>{announcement.title}</p>
                    )}
                    <p style={{ color: text, fontSize: "0.9375rem", margin: 0 }}>
                        {announcement.message}
                        {announcement.link && (
                            <>
                                {" "}
                                <a href={announcement.link} style={{ color: primary, fontWeight: 600 }}>
                                    Learn more
                                </a>
                            </>
                        )}
                    </p>
                </div>
            )}

            <form onSubmit={submitSearch} className="mb-6 flex gap-2" style={{ maxWidth: "480px" }}>
                <input
                    value={term}
                    onChange={(e) => setTerm(e.target.value)}
                    placeholder="Search products or produce"
                    style={{
                        flex: 1,
                        border: `1px solid ${dark ? "#4B5563" : "#9CA3AF"}`,
                        background: dark ? "#111827" : "#FFFFFF",
                        color: text,
                        padding: "9px 12px",
                        fontSize: "0.9375rem",
                        outline: "none",
                        fontFamily: "inherit",
                    }}
                />
                <button
                    type="submit"
                    style={{ background: primary, color: "#FFFFFF", fontWeight: 600, padding: "0 18px", border: "none", cursor: "pointer", fontFamily: "inherit" }}
                >
                    Search
                </button>
                {query && (
                    <button
                        type="button"
                        onClick={() => {
                            setTerm("");
                            router.get("/market-center", {}, { preserveScroll: true });
                        }}
                        style={{ background: "transparent", color: textSecondary, border: `1px solid ${border}`, padding: "0 14px", cursor: "pointer", fontFamily: "inherit" }}
                    >
                        Clear
                    </button>
                )}
            </form>

            {searchResults !== null ? (
                <>
                    <h2 style={{ color: text, fontSize: "1.125rem", fontWeight: 700, margin: "0 0 12px" }}>
                        {searchResults.length} result{searchResults.length === 1 ? "" : "s"} for &ldquo;{query}&rdquo;
                    </h2>
                    {searchResults.length === 0 && (
                        <div className="px-6 py-10 text-center" style={{ background: surface, border: `1px solid ${border}`, color: textSecondary }}>
                            Nothing matched that search.
                        </div>
                    )}
                    <div className="grid gap-4" style={{ gridTemplateColumns: "repeat(auto-fill, minmax(220px, 1fr))" }}>
                        {searchResults.map((item) => (
                            <ItemCard key={`${item.kind}-${item.id}`} item={item} />
                        ))}
                    </div>
                </>
            ) : (
                (rows ?? []).map((row) => (
                    <section key={row.category.slug} className="mb-8">
                        <div className="flex items-center justify-between mb-3">
                            <h2 style={{ color: text, fontSize: "1.125rem", fontWeight: 700, margin: 0 }}>{row.category.name}</h2>
                            {row.has_more && (
                                <Link href={`/market-center/${row.category.slug}`} style={{ color: primary, fontWeight: 600, fontSize: "0.9375rem" }}>
                                    Browse more →
                                </Link>
                            )}
                        </div>

                        {row.items.length === 0 ? (
                            <p style={{ color: textSecondary, fontSize: "0.9375rem" }}>Nothing here yet.</p>
                        ) : (
                            <div className="grid gap-4" style={{ gridTemplateColumns: "repeat(auto-fill, minmax(220px, 1fr))" }}>
                                {row.items.map((item) => (
                                    <ItemCard key={`${item.kind}-${item.id}`} item={item} />
                                ))}
                            </div>
                        )}
                    </section>
                ))
            )}
        </>
    );
}
