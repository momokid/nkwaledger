import { router, usePage } from "@inertiajs/react";
import { useEffect } from "react";

export default function useAuthGuard() {
    const { auth } = usePage().props as { auth: { user: object | null } };

    useEffect(() => {
        if (!auth.user) {
            router.visit("/login", { replace: true });
        }
    }, [auth.user]);

    useEffect(() => {
        const onPageShow = (e: PageTransitionEvent) => {
            if (e.persisted && !auth.user) {
                router.visit("/login", { replace: true });
            }
        };

        window.addEventListener("pageshow", onPageShow);
        return () => window.removeEventListener("pageshow", onPageShow);
    }, [auth.user]);
}
