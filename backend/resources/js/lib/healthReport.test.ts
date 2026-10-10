// @vitest-environment jsdom
import { describe, expect, it } from "vitest";
import { buildHealthReport, healthReportErrors, mediaToFile } from "./healthReport";

const FARMER = "5b0f9f4e-0c3e-4a67-9a52-1e0f3f2a1111";
const photo = (size = 10, type = "image/png") => new File([new Uint8Array(size)], "leaf.png", { type });
const voice = () => new File([new Uint8Array([1, 2, 3])], "voice-note.webm", { type: "audio/webm" });

describe("healthReportErrors", () => {
    it("asks for a description and a photo with the online wording", () => {
        expect(healthReportErrors("", null, null)).toEqual({
            description: "Please describe what you are seeing.",
            photo: "Please add one photo.",
        });
    });

    it("treats a blank description as missing", () => {
        expect(healthReportErrors("   ", photo(), null).description).toBe("Please describe what you are seeing.");
    });

    it("refuses a description over 1000 characters like the server does", () => {
        expect(healthReportErrors("a".repeat(1001), photo(), null).description).toBe(
            "The description field must not be greater than 1000 characters.",
        );
    });

    it("refuses a file that is not a photo", () => {
        expect(healthReportErrors("Weak birds", photo(10, "application/pdf"), null).photo).toBe("That file does not look like a photo.");
    });

    it("refuses a photo over 10 MB", () => {
        expect(healthReportErrors("Weak birds", photo(10 * 1024 * 1024 + 1), null).photo).toBe(
            "That photo is too large. Please choose a smaller one.",
        );
    });

    it("refuses a voice note over 5 MB", () => {
        const big = new File([new Uint8Array(5 * 1024 * 1024 + 1)], "v.webm", { type: "audio/webm" });

        expect(healthReportErrors("Weak birds", photo(), big).audio).toBe("That recording is too large.");
    });

    it("accepts a complete report", () => {
        expect(healthReportErrors("Weak birds", photo(), voice())).toEqual({});
    });
});

describe("buildHealthReport", () => {
    it("carries the text for the server and the media for the phone", async () => {
        const report = await buildHealthReport({ farmer: FARMER, farmUnitId: 4, description: "Weak birds", photo: photo(), audio: voice(), uuid: "u-1" });

        expect(report).toMatchObject({ shape: 2, type: "health_report", uuid: "u-1", farmer: FARMER, farm_unit_id: 4, description: "Weak birds" });
        expect(report.event_date).toMatch(/^\d{4}-\d{2}-\d{2}$/);
        expect(report.media.photo.type).toBe("image/png");
        expect(report.media.audio?.type).toBe("audio/webm");
    });

    it("leaves the voice note out when there is none", async () => {
        const report = await buildHealthReport({ farmer: FARMER, farmUnitId: 4, description: "x", photo: photo(), audio: null, uuid: "u-2" });

        expect(report.media.audio).toBeUndefined();
    });

    it("keeps the exact bytes, so the media can be sent later", async () => {
        const report = await buildHealthReport({ farmer: FARMER, farmUnitId: 4, description: "x", photo: photo(), audio: voice(), uuid: "u-3" });
        const back = mediaToFile(report.media.audio!);

        expect(back.name).toBe("voice-note.webm");
        expect(back.type).toBe("audio/webm");
        const bytes = await new Promise<ArrayBuffer>((resolve) => {
            const reader = new FileReader();
            reader.onload = () => resolve(reader.result as ArrayBuffer);
            reader.readAsArrayBuffer(back);
        });

        expect(Array.from(new Uint8Array(bytes))).toEqual([1, 2, 3]);
    });
});
