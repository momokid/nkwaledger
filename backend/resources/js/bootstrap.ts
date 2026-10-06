import axios from "axios";
import { noteContact } from "@/lib/serverContact";

window.axios = axios;

window.axios.interceptors.response.use((response) => {
    noteContact(response.headers?.date as string | undefined);

    return response;
});

window.axios.defaults.headers.common["X-Requested-With"] = "XMLHttpRequest";

// Retrieve CSRF token from meta tag
const token = document.head.querySelector<HTMLMetaElement>(
    'meta[name="csrf-token"]',
);

if (token) {
    window.axios.defaults.headers.common["X-CSRF-TOKEN"] = token.content;
} else {
    console.error(
        "CSRF token not found: https://laravel.com/docs/csrf#csrf-token-mismatch",
    );
}
