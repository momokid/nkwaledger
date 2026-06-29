import defaultTheme from "tailwindcss/defaultTheme";
import forms from "@tailwindcss/forms";

/** @type {import('tailwindcss').Config} */
export default {
    content: [
        "./vendor/laravel/framework/src/Illuminate/Pagination/resources/views/*.blade.php",
        "./storage/framework/views/*.php",
        "./resources/views/**/*.blade.php",
        "./resources/js/**/*.tsx",
    ],

    theme: {
        borderRadius: {
            DEFAULT: "0",
            none: "0",
            sm: "0",
            md: "0",
            lg: "0",
            xl: "0",
            "2xl": "0",
            "3xl": "0",
            full: "0",
        },
        extend: {
            fontFamily: {
                sans: [
                    "Inter",
                    "Segoe UI Variable",
                    "Segoe UI",
                    ...defaultTheme.fontFamily.sans,
                ],
            },
            colors: {
                brand: {
                    DEFAULT: "#1D9E75",
                    dark: "#0F6E56",
                    light: "#EAF5F0",
                    border: "#A8D9C8",
                },
                gold: "#BA7517",
            },
        },
    },

    plugins: [forms],
};
