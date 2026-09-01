export const shortDate = (value: string | null | undefined): string => {
    if (!value) return "—";

    const date = new Date(value);

    if (Number.isNaN(date.getTime())) return "—";

    const day = String(date.getDate()).padStart(2, "0");
    const month = String(date.getMonth() + 1).padStart(2, "0");

    return `${day}/${month}/${date.getFullYear()}`;
};

export const cedis = (minor: number): string =>
    (minor / 100).toLocaleString("en-GH", {
        minimumFractionDigits: 2,
        maximumFractionDigits: 2,
    });
