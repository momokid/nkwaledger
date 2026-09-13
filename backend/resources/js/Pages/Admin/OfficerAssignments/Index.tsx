import AdminLayout from "@/Layouts/AdminLayout";
import { useTheme } from "@/Layouts/AuthenticatedLayout";
import { router, useForm, usePage } from "@inertiajs/react";
import { PageProps } from "@/types";
import { FormEvent } from "react";
import Button from "@/Components/Button";

interface AssignmentRow {
    id: number;
    agent_name: string;
    officer_name: string;
    role: string;
}

interface PersonOption {
    id: number;
    name: string;
}

interface Props extends PageProps {
    assignments: AssignmentRow[];
    agents: PersonOption[];
    vets: PersonOption[];
    advisers: PersonOption[];
}

export default function Index(props: Props) {
    return (
        <AdminLayout title="Officer Assignments">
            <IndexContent {...props} />
        </AdminLayout>
    );
}

type ContentProps = Pick<Props, "assignments" | "agents" | "vets" | "advisers">;

function IndexContent({ assignments, agents, vets, advisers }: ContentProps) {
    const { errors } = usePage<Props>().props;
    const { dark } = useTheme();

    const surface = dark ? "#1F2937" : "#FFFFFF";
    const border = dark ? "#374151" : "#E5E7EB";
    const inputBorder = dark ? "#4B5563" : "#9CA3AF";
    const inputBg = dark ? "#111827" : "#FFFFFF";
    const text = dark ? "#F9FAFB" : "#111827";
    const textSecondary = dark ? "#9CA3AF" : "#6B7280";
    const headerBg = dark ? "rgba(29,158,117,0.15)" : "#EAF5F0";
    const headerText = "#1D9E75";

    const form = useForm({
        agent_id: "",
        role: "vet",
        officer_id: "",
    });

    const officerOptions =
        form.data.role === "vet" ? vets : advisers;

    const submit = (event: FormEvent) => {
        event.preventDefault();

        form.post(route("admin.officer-assignments.store"), {
            preserveScroll: true,
            onSuccess: () => form.reset("agent_id", "officer_id"),
        });
    };

    const remove = (id: number) => {
        router.delete(route("admin.officer-assignments.destroy", id), {
            preserveScroll: true,
        });
    };

    const field = {
        width: "100%",
        padding: "9px 12px",
        border: `1px solid ${inputBorder}`,
        background: inputBg,
        color: text,
        fontSize: "1.0625rem",
    } as const;

    const label = {
        display: "block",
        fontSize: "1rem",
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
        <div className="p-6">
            <h1 style={{ fontSize: "1.375rem", fontWeight: 700, color: text }}>
                Officer Assignments
            </h1>
            <p
                style={{
                    fontSize: "1.0625rem",
                    color: textSecondary,
                    marginTop: "4px",
                }}
            >
                Link an agent to the vet or adviser who should receive their
                farmers' reports. Anything already waiting for this agent
                moves over the moment you add the link.
            </p>

            <form
                onSubmit={submit}
                className="mt-5 mb-6 p-4 grid grid-cols-3 gap-3 items-end"
                style={{ background: surface, border: `1px solid ${border}` }}
            >
                <div>
                    <label style={label}>Agent</label>
                    <select
                        style={field}
                        value={form.data.agent_id}
                        onChange={(event) =>
                            form.setData("agent_id", event.target.value)
                        }
                    >
                        <option value="">Choose one</option>
                        {agents.map((agent) => (
                            <option key={agent.id} value={agent.id}>
                                {agent.name}
                            </option>
                        ))}
                    </select>
                    {errors.agent_id && (
                        <p style={errorText}>{errors.agent_id}</p>
                    )}
                </div>

                <div>
                    <label style={label}>Role</label>
                    <select
                        style={field}
                        value={form.data.role}
                        onChange={(event) => {
                            form.setData("role", event.target.value);
                            form.setData("officer_id", "");
                        }}
                    >
                        <option value="vet">Vet</option>
                        <option value="adviser">Adviser</option>
                    </select>
                    {errors.role && <p style={errorText}>{errors.role}</p>}
                </div>

                <div>
                    <label style={label}>Officer</label>
                    <select
                        style={field}
                        value={form.data.officer_id}
                        onChange={(event) =>
                            form.setData("officer_id", event.target.value)
                        }
                    >
                        <option value="">Choose one</option>
                        {officerOptions.map((officer) => (
                            <option key={officer.id} value={officer.id}>
                                {officer.name}
                            </option>
                        ))}
                    </select>
                    {errors.officer_id && (
                        <p style={errorText}>{errors.officer_id}</p>
                    )}
                </div>

                <div className="col-span-3">
                    <Button
                        type="submit"
                        size="small"
                        busy={form.processing}
                        busyLabel="Linking..."
                    >
                        Add link
                    </Button>
                </div>
            </form>

            {assignments.length === 0 ? (
                <div
                    className="p-4"
                    style={{
                        background: surface,
                        border: `1px solid ${border}`,
                        color: textSecondary,
                        fontSize: "1.0625rem",
                    }}
                >
                    No officers are linked yet.
                </div>
            ) : (
                <div
                    style={{
                        background: surface,
                        border: `1px solid ${border}`,
                        overflowX: "auto",
                    }}
                >
                    <table
                        className="w-full"
                        style={{ borderCollapse: "collapse", fontSize: "1rem" }}
                    >
                        <thead>
                            <tr style={{ background: headerBg }}>
                                {["Agent", "Role", "Officer", ""].map(
                                    (heading) => (
                                        <th
                                            key={heading}
                                            className="text-left px-4 py-2"
                                            style={{ color: headerText }}
                                        >
                                            {heading}
                                        </th>
                                    ),
                                )}
                            </tr>
                        </thead>
                        <tbody>
                            {assignments.map((assignment) => (
                                <tr
                                    key={assignment.id}
                                    style={{ borderTop: `1px solid ${border}` }}
                                >
                                    <td className="px-4 py-2" style={{ color: text }}>
                                        {assignment.agent_name}
                                    </td>
                                    <td className="px-4 py-2" style={{ color: text }}>
                                        {assignment.role === "vet"
                                            ? "Vet"
                                            : "Adviser"}
                                    </td>
                                    <td className="px-4 py-2" style={{ color: text }}>
                                        {assignment.officer_name}
                                    </td>
                                    <td className="px-4 py-2">
                                        <button
                                            onClick={() =>
                                                remove(assignment.id)
                                            }
                                            style={{
                                                background: "transparent",
                                                border: "none",
                                                color: "#B91C1C",
                                                textDecoration: "underline",
                                                cursor: "pointer",
                                                fontSize: "0.9375rem",
                                            }}
                                        >
                                            Remove
                                        </button>
                                    </td>
                                </tr>
                            ))}
                        </tbody>
                    </table>
                </div>
            )}
        </div>
    );
}
