import { useTheme } from "@/Layouts/AuthenticatedLayout";
import useGreeting from "@/hooks/useGreeting";

interface GreetingHeaderProps {
    subtitle: string;
}

export default function GreetingHeader({ subtitle }: GreetingHeaderProps) {
    const { dark } = useTheme();
    const { greeting, firstName } = useGreeting();

    const text = dark ? "#F9FAFB" : "#111827";
    const textSecondary = dark ? "#9CA3AF" : "#6B7280";

    return (
        <>
            <p
                style={{
                    fontSize: "1.4375rem",
                    fontWeight: 600,
                    color: text,
                    marginBottom: "4px",
                }}
            >
                {greeting}, {firstName}
            </p>
            <p
                style={{
                    fontSize: "1.125rem",
                    color: textSecondary,
                    marginBottom: "20px",
                }}
            >
                {subtitle}
            </p>
        </>
    );
}
