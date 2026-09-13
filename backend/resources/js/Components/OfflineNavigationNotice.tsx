import { useEffect, useRef, useState } from "react";

// most pages still need a live connection to open — this tells a farmer why a tap
// on a link did nothing, instead of letting the error vanish silently into the console
export default function OfflineNavigationNotice() {
    const [visible, setVisible] = useState(false);
    const timerRef = useRef<ReturnType<typeof setTimeout> | null>(null);

    useEffect(() => {
        const show = () => {
            setVisible(true);
            if (timerRef.current) clearTimeout(timerRef.current);
            timerRef.current = setTimeout(() => setVisible(false), 6000);
        };

        window.addEventListener("inertia-offline-blocked", show);
        return () => {
            window.removeEventListener("inertia-offline-blocked", show);
            if (timerRef.current) clearTimeout(timerRef.current);
        };
    }, []);

    if (!visible) return null;

    return (
        <div
            style={{
                position: "fixed",
                top: "16px",
                left: "50%",
                transform: "translateX(-50%)",
                background: "#BA7517",
                color: "#FFFFFF",
                padding: "10px 20px",
                fontSize: "1rem",
                fontWeight: 600,
                zIndex: 100,
                fontFamily: "'Inter', system-ui, sans-serif",
                boxShadow: "0 2px 8px rgba(0,0,0,0.2)",
            }}
        >
            You're offline — this page needs a connection to open.
        </div>
    );
}
