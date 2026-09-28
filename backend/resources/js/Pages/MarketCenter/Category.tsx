import AuthenticatedLayout, { useTheme } from "@/Layouts/AuthenticatedLayout";
import BackLink from "@/Components/BackLink";
import { Link, router } from "@inertiajs/react";
import { IconBuildingStore, IconPlant2 } from "@tabler/icons-react";

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

interface Props {
    category: { slug: string; name: string };
    items: Item[];
    total: number;
    perPage: number;
    currentPage: number;
    lastPage: number;
}

export default function Category(props: Props) {
    return (
        <AuthenticatedLayout title={props.category.name}>
            <BackLink fallbackHref="/market-center" />
            <CategoryContent {...props} />
        </AuthenticatedLayout>
    );
}

function CategoryContent({ category, items, total, currentPage, lastPage }: Props) {
    const { dark } = useTheme();
    const surface = dark ? "#1F2937" : "#FFFFFF";
    const border = dark ? "#374151" : "#E5E7EB";
    const text = dark ? "#F9FAFB" : "#111827";
    const textSecondary = dark ? "#9CA3AF" : "#6B7280";
    const placeholderBg = dark ? "#111827" : "#F3F4F6";
    const primary = "#1D9E75";

    const cedis = (minor: number) => `GHS ${(minor / 100).toFixed(2)}`;

    const goToPage = (page: number) => {
        router.get(`/market-center/${category.slug}`, { page }, { preserveScroll: true });
    };

    return (
        <>
            <h1 style={{ color: text, fontSize: "1.375rem", fontWeight: 700, margin: "0 0 4px" }}>{category.name}</h1>
            <p style={{ color: textSecondary, fontSize: "0.9375rem", margin: "0 0 16px" }}>{total} item{total === 1 ? "" : "s"}</p>

            {items.length === 0 && (
                <div className="px-6 py-10 text-center" style={{ background: surface, border: `1px solid ${border}`, color: textSecondary }}>
                    Nothing here yet.
                </div>
            )}

            <div className="grid gap-4" style={{ gridTemplateColumns: "repeat(auto-fill, minmax(220px, 1fr))" }}>
                {items.map((item) => (
                    <Link
                        key={`${item.kind}-${item.id}`}
                        href={item.link}
                        style={{ background: surface, border: `1px solid ${border}`, overflow: "hidden", display: "block" }}
                    >
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
                        </div>
                    </Link>
                ))}
            </div>

            {lastPage > 1 && (
                <div className="flex flex-wrap gap-2 mt-5">
                    {Array.from({ length: lastPage }, (_, i) => i + 1).map((page) => (
                        <button
                            key={page}
                            onClick={() => goToPage(page)}
                            style={{
                                padding: "8px 14px",
                                fontSize: "1rem",
                                border: `1px solid ${page === currentPage ? primary : border}`,
                                background: page === currentPage ? primary : surface,
                                color: page === currentPage ? "#FFFFFF" : text,
                                cursor: "pointer",
                                fontFamily: "inherit",
                            }}
                        >
                            {page}
                        </button>
                    ))}
                </div>
            )}
        </>
    );
}
