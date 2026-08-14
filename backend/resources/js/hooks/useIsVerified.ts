import { usePage } from "@inertiajs/react";

interface GateProps {
    auth: {
        user: {
            is_phone_verified?: boolean;
        } | null;
    };
}

// tells a page whether this user has proved their phone
export default function useIsVerified(): boolean {
    const { auth } = usePage().props as unknown as GateProps;

    return auth?.user?.is_phone_verified !== false;
}
