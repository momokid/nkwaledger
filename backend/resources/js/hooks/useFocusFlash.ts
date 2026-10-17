import { useEffect } from "react";

// a notification opens a page with ?focus=<public id>: scroll to that item and flash it once
export default function useFocusFlash() {
    useEffect(() => {
        const id = new URLSearchParams(window.location.search).get("focus");
        const target = id ? Array.from(document.querySelectorAll<HTMLElement>("[data-focus]")).find((element) => element.dataset.focus === id) : undefined;

        if (!target) {
            return;
        }

        target.scrollIntoView({ block: "center" });
        target.classList.add("flash-focus");
        const timer = setTimeout(() => target.classList.remove("flash-focus"), 3000);

        return () => {
            clearTimeout(timer);
            target.classList.remove("flash-focus");
        };
    }, []);
}
