const MAX_SIDE = 1600;
const QUALITY = 0.75;

// shrinks and re-encodes a photo before upload so a slow connection carries less;
// the server re-encodes anyway, so on any doubt the original file is sent as it is
export async function compressImage(file: File): Promise<File> {
    try {
        const bitmap = await createImageBitmap(file, {
            imageOrientation: "from-image",
        });
        const scale = Math.min(1, MAX_SIDE / Math.max(bitmap.width, bitmap.height));
        const canvas = document.createElement("canvas");
        canvas.width = Math.round(bitmap.width * scale);
        canvas.height = Math.round(bitmap.height * scale);
        canvas.getContext("2d")?.drawImage(bitmap, 0, 0, canvas.width, canvas.height);

        const blob = await new Promise<Blob | null>((resolve) =>
            canvas.toBlob(resolve, "image/webp", QUALITY),
        );

        if (!blob || blob.type !== "image/webp" || blob.size >= file.size) {
            return file;
        }

        return new File([blob], file.name.replace(/\.[^.]+$/, "") + ".webp", {
            type: "image/webp",
        });
    } catch {
        return file;
    }
}
