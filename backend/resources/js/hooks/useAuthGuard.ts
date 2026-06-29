import { router } from "@inertiajs/react";
import { useEffect } from "react";

export default function useAuthGuard() {
    useEffect(() => {
        const check = async () => {
            try {
                const res = await fetch("/auth/check", {
                    headers: { "X-Requested-With": "XMLHttpRequest" },
                    credentials: "same-origin",
                });
                if (!res.ok) {
                    router.visit("/login", { replace: true });
                }
            } catch {
                router.visit("/login", { replace: true });
            }
        };

        check();

        const onPageShow = (e: PageTransitionEvent) => {
            if (e.persisted) {
                window.location.reload();
            }
        };

        window.addEventListener("pageshow", onPageShow);
        return () => window.removeEventListener("pageshow", onPageShow);
    }, []);
}
