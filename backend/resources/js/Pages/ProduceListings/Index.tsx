import AuthenticatedLayout, { useTheme } from "@/Layouts/AuthenticatedLayout";
import { Link, router } from "@inertiajs/react";

interface ListingCard {
    uuid: string;
    product_name: string | null;
    quantity_remaining: number;
    unit_of_measure: string | null;
    photo_url: string | null;
    expires_at: string | null;
}

interface PaginationLink {
    url: string | null;
    label: string;
    active: boolean;
}

interface Props {
    listings: { data: ListingCard[]; links: PaginationLink[] };
}

export default function Index({ listings }: Props) {
    return (
        <AuthenticatedLayout title="Produce Listings">
            <IndexContent listings={listings} />
        </AuthenticatedLayout>
    );
}

function IndexContent({ listings }: Props) {
    const { dark } = useTheme();
    const surface = dark ? "#1F2937" : "#FFFFFF";
    const border = dark ? "#374151" : "#E5E7EB";
    const text = dark ? "#F9FAFB" : "#111827";
    const textSecondary = dark ? "#9CA3AF" : "#6B7280";
    const placeholderBg = dark ? "#111827" : "#F3F4F6";
    const primary = "#1D9E75";

    const visit = (url: string | null) => {
        if (!url) return;
        router.visit(url, { preserveScroll: true });
    };

    return (
        <>
            <h1 style={{ color: text, fontSize: "1.375rem", fontWeight: 700, margin: "0 0 16px" }}>Produce For Sale</h1>

            {listings.data.length === 0 && (
                <div
                    className="px-6 py-10 text-center"
                    style={{ background: surface, border: `1px solid ${border}`, color: textSecondary, fontSize: "1.125rem" }}
                >
                    Nothing listed for sale right now.
                </div>
            )}

            <div className="grid gap-4" style={{ gridTemplateColumns: "repeat(auto-fill, minmax(220px, 1fr))" }}>
                {listings.data.map((listing) => (
                    <Link
                        key={listing.uuid}
                        href={`/produce-listings/${listing.uuid}`}
                        style={{ background: surface, border: `1px solid ${border}`, overflow: "hidden", display: "block" }}
                    >
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
                            <p style={{ color: primary, fontWeight: 600, fontSize: "0.9375rem", margin: "4px 0 0" }}>
                                {listing.quantity_remaining} {listing.unit_of_measure} available
                            </p>
                        </div>
                    </Link>
                ))}
            </div>

            {listings.links.length > 3 && (
                <div className="flex flex-wrap gap-2 mt-5">
                    {listings.links.map((link) => (
                        <button
                            key={link.label}
                            onClick={() => visit(link.url)}
                            disabled={!link.url}
                            dangerouslySetInnerHTML={{ __html: link.label }}
                            style={{
                                padding: "8px 14px",
                                fontSize: "1rem",
                                border: `1px solid ${link.active ? primary : border}`,
                                background: link.active ? primary : surface,
                                color: link.active ? "#FFFFFF" : link.url ? text : textSecondary,
                                cursor: link.url ? "pointer" : "not-allowed",
                                fontFamily: "inherit",
                            }}
                        />
                    ))}
                </div>
            )}
        </>
    );
}
