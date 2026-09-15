import AuthenticatedLayout, { useTheme } from "@/Layouts/AuthenticatedLayout";
import { useForm, usePage } from "@inertiajs/react";
import { FormEvent, useEffect, useRef, useState } from "react";
import { IconMicrophone, IconPlayerStop, IconTrash } from "@tabler/icons-react";
import Button from "@/Components/Button";

interface Props {
    farmUnit: { id: number; name: string };
}

type RecordingState = "idle" | "requesting" | "recording" | "recorded";

const MAX_RECORDING_SECONDS = 30;

// whatever container MediaRecorder actually used, kept only for a sensible filename
const EXTENSION_FOR_MIME: Record<string, string> = {
    "audio/webm": "webm",
    "audio/ogg": "ogg",
    "audio/mp4": "m4a",
    "audio/aac": "aac",
    "audio/mpeg": "mp3",
};

function extensionFor(mimeType: string): string {
    return EXTENSION_FOR_MIME[mimeType.split(";")[0].trim()] ?? "webm";
}

function formatSeconds(totalSeconds: number): string {
    const minutes = Math.floor(totalSeconds / 60);
    const seconds = totalSeconds % 60;

    return `${minutes}:${seconds.toString().padStart(2, "0")}`;
}

export default function Create(props: Props) {
    return (
        <AuthenticatedLayout title="Report a problem">
            <CreateContent {...props} />
        </AuthenticatedLayout>
    );
}

function CreateContent({ farmUnit }: Props) {
    const { errors } = usePage().props as unknown as {
        errors: Record<string, string>;
    };
    const { dark } = useTheme();

    const surface = dark ? "#1F2937" : "#FFFFFF";
    const border = dark ? "#374151" : "#E5E7EB";
    const inputBorder = dark ? "#4B5563" : "#9CA3AF";
    const inputBg = dark ? "#111827" : "#FFFFFF";
    const text = dark ? "#F9FAFB" : "#111827";
    const textSecondary = dark ? "#9CA3AF" : "#6B7280";
    const danger = "#B91C1C";

    const [preview, setPreview] = useState<string | null>(null);
    const photoInputRef = useRef<HTMLInputElement>(null);

    const form = useForm<{
        description: string;
        photo: File | null;
        audio: File | null;
    }>({
        description: "",
        photo: null,
        audio: null,
    });

    const submit = (event: FormEvent) => {
        event.preventDefault();

        form.post(`/my-farm/${farmUnit.id}/report-problem`, {
            forceFormData: true,
        });
    };

    const onPhotoChange = (event: React.ChangeEvent<HTMLInputElement>) => {
        const file = event.target.files?.[0] ?? null;

        form.setData("photo", file);
        setPreview(file ? URL.createObjectURL(file) : null);
    };

    // --- voice note recording ---

    const recorderSupported =
        typeof window !== "undefined" && "MediaRecorder" in window;

    const [recordingState, setRecordingState] = useState<RecordingState>("idle");
    const [elapsedSeconds, setElapsedSeconds] = useState(0);
    const [audioUrl, setAudioUrl] = useState<string | null>(null);
    const [micError, setMicError] = useState<string | null>(null);

    const mediaRecorderRef = useRef<MediaRecorder | null>(null);
    const chunksRef = useRef<Blob[]>([]);
    const streamRef = useRef<MediaStream | null>(null);
    const timerRef = useRef<ReturnType<typeof setInterval> | null>(null);

    const stopTimer = () => {
        if (timerRef.current !== null) {
            clearInterval(timerRef.current);
            timerRef.current = null;
        }
    };

    const releaseStream = () => {
        streamRef.current?.getTracks().forEach((track) => track.stop());
        streamRef.current = null;
    };

    useEffect(() => {
        // leaving mid-recording should not leave the microphone running
        return () => {
            stopTimer();
            releaseStream();
        };
    }, []);

    const startRecording = async () => {
        if (audioUrl) {
            URL.revokeObjectURL(audioUrl);
            setAudioUrl(null);
        }

        setMicError(null);
        setRecordingState("requesting");

        try {
            const stream = await navigator.mediaDevices.getUserMedia({ audio: true });
            streamRef.current = stream;

            const recorder = new MediaRecorder(stream);
            mediaRecorderRef.current = recorder;
            chunksRef.current = [];

            recorder.ondataavailable = (event) => {
                if (event.data.size > 0) chunksRef.current.push(event.data);
            };

            recorder.onstop = () => {
                stopTimer();
                releaseStream();

                const mimeType = recorder.mimeType || "audio/webm";
                const blob = new Blob(chunksRef.current, { type: mimeType });
                const file = new File(
                    [blob],
                    `voice-note.${extensionFor(mimeType)}`,
                    { type: mimeType },
                );

                form.setData("audio", file);
                setAudioUrl(URL.createObjectURL(blob));
                setRecordingState("recorded");
            };

            recorder.start();
            setElapsedSeconds(0);
            setRecordingState("recording");

            timerRef.current = setInterval(() => {
                setElapsedSeconds((current) => {
                    const next = current + 1;

                    if (next >= MAX_RECORDING_SECONDS) {
                        recorder.stop();
                    }

                    return next;
                });
            }, 1000);
        } catch {
            setRecordingState("idle");
            setMicError(
                "We could not reach your microphone. You can still send this report without a voice note.",
            );
        }
    };

    const stopRecording = () => {
        mediaRecorderRef.current?.stop();
    };

    const discardRecording = () => {
        if (audioUrl) URL.revokeObjectURL(audioUrl);

        setAudioUrl(null);
        form.setData("audio", null);
        setRecordingState("idle");
        setElapsedSeconds(0);
    };

    const field = {
        width: "100%",
        padding: "10px 12px",
        border: `1px solid ${inputBorder}`,
        background: inputBg,
        color: text,
        fontSize: "1.125rem",
    } as const;

    const label = {
        display: "block",
        fontSize: "1.0625rem",
        fontWeight: 600,
        color: text,
        marginBottom: "6px",
    } as const;

    const errorText = {
        fontSize: "0.9375rem",
        color: "#B91C1C",
        marginTop: "4px",
    } as const;

    return (
        <div
            className="p-6"
            style={{
                background: surface,
                border: `1px solid ${border}`,
                maxWidth: "560px",
            }}
        >
            <h2 style={{ fontSize: "1.375rem", fontWeight: 700, color: text }}>
                Report a problem
            </h2>
            <p
                style={{
                    fontSize: "1.0625rem",
                    color: textSecondary,
                    marginTop: "4px",
                }}
            >
                {farmUnit.name}. Tell us what you are seeing, and add one
                photo. We will send this to the right person to help.
            </p>

            <form onSubmit={submit} className="mt-5">
                <div className="mb-4">
                    <label style={label}>What is happening?</label>
                    <textarea
                        style={{ ...field, minHeight: "100px" }}
                        placeholder="e.g. Some of the birds look weak and are not eating"
                        value={form.data.description}
                        onChange={(event) =>
                            form.setData("description", event.target.value)
                        }
                    />
                    {errors.description && (
                        <p style={errorText}>{errors.description}</p>
                    )}
                </div>

                <div className="mb-5">
                    <label style={label}>A photo</label>
                    <input
                        ref={photoInputRef}
                        type="file"
                        accept="image/*"
                        capture="environment"
                        onChange={onPhotoChange}
                        style={{ display: "none" }}
                    />
                    <Button
                        type="button"
                        look="secondary"
                        size="small"
                        onClick={() => photoInputRef.current?.click()}
                    >
                        {form.data.photo ? "Change photo" : "Choose a photo"}
                    </Button>
                    {preview && (
                        <img
                            src={preview}
                            alt="Photo preview"
                            className="mt-2"
                            style={{
                                maxWidth: "100%",
                                maxHeight: "220px",
                                border: `1px solid ${border}`,
                            }}
                        />
                    )}
                    {errors.photo && <p style={errorText}>{errors.photo}</p>}
                </div>

                {recorderSupported && (
                    <div className="mb-5">
                        <label style={label}>A voice note (optional)</label>

                        {recordingState === "idle" && (
                            <Button
                                type="button"
                                look="secondary"
                                size="small"
                                onClick={startRecording}
                            >
                                <IconMicrophone size={18} stroke={1.8} />
                                Record a voice note
                            </Button>
                        )}

                        {recordingState === "requesting" && (
                            <p style={{ color: textSecondary, fontSize: "1rem" }}>
                                Asking for microphone access...
                            </p>
                        )}

                        {recordingState === "recording" && (
                            <div
                                className="flex items-center"
                                style={{ gap: "12px" }}
                            >
                                <span
                                    aria-hidden="true"
                                    style={{
                                        width: "10px",
                                        height: "10px",
                                        borderRadius: "50%",
                                        background: danger,
                                    }}
                                />
                                <span
                                    style={{
                                        color: text,
                                        fontSize: "1.0625rem",
                                        fontWeight: 600,
                                    }}
                                >
                                    Recording... {formatSeconds(elapsedSeconds)} / 0:30
                                </span>
                                <Button
                                    type="button"
                                    look="danger"
                                    size="small"
                                    onClick={stopRecording}
                                >
                                    <IconPlayerStop size={18} stroke={1.8} />
                                    Stop
                                </Button>
                            </div>
                        )}

                        {recordingState === "recorded" && audioUrl && (
                            <div>
                                <audio
                                    controls
                                    src={audioUrl}
                                    style={{ width: "100%" }}
                                />
                                <div className="flex mt-2" style={{ gap: "10px" }}>
                                    <Button
                                        type="button"
                                        look="secondary"
                                        size="small"
                                        onClick={startRecording}
                                    >
                                        <IconMicrophone size={18} stroke={1.8} />
                                        Re-record
                                    </Button>
                                    <Button
                                        type="button"
                                        look="danger"
                                        size="small"
                                        onClick={discardRecording}
                                    >
                                        <IconTrash size={18} stroke={1.8} />
                                        Remove
                                    </Button>
                                </div>
                            </div>
                        )}

                        {micError && <p style={errorText}>{micError}</p>}
                        {errors.audio && <p style={errorText}>{errors.audio}</p>}
                    </div>
                )}

                <Button
                    type="submit"
                    busy={form.processing}
                    busyLabel="Sending..."
                >
                    Send this report
                </Button>
            </form>
        </div>
    );
}
