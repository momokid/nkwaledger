import { useEffect, useState } from "react";

interface Props {
    dark: boolean;
}

export default function ConnectivityIndicator({ dark }: Props) {
    const [online, setOnline] = useState(
        typeof navigator === "undefined" ? true : navigator.onLine,
    );

    useEffect(() => {
        const goOnline = () => setOnline(true);
        const goOffline = () => setOnline(false);

        window.addEventListener("online", goOnline);
        window.addEventListener("offline", goOffline);

        return () => {
            window.removeEventListener("online", goOnline);
            window.removeEventListener("offline", goOffline);
        };
    }, []);

    const dotColor = online ? "#1D9E75" : "#9CA3AF";
    const textColor = dark ? "#9CA3AF" : "#6B7280";

    return (
        <div
            role="status"
            aria-live="polite"
            style={{
                display: "flex",
                alignItems: "center",
                gap: "6px",
            }}
        >
            <span
                aria-hidden="true"
                style={{
                    width: "10px",
                    height: "10px",
                    borderRadius: "50%",
                    background: dotColor,
                    flexShrink: 0,
                }}
            />
            <span
                style={{
                    fontSize: "0.875rem",
                    fontWeight: 600,
                    color: textColor,
                }}
            >
                {online ? "Online" : "Offline"}
            </span>
        </div>
    );
}
