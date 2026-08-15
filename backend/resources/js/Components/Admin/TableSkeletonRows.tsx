import { useTheme } from "@/Layouts/AuthenticatedLayout";

interface Props {
    rows?: number;
    columns: number;
}

export default function TableSkeletonRows({ rows = 5, columns }: Props) {
    const { dark } = useTheme();

    const border = dark ? "#374151" : "#E5E7EB";
    const pulse = dark ? "#374151" : "#E5E7EB";

    return (
        <>
            {Array.from({ length: rows }).map((_, rowIndex) => (
                <tr key={rowIndex} style={{ borderTop: `1px solid ${border}` }}>
                    {Array.from({ length: columns }).map((__, colIndex) => (
                        <td key={colIndex} className="px-4 py-3">
                            <div
                                className="animate-pulse"
                                style={{
                                    height: "16px",
                                    width: colIndex === 0 ? "70%" : "50%",
                                    background: pulse,
                                }}
                            />
                        </td>
                    ))}
                </tr>
            ))}
        </>
    );
}
