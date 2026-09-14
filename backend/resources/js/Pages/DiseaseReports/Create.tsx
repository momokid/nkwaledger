import AuthenticatedLayout, { useTheme } from "@/Layouts/AuthenticatedLayout";
import { useForm, usePage } from "@inertiajs/react";
import { FormEvent, useState } from "react";
import Button from "@/Components/Button";

interface Props {
    farmUnit: { id: number; name: string };
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

    const [preview, setPreview] = useState<string | null>(null);

    const form = useForm<{ description: string; photo: File | null }>({
        description: "",
        photo: null,
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
                        type="file"
                        accept="image/*"
                        capture="environment"
                        style={field}
                        onChange={onPhotoChange}
                    />
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
