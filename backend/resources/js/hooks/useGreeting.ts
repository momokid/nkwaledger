import { usePage } from "@inertiajs/react";

interface AuthProps {
    auth: {
        user: {
            first_name?: string;
        } | null;
    };
}

export default function useGreeting(fallbackName: string = "there") {
    const { auth } = usePage().props as unknown as AuthProps;
    const firstName = auth?.user?.first_name ?? fallbackName;

    const hour = new Date().getHours();
    const greeting =
        hour < 12
            ? "Good morning"
            : hour < 17
              ? "Good afternoon"
              : "Good evening";

    return { greeting, firstName };
}
