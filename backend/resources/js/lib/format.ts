export const shortDate = (value: string | null | undefined): string => {
    if (!value) return "—";

    const date = new Date(value);

    if (Number.isNaN(date.getTime())) return "—";

    return date.toLocaleDateString("en-US", {
        month: "short",
        day: "numeric",
        year: "numeric",
    });
};

export const cedis = (minor: number): string =>
    (minor / 100).toLocaleString("en-GH", {
        minimumFractionDigits: 2,
        maximumFractionDigits: 2,
    });

// DD/MM/YYYY from the date part of a YYYY-MM-DD string, read as text so no timezone can move the day
export const slashDate = (value: string | null | undefined): string => {
    const match = /^(\d{4})-(\d{2})-(\d{2})/.exec(value ?? "");

    return match ? `${match[3]}/${match[2]}/${match[1]}` : "—";
};

// a loan past its due date: the words and the colour (amber for expense/reduction, red for loss)
export const LATE_STATUS: Record<string, { label: string; color: string }> = {
    overdue: { label: "Overdue", color: "#B45309" },
    default: { label: "Default", color: "#B91C1C" },
};
