import { defineConfig } from "vitest/config";
import path from "node:path";

export default defineConfig({
    resolve: {
        alias: {
            "@": path.resolve(__dirname, "resources/js"),
        },
    },
    test: {
        environment: "node",
        setupFiles: ["./resources/js/tests/setup.ts"],
        include: ["resources/js/**/*.test.{ts,tsx}"],
    },
});
