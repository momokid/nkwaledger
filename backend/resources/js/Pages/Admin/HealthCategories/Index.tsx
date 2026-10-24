import AdminLayout from "@/Layouts/AdminLayout";
import { useTheme } from "@/Layouts/AuthenticatedLayout";
import TableSkeletonRows from "@/Components/Admin/TableSkeletonRows";
import { router, usePage } from "@inertiajs/react";
import { PageProps } from "@/types";
import { FormEvent, useState } from "react";

interface CategoryData {
    uuid: string;
    name: string;
    is_active: boolean;
}

interface GroupData {
    name: string;
    categories: CategoryData[];
}

interface Props extends PageProps {
    groups: GroupData[];
}

export default function Index({ groups }: Props) {
    return (
        <AdminLayout title="Health categories">
            <IndexContent groups={groups} />
        </AdminLayout>
    );
}

type ContentProps = Pick<Props, "groups">;

function IndexContent({ groups }: ContentProps) {
    const { errors } = usePage<Props>().props;
    const { dark } = useTheme();
    const [active, setActive] = useState(groups[0]?.name ?? "");
    const [loading, setLoading] = useState(false);
    const [newName, setNewName] = useState("");
    const [editing, setEditing] = useState<string | null>(null);
    const [editName, setEditName] = useState("");
    const [failed, setFailed] = useState(false);

    const surface = dark ? "#1F2937" : "#FFFFFF";
    const border = dark ? "#374151" : "#E5E7EB";
    const text = dark ? "#F9FAFB" : "#111827";
    const textSecondary = dark ? "#9CA3AF" : "#6B7280";
    const headerBg = dark ? "rgba(29,158,117,0.15)" : "#EAF5F0";

    const group = groups.find((g) => g.name === active) ?? groups[0];

    // every save goes through here: the list reloads in place and shows skeletons meanwhile
    const options = (onSuccess?: () => void) => ({
        preserveScroll: true,
        onStart: () => {
            setLoading(true);
            setFailed(false);
        },
        onSuccess,
        onError: (e: Record<string, string>) => setFailed(!e.name),
        onFinish: () => setLoading(false),
    });

    const add = (event: FormEvent) => {
        event.preventDefault();
        router.post(
            route("admin.health-categories.store"),
            { farm_category: group.name, name: newName },
            options(() => setNewName("")),
        );
    };

    const saveName = (uuid: string) => {
        router.patch(
            route("admin.health-categories.update", uuid),
            { name: editName },
            options(() => setEditing(null)),
        );
    };

    const setOn = (uuid: string, isActive: boolean) =>
        router.patch(
            route("admin.health-categories.update", uuid),
            { is_active: isActive },
            options(),
        );

    const move = (uuid: string, direction: "up" | "down") =>
        router.patch(
            route("admin.health-categories.move", uuid),
            { direction },
            options(),
        );

    const link = (color: string, disabled = false) => ({
        color,
        background: "transparent",
        border: "none",
        fontWeight: 600,
        fontSize: "0.9375rem",
        cursor: disabled ? "not-allowed" : "pointer",
        opacity: disabled ? 0.4 : 1,
    });

    const input = {
        border: `1px solid ${dark ? "#4B5563" : "#9CA3AF"}`,
        background: dark ? "#111827" : "#FFFFFF",
        color: text,
        padding: "8px 10px",
        fontSize: "1.25rem",
        outline: "none",
        fontFamily: "inherit",
    };

    const fieldError = (key: string) =>
        errors?.[key] && (
            <p style={{ color: "#DC2626", fontSize: "1.125rem", marginTop: 4 }}>
                {errors[key]}
            </p>
        );

    return (
        <>
            <p className="mb-4" style={{ color: textSecondary }}>
                The problems farmers can report, for each farm category.
            </p>

            <div className="flex gap-2 mb-4 flex-wrap">
                {groups.map((g) => (
                    <button
                        key={g.name}
                        onClick={() => {
                            setActive(g.name);
                            setEditing(null);
                        }}
                        style={{
                            padding: "8px 16px",
                            fontSize: "1.25rem",
                            fontWeight: 600,
                            border: `1px solid ${border}`,
                            background: g.name === active ? "#1D9E75" : surface,
                            color: g.name === active ? "#FFFFFF" : text,
                            cursor: "pointer",
                        }}
                    >
                        {g.name}
                    </button>
                ))}
            </div>

            {group && (
                <>
                    <form
                        onSubmit={add}
                        className="mb-6"
                        style={{
                            background: surface,
                            border: `1px solid ${border}`,
                            padding: "20px",
                            display: "flex",
                            gap: "12px",
                            alignItems: "flex-end",
                            flexWrap: "wrap",
                        }}
                    >
                        <div>
                            <label
                                style={{
                                    display: "block",
                                    fontSize: "1.125rem",
                                    fontWeight: 600,
                                    color: text,
                                    marginBottom: 6,
                                }}
                            >
                                Category name
                            </label>
                            <input
                                type="text"
                                value={newName}
                                onChange={(e) => setNewName(e.target.value)}
                                style={{ ...input, width: "260px" }}
                            />
                            {!editing && fieldError("name")}
                            {!editing && fieldError("farm_category")}
                        </div>
                        <button
                            type="submit"
                            disabled={loading}
                            style={{
                                background: "#1D9E75",
                                color: "#FFFFFF",
                                border: "none",
                                padding: "10px 20px",
                                fontSize: "1.25rem",
                                fontWeight: 600,
                                cursor: loading ? "not-allowed" : "pointer",
                                opacity: loading ? 0.7 : 1,
                            }}
                        >
                            Add category
                        </button>
                    </form>

                    {failed && (
                        <p style={{ color: "#DC2626", marginBottom: 12 }}>
                            Could not save. Try again.
                        </p>
                    )}

                    <div
                        className="overflow-x-auto"
                        style={{ background: surface, border: `1px solid ${border}` }}
                    >
                        <table className="min-w-full" style={{ fontSize: "1.25rem" }}>
                            <thead>
                                <tr style={{ background: headerBg }}>
                                    {["Category name", "Status", "Actions"].map((h) => (
                                        <th
                                            key={h}
                                            className="text-left px-4 py-3"
                                            style={{ color: "#1D9E75", fontWeight: 700 }}
                                        >
                                            {h}
                                        </th>
                                    ))}
                                </tr>
                            </thead>
                            <tbody>
                                {loading && <TableSkeletonRows columns={3} />}

                                {!loading && group.categories.length === 0 && (
                                    <tr>
                                        <td
                                            colSpan={3}
                                            className="px-4 py-6 text-center"
                                            style={{ color: textSecondary }}
                                        >
                                            No categories yet for this farm category.
                                        </td>
                                    </tr>
                                )}

                                {!loading &&
                                    group.categories.map((c, i, all) => (
                                        <tr key={c.uuid} style={{ borderTop: `1px solid ${border}` }}>
                                            <td className="px-4 py-3" style={{ color: text }}>
                                                {editing === c.uuid ? (
                                                    <>
                                                        <input
                                                            type="text"
                                                            value={editName}
                                                            onChange={(e) => setEditName(e.target.value)}
                                                            style={{ ...input, width: "100%" }}
                                                        />
                                                        {fieldError("name")}
                                                    </>
                                                ) : (
                                                    c.name
                                                )}
                                            </td>
                                            <td className="px-4 py-3">
                                                <span
                                                    style={{
                                                        fontWeight: 600,
                                                        color: c.is_active ? "#1D9E75" : textSecondary,
                                                    }}
                                                >
                                                    {c.is_active ? "Active" : "Off"}
                                                </span>
                                            </td>
                                            <td className="px-4 py-3">
                                                <div style={{ display: "flex", gap: 12, flexWrap: "wrap" }}>
                                                    {editing === c.uuid ? (
                                                        <>
                                                            <button onClick={() => saveName(c.uuid)} style={link("#1D9E75")}>
                                                                Save
                                                            </button>
                                                            <button onClick={() => setEditing(null)} style={link(textSecondary)}>
                                                                Cancel
                                                            </button>
                                                        </>
                                                    ) : (
                                                        <>
                                                            <button
                                                                onClick={() => {
                                                                    setEditing(c.uuid);
                                                                    setEditName(c.name);
                                                                }}
                                                                style={link("#1D9E75")}
                                                            >
                                                                Rename
                                                            </button>
                                                            <button
                                                                disabled={i === 0}
                                                                onClick={() => move(c.uuid, "up")}
                                                                style={link(textSecondary, i === 0)}
                                                            >
                                                                Move up
                                                            </button>
                                                            <button
                                                                disabled={i === all.length - 1}
                                                                onClick={() => move(c.uuid, "down")}
                                                                style={link(textSecondary, i === all.length - 1)}
                                                            >
                                                                Move down
                                                            </button>
                                                            <button
                                                                onClick={() => setOn(c.uuid, !c.is_active)}
                                                                title={
                                                                    c.is_active
                                                                        ? "Farmers will no longer see this category. Old reports keep it."
                                                                        : undefined
                                                                }
                                                                style={link(c.is_active ? "#B45309" : "#1D9E75")}
                                                            >
                                                                {c.is_active ? "Switch off" : "Switch on"}
                                                            </button>
                                                        </>
                                                    )}
                                                </div>
                                            </td>
                                        </tr>
                                    ))}
                            </tbody>
                        </table>
                    </div>
                </>
            )}
        </>
    );
}
