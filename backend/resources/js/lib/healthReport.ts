import { compressImage } from "./compressImage";
import { QueuedHealthReport, QueuedMedia } from "@/types/offlineQueue";

export const GENERIC_ERROR = "Something went wrong. Please try again.";

// the same limits and words as StoreDiseaseReportRequest, so a report is judged the same on or off line
const MAX_DESCRIPTION = 1000;
const MAX_PHOTO_BYTES = 10240 * 1024;
const MAX_AUDIO_BYTES = 5120 * 1024;

export function healthReportErrors(description: string, photo: File | null, audio: File | null): Record<string, string> {
    const errors: Record<string, string> = {};

    if (description.trim() === "") {
        errors.description = "Please describe what you are seeing.";
    } else if (description.trim().length > MAX_DESCRIPTION) {
        errors.description = `The description field must not be greater than ${MAX_DESCRIPTION} characters.`;
    }

    if (!photo) {
        errors.photo = "Please add one photo.";
    } else if (!photo.type.startsWith("image/")) {
        errors.photo = "That file does not look like a photo.";
    } else if (photo.size > MAX_PHOTO_BYTES) {
        errors.photo = "That photo is too large. Please choose a smaller one.";
    }

    if (audio && audio.size > MAX_AUDIO_BYTES) {
        errors.audio = "That recording is too large.";
    }

    return errors;
}

function toMedia(file: File): Promise<QueuedMedia> {
    return new Promise((resolve, reject) => {
        const reader = new FileReader();

        reader.onload = () => resolve({ name: file.name, type: file.type, data: String(reader.result).split(",")[1] ?? "" });
        reader.onerror = () => reject(reader.error);
        reader.readAsDataURL(file);
    });
}

export function mediaToFile(media: QueuedMedia): File {
    const bytes = Uint8Array.from(atob(media.data), (char) => char.charCodeAt(0));

    return new File([bytes], media.name, { type: media.type });
}

interface HealthReportInput {
    farmer: string;
    farmUnitId: number;
    description: string;
    photo: File;
    audio: File | null;
    uuid: string;
}

// the photo is shrunk and re-encoded as online uploads are; the media stays encrypted with the report on the phone
export async function buildHealthReport({ farmer, farmUnitId, description, photo, audio, uuid }: HealthReportInput): Promise<QueuedHealthReport> {
    return {
        shape: 2,
        type: "health_report",
        uuid,
        farmer,
        farm_unit_id: farmUnitId,
        description: description.trim(),
        event_date: new Date().toISOString().slice(0, 10),
        device_created_at: new Date().toISOString(),
        media: {
            photo: await toMedia(await compressImage(photo)),
            ...(audio ? { audio: await toMedia(audio) } : {}),
        },
    };
}
