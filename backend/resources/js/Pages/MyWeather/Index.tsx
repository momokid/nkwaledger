import AuthenticatedLayout, { useTheme } from "@/Layouts/AuthenticatedLayout";
import { PageProps } from "@/types";
import { Link } from "@inertiajs/react";
import {
    IconCloud,
    IconCloudRain,
    IconSun,
    IconWind,
} from "@tabler/icons-react";

type Condition = "heavy_rain" | "strong_wind" | "very_hot" | "normal";

interface ForecastDay {
    date: string;
    condition: Condition;
    precipitation_mm: number | null;
    temperature_max_c: number | null;
    windspeed_max_kmh: number | null;
}

interface Advice {
    category: string;
    message: string;
}

interface Alert {
    farm_type: string;
    message: string;
}

interface HistoryEntry {
    date: string;
    condition: Condition;
    headline: string;
}

interface LocationWeather {
    community: string;
    available: boolean;
    headline?: string;
    advice?: Advice[];
    alerts?: Alert[];
    outlook?: string;
    forecast?: ForecastDay[];
    history?: HistoryEntry[];
}

interface Props extends PageProps {
    locations: LocationWeather[];
}

export default function Index(props: Props) {
    return (
        <AuthenticatedLayout title="Weather">
            <IndexContent locations={props.locations} />
        </AuthenticatedLayout>
    );
}

type ContentProps = Pick<Props, "locations">;

const iconFor = (condition: Condition) => {
    if (condition === "heavy_rain")
        return {
            Icon: IconCloudRain,
            animation: "weatherRain 1.6s ease-in-out infinite",
        };
    if (condition === "strong_wind")
        return {
            Icon: IconWind,
            animation: "weatherWind 1.6s ease-in-out infinite",
        };
    if (condition === "very_hot")
        return {
            Icon: IconSun,
            animation: "weatherSun 2s ease-in-out infinite",
        };
    return { Icon: IconCloud, animation: "none" };
};

const weekdayLabel = (isoDate: string): string =>
    new Date(`${isoDate}T00:00:00`).toLocaleDateString("en-GB", {
        weekday: "short",
        day: "numeric",
    });

function IndexContent({ locations }: ContentProps) {
    const { dark } = useTheme();

    const surface = dark ? "#1F2937" : "#FFFFFF";
    const border = dark ? "#374151" : "#E5E7EB";
    const text = dark ? "#F9FAFB" : "#111827";
    const textSecondary = dark ? "#9CA3AF" : "#6B7280";
    const track = dark ? "#111827" : "#F9FAFB";

    const goodBg = dark ? "rgba(29,158,117,0.15)" : "#EAF5F0";
    const goodText = dark ? "#4ADE80" : "#0F6E56";
    const warnBg = dark ? "rgba(180,83,9,0.15)" : "#FEF3C7";
    const warnText = dark ? "#FBBF24" : "#92400E";

    if (locations.length === 0) {
        return (
            <div className="p-6">
                <p
                    style={{
                        fontSize: "1.625rem",
                        fontWeight: 700,
                        color: text,
                        marginBottom: "16px",
                    }}
                >
                    Weather
                </p>
                <div
                    style={{
                        background: surface,
                        border: `1px solid ${border}`,
                        padding: "24px",
                    }}
                >
                    <p
                        style={{
                            fontSize: "1.125rem",
                            color: textSecondary,
                            marginBottom: "12px",
                        }}
                    >
                        Add a farm unit to see the weather for your land.
                    </p>
                    <Link
                        href="/my-farm"
                        style={{
                            fontSize: "1.125rem",
                            fontWeight: 600,
                            color: "#1D9E75",
                        }}
                    >
                        Go to My Farm →
                    </Link>
                </div>
            </div>
        );
    }

    return (
        <div className="p-6">
            <style>{`
                @keyframes weatherRain { 0%,100% { transform: translateY(0); } 50% { transform: translateY(3px); } }
                @keyframes weatherSun { 0%,100% { opacity: 1; transform: scale(1); } 50% { opacity: 0.7; transform: scale(1.1); } }
                @keyframes weatherWind { 0%,100% { transform: translateX(0); } 50% { transform: translateX(4px); } }
            `}</style>

            <p
                style={{
                    fontSize: "1.625rem",
                    fontWeight: 700,
                    color: text,
                    marginBottom: "20px",
                }}
            >
                Weather
            </p>

            <div
                style={{
                    display: "flex",
                    flexDirection: "column",
                    gap: "16px",
                }}
            >
                {locations.map((location) => (
                    <LocationCard
                        key={location.community}
                        location={location}
                        dark={dark}
                        surface={surface}
                        border={border}
                        text={text}
                        textSecondary={textSecondary}
                        track={track}
                        goodBg={goodBg}
                        goodText={goodText}
                        warnBg={warnBg}
                        warnText={warnText}
                    />
                ))}
            </div>
        </div>
    );
}

function LocationCard({
    location,
    surface,
    border,
    text,
    textSecondary,
    track,
    goodBg,
    goodText,
    warnBg,
    warnText,
}: {
    location: LocationWeather;
    dark: boolean;
    surface: string;
    border: string;
    text: string;
    textSecondary: string;
    track: string;
    goodBg: string;
    goodText: string;
    warnBg: string;
    warnText: string;
}) {
    if (!location.available) {
        return (
            <div
                style={{
                    background: surface,
                    border: `1px solid ${border}`,
                    padding: "20px",
                }}
            >
                <p
                    style={{
                        fontSize: "1.25rem",
                        fontWeight: 600,
                        color: text,
                        marginBottom: "8px",
                    }}
                >
                    {location.community}
                </p>
                <p style={{ fontSize: "1rem", color: textSecondary }}>
                    Weather isn't available for this location right now.
                </p>
            </div>
        );
    }

    const forecast = location.forecast ?? [];
    const today = forecast[0];
    const isNormal = today?.condition === "normal";
    const bannerBg = isNormal ? goodBg : warnBg;
    const bannerText = isNormal ? goodText : warnText;
    const { Icon: TodayIcon, animation: todayAnimation } = today
        ? iconFor(today.condition)
        : { Icon: IconCloud, animation: "none" };

    return (
        <div
            style={{
                background: surface,
                border: `1px solid ${border}`,
                padding: "20px",
            }}
        >
            <p
                style={{
                    fontSize: "1.25rem",
                    fontWeight: 600,
                    color: text,
                    marginBottom: "14px",
                }}
            >
                {location.community}
            </p>

            <div
                style={{
                    display: "flex",
                    alignItems: "center",
                    gap: "12px",
                    background: bannerBg,
                    padding: "14px 16px",
                    marginBottom: "18px",
                }}
            >
                <TodayIcon
                    size={26}
                    style={{ color: bannerText, animation: todayAnimation }}
                />
                <p
                    style={{
                        fontSize: "1.125rem",
                        fontWeight: 600,
                        color: bannerText,
                    }}
                >
                    {location.headline}
                </p>
            </div>

            {location.alerts && location.alerts.length > 0 && (
                <div
                    style={{
                        display: "flex",
                        flexDirection: "column",
                        gap: "8px",
                        marginBottom: "16px",
                    }}
                >
                    {location.alerts.map((alert) => (
                        <p
                            key={alert.farm_type}
                            style={{
                                fontSize: "1rem",
                                color: warnText,
                                background: warnBg,
                                padding: "10px 12px",
                            }}
                        >
                            <span style={{ fontWeight: 600 }}>
                                {alert.farm_type}:
                            </span>{" "}
                            {alert.message}
                        </p>
                    ))}
                </div>
            )}

            {location.advice && location.advice.length > 0 && (
                <div
                    style={{
                        display: "flex",
                        flexDirection: "column",
                        gap: "8px",
                        marginBottom: "20px",
                    }}
                >
                    {location.advice.map((item) => (
                        <p
                            key={item.category}
                            style={{ fontSize: "1rem", color: textSecondary }}
                        >
                            <span style={{ fontWeight: 600, color: text }}>
                                {item.category}:
                            </span>{" "}
                            {item.message}
                        </p>
                    ))}
                </div>
            )}

            {location.outlook && (
                <p
                    style={{
                        fontSize: "1.0625rem",
                        color: textSecondary,
                        marginBottom: "16px",
                    }}
                >
                    {location.outlook}
                </p>
            )}

            <div
                style={{
                    display: "grid",
                    gridTemplateColumns: `repeat(${forecast.length}, 1fr)`,
                    gap: "10px",
                }}
            >
                {forecast.map((day) => {
                    const { Icon, animation } = iconFor(day.condition);
                    return (
                        <div
                            key={day.date}
                            style={{
                                background: track,
                                padding: "12px 8px",
                                textAlign: "center",
                            }}
                        >
                            <p
                                style={{
                                    fontSize: "0.9375rem",
                                    color: textSecondary,
                                    marginBottom: "8px",
                                }}
                            >
                                {weekdayLabel(day.date)}
                            </p>
                            <Icon
                                size={24}
                                style={{
                                    color: text,
                                    animation,
                                    margin: "0 auto 8px",
                                }}
                            />
                            <p
                                style={{
                                    fontSize: "1.0625rem",
                                    fontWeight: 600,
                                    color: text,
                                }}
                            >
                                {day.temperature_max_c !== null
                                    ? `${Math.round(day.temperature_max_c)}°`
                                    : "—"}
                            </p>
                            <p
                                style={{
                                    fontSize: "0.875rem",
                                    color: textSecondary,
                                }}
                            >
                                {day.precipitation_mm !== null
                                    ? `${day.precipitation_mm}mm`
                                    : "—"}
                            </p>
                        </div>
                    );
                })}
            </div>

            {location.history && location.history.length > 0 && (
                <div
                    style={{
                        marginTop: "20px",
                        paddingTop: "18px",
                        borderTop: `1px solid ${border}`,
                    }}
                >
                    <p
                        style={{
                            fontSize: "1rem",
                            fontWeight: 600,
                            color: text,
                            marginBottom: "10px",
                        }}
                    >
                        Recent days
                    </p>
                    <div
                        style={{
                            display: "flex",
                            flexDirection: "column",
                            gap: "6px",
                        }}
                    >
                        {location.history.map((entry) => (
                            <div
                                key={entry.date}
                                style={{
                                    display: "flex",
                                    justifyContent: "space-between",
                                    gap: "12px",
                                }}
                            >
                                <p
                                    style={{
                                        fontSize: "0.9375rem",
                                        color: textSecondary,
                                    }}
                                >
                                    {weekdayLabel(entry.date)}
                                </p>
                                <p style={{ fontSize: "0.9375rem", color: text }}>
                                    {entry.headline}
                                </p>
                            </div>
                        ))}
                    </div>
                </div>
            )}
        </div>
    );
}
