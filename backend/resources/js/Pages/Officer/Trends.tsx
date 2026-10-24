import AuthenticatedLayout, { useTheme } from "@/Layouts/AuthenticatedLayout";
import useAuthGuard from "@/hooks/useAuthGuard";
import { slashDate } from "@/lib/format";
import { Head, Link } from "@inertiajs/react";

interface Props {
    totals: { ninety: number; thirty: number };
    problems: { uuid: string; name: string; count: number }[];
    weeks: { week_start: string; count: number }[];
    communities: { name: string; count: number }[];
    field: "crops" | "livestock";
    basePath: string;
}

export default function Trends(props: Props) {
    useAuthGuard();

    return (
        <AuthenticatedLayout title={props.field === "crops" ? "Crops" : "Livestock"}>
            <Head title="Trends" />
            <Content {...props} />
        </AuthenticatedLayout>
    );
}

function Content({ totals, problems, weeks, communities, basePath }: Props) {
    const { dark } = useTheme();
    const surface = dark ? "#1F2937" : "#FFFFFF";
    const border = dark ? "#374151" : "#E5E7EB";
    const text = dark ? "#F9FAFB" : "#111827";
    const textSecondary = dark ? "#9CA3AF" : "#6B7280";
    const bar = "#1D9E75";
    const heading = { fontSize: "1.25rem", fontWeight: 700, color: text, margin: "24px 0 8px" } as const;
    const maxProblem = Math.max(1, ...problems.map((p) => p.count));
    const maxWeek = Math.max(1, ...weeks.map((w) => w.count));
    const maxCommunity = Math.max(1, ...communities.map((c) => c.count));

    return (
        <div className="p-6">
            <p style={{ fontSize: "1.625rem", fontWeight: 700, color: text }}>Trends, last 90 days</p>

            {totals.ninety === 0 ? (
                <p className="mt-4" style={{ color: textSecondary, fontSize: "1.0625rem" }}>
                    No reports in the last 90 days.
                </p>
            ) : (
                <>
                    <div className="mt-4 p-4" style={{ background: surface, border: `1px solid ${border}` }}>
                        <div style={{ color: textSecondary, fontSize: "1rem" }}>Reports received</div>
                        <div style={{ color: text, fontWeight: 700, fontSize: "1.75rem" }}>{totals.ninety}</div>
                        <div style={{ color: textSecondary, fontSize: "1rem" }}>{totals.thirty} in the last 30 days</div>
                    </div>

                    <p style={heading}>Most reported problems</p>
                    {problems.map((problem) => (
                        <Link
                            key={problem.uuid}
                            href={`${basePath}/dashboard?category=${problem.uuid}`}
                            className="block mb-2 p-3"
                            style={{ background: surface, border: `1px solid ${border}` }}
                        >
                            <div className="flex justify-between" style={{ color: text, fontSize: "1.0625rem" }}>
                                <span>{problem.name}</span>
                                <span style={{ fontWeight: 700 }}>{problem.count}</span>
                            </div>
                            <div style={{ height: 8, marginTop: 6, background: border }}>
                                <div style={{ height: 8, width: `${(problem.count / maxProblem) * 100}%`, background: bar }} />
                            </div>
                        </Link>
                    ))}

                    <p style={heading}>Reports by week</p>
                    <div className="flex items-end gap-1 p-3" style={{ background: surface, border: `1px solid ${border}`, height: 140 }}>
                        {weeks.map((week) => (
                            <div
                                key={week.week_start}
                                title={`${slashDate(week.week_start)}: ${week.count}`}
                                style={{ flex: 1, minHeight: 2, height: `${(week.count / maxWeek) * 100}%`, background: bar }}
                            />
                        ))}
                    </div>

                    {communities.length > 0 && (
                        <>
                            <p style={heading}>Reports by community</p>
                            {communities.map((community) => (
                                <div key={community.name} className="mb-2 p-3" style={{ background: surface, border: `1px solid ${border}` }}>
                                    <div className="flex justify-between" style={{ color: text, fontSize: "1.0625rem" }}>
                                        <span>{community.name}</span>
                                        <span style={{ fontWeight: 700 }}>{community.count}</span>
                                    </div>
                                    <div style={{ height: 8, marginTop: 6, background: border }}>
                                        <div style={{ height: 8, width: `${(community.count / maxCommunity) * 100}%`, background: bar }} />
                                    </div>
                                </div>
                            ))}
                        </>
                    )}
                </>
            )}

            <Link href={`${basePath}/dashboard`} className="block mt-6" style={{ color: bar, fontWeight: 600, fontSize: "1.0625rem" }}>
                See all reports
            </Link>
        </div>
    );
}
