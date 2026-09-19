import { PropsWithChildren, useState, useEffect } from "react";
import { ThemeContext, TextSize } from "@/Layouts/AuthenticatedLayout";

// guest pages render before login, so there's no AuthenticatedLayout/AdminLayout
// around them to supply ThemeContext — this reads the same "nkwa_theme" key so a
// preference set on the dashboard still applies here
export default function GuestThemeProvider({ children }: PropsWithChildren) {
    const [dark, setDark] = useState(false);
    const [textSize, setTextSizeState] = useState<TextSize>("normal");

    useEffect(() => {
        const savedTheme = localStorage.getItem("nkwa_theme");
        const savedTextSize = localStorage.getItem("nkwa_text_size");
        if (savedTheme === "dark") setDark(true);
        if (
            savedTextSize === "normal" ||
            savedTextSize === "large" ||
            savedTextSize === "extra-large"
        ) {
            setTextSizeState(savedTextSize);
        }
    }, []);

    const toggle = () => {
        setDark((prev) => {
            localStorage.setItem("nkwa_theme", !prev ? "dark" : "light");
            return !prev;
        });
    };

    const setTextSize = (size: TextSize) => {
        localStorage.setItem("nkwa_text_size", size);
        setTextSizeState(size);
    };

    return (
        <ThemeContext.Provider value={{ dark, toggle, textSize, setTextSize }}>
            {children}
        </ThemeContext.Provider>
    );
}
