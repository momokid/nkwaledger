import { useTheme } from "@/Layouts/AuthenticatedLayout";
import { useEffect, useRef, useState } from "react";
import L from "leaflet";
import "leaflet/dist/leaflet.css";

export interface CommunityItem {
    id: number;
    name: string;
    district_id: number;
    latitude: number | null;
    longitude: number | null;
}

interface DistrictOption {
    id: number;
    name: string;
}

interface Props {
    items: CommunityItem[] | null;
    loading: boolean;
    districts: DistrictOption[];
    defaultDistrictId: string;
    permissions: { create: boolean; update: boolean; delete: boolean };
    onClose: () => void;
    onDistrictChange: (districtId: string) => void;
    onCreate: (
        name: string,
        districtId: string,
        latitude: number | null,
        longitude: number | null,
    ) => Promise<void>;
    onUpdate: (
        id: number,
        name: string,
        districtId: string,
        latitude: number | null,
        longitude: number | null,
    ) => Promise<void>;
    onDelete: (id: number) => Promise<void>;
}

const GHANA_CENTER: [number, number] = [7.9465, -1.0232];

export default function CommunityModal({
    items,
    loading,
    districts,
    defaultDistrictId,
    permissions,
    onClose,
    onDistrictChange,
    onCreate,
    onUpdate,
    onDelete,
}: Props) {
    const { dark } = useTheme();

    const [newName, setNewName] = useState("");
    const [newDistrictId, setNewDistrictId] = useState(defaultDistrictId);
    const [newLat, setNewLat] = useState("");
    const [newLng, setNewLng] = useState("");
    const [suggesting, setSuggesting] = useState(false);
    const [candidates, setCandidates] = useState<
        { latitude: number; longitude: number; label: string }[]
    >([]);
    const [createError, setCreateError] = useState<string | null>(null);
    const [creating, setCreating] = useState(false);

    const [editingId, setEditingId] = useState<number | null>(null);
    const [editName, setEditName] = useState("");
    const [editDistrictId, setEditDistrictId] = useState("");
    const [editLat, setEditLat] = useState("");
    const [editLng, setEditLng] = useState("");
    const [editError, setEditError] = useState<string | null>(null);
    const [busyId, setBusyId] = useState<number | null>(null);

    const mapRef = useRef<HTMLDivElement | null>(null);
    const mapInstance = useRef<L.Map | null>(null);
    const markerInstance = useRef<L.Marker | null>(null);
    const requestSeq = useRef(0);

    useEffect(() => {
        if (!mapRef.current || mapInstance.current) return;

        const map = L.map(mapRef.current, { zoomControl: false }).setView(
            GHANA_CENTER,
            7,
        );
        L.tileLayer("https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png", {
            attribution: "&copy; OpenStreetMap contributors",
        }).addTo(map);

        const marker = L.marker(GHANA_CENTER, { draggable: true }).addTo(map);
        marker.on("dragend", () => {
            const pos = marker.getLatLng();
            setNewLat(pos.lat.toFixed(6));
            setNewLng(pos.lng.toFixed(6));
        });

        mapInstance.current = map;
        markerInstance.current = marker;

        // the modal renders inside a fixed overlay, so Leaflet measures a
        // zero-size container on first paint unless nudged after layout settles
        setTimeout(() => map.invalidateSize(), 50);

        return () => {
            map.remove();
            mapInstance.current = null;
            markerInstance.current = null;
        };
    }, []);

    const moveMarker = (lat: number, lng: number) => {
        if (!mapInstance.current || !markerInstance.current) return;
        markerInstance.current.setLatLng([lat, lng]);
        mapInstance.current.panTo([lat, lng]);
    };

    useEffect(() => {
        const lat = parseFloat(newLat);
        const lng = parseFloat(newLng);
        if (!isNaN(lat) && !isNaN(lng)) {
            moveMarker(lat, lng);
        }
    }, [newLat, newLng]);

    useEffect(() => {
        if (!newName.trim() || !newDistrictId || editingId !== null) {
            setCandidates([]);
            return;
        }

        const seq = ++requestSeq.current;
        setSuggesting(true);

        const timer = setTimeout(async () => {
            try {
                const response = await fetch(
                    `/admin/communities/suggest-location?name=${encodeURIComponent(newName)}&district_id=${newDistrictId}`,
                    { headers: { Accept: "application/json" } },
                );
                if (!response.ok) return;
                const data = await response.json();
                // ignore a stale response if the admin kept typing in the meantime
                if (seq !== requestSeq.current) return;
                setCandidates(data.candidates ?? []);
            } finally {
                if (seq === requestSeq.current) setSuggesting(false);
            }
        }, 600);

        return () => clearTimeout(timer);
    }, [newName, newDistrictId]);

    const chooseCandidate = (candidate: {
        latitude: number;
        longitude: number;
        label: string;
    }) => {
        setNewLat(String(candidate.latitude));
        setNewLng(String(candidate.longitude));
        setCandidates([]);
    };

    const overlay = "rgba(0,0,0,0.5)";
    const surface = dark ? "#1F2937" : "#FFFFFF";
    const border = dark ? "#374151" : "#E5E7EB";
    const text = dark ? "#F9FAFB" : "#111827";
    const textSecondary = dark ? "#9CA3AF" : "#6B7280";
    const inputBorder = dark ? "#4B5563" : "#9CA3AF";
    const inputBg = dark ? "#111827" : "#FFFFFF";

    const inputStyle = {
        border: `1px solid ${inputBorder}`,
        background: inputBg,
        color: text,
        padding: "8px 10px",
        fontSize: "15px",
        outline: "none",
        fontFamily: "inherit",
        width: "100%",
    };

    const labelStyle = {
        display: "block",
        fontSize: "14px",
        fontWeight: 600,
        color: text,
        marginBottom: "5px",
    };

    const errorTextStyle = {
        color: "#DC2626",
        fontSize: "13px",
        marginTop: "4px",
    };

    const submitCreate = async () => {
        if (!newName.trim()) {
            setCreateError("Name is required.");
            return;
        }
        if (!newDistrictId) {
            setCreateError("Please select a district.");
            return;
        }
        setCreateError(null);
        setCreating(true);
        const lat = newLat ? parseFloat(newLat) : null;
        const lng = newLng ? parseFloat(newLng) : null;
        await onCreate(newName, newDistrictId, lat, lng);
        setNewName("");
        setNewLat("");
        setNewLng("");
        setCreating(false);
    };

    const startEdit = (item: CommunityItem) => {
        setEditingId(item.id);
        setEditName(item.name);
        setEditDistrictId(String(item.district_id));
        setEditLat(item.latitude !== null ? String(item.latitude) : "");
        setEditLng(item.longitude !== null ? String(item.longitude) : "");
        setEditError(null);
    };

    const cancelEdit = () => {
        setEditingId(null);
        setEditError(null);
    };

    const submitEdit = async (id: number) => {
        if (!editName.trim()) {
            setEditError("Name is required.");
            return;
        }
        setBusyId(id);
        const lat = editLat ? parseFloat(editLat) : null;
        const lng = editLng ? parseFloat(editLng) : null;
        await onUpdate(id, editName, editDistrictId, lat, lng);
        setBusyId(null);
        setEditingId(null);
    };

    const remove = async (id: number) => {
        if (
            !window.confirm(
                "Delete this community? This cannot be undone from here.",
            )
        ) {
            return;
        }
        setBusyId(id);
        await onDelete(id);
        setBusyId(null);
    };

    return (
        <div
            onClick={onClose}
            style={{
                position: "fixed",
                inset: 0,
                background: overlay,
                display: "flex",
                alignItems: "center",
                justifyContent: "center",
                zIndex: 50,
            }}
        >
            <div
                onClick={(event) => event.stopPropagation()}
                style={{
                    background: surface,
                    border: `1px solid ${border}`,
                    padding: "20px",
                    width: "90%",
                    maxWidth: "520px",
                    maxHeight: "88vh",
                    overflowY: "auto",
                }}
            >
                <div className="flex justify-between items-center mb-4">
                    <p
                        style={{
                            fontSize: "20px",
                            fontWeight: 700,
                            color: text,
                        }}
                    >
                        Communities
                    </p>
                    <button
                        onClick={onClose}
                        style={{
                            background: "none",
                            border: "none",
                            cursor: "pointer",
                            color: textSecondary,
                            fontSize: "20px",
                        }}
                    >
                        ×
                    </button>
                </div>

                {loading ? (
                    <p style={{ color: textSecondary, fontSize: "15px" }}>
                        Loading…
                    </p>
                ) : (
                    (items ?? []).map((item) => {
                        const isEditing = editingId === item.id;

                        return (
                            <div
                                key={item.id}
                                style={{
                                    padding: "10px 0",
                                    borderBottom: `1px solid ${border}`,
                                }}
                            >
                                {isEditing ? (
                                    <div>
                                        <input
                                            style={{
                                                ...inputStyle,
                                                marginBottom: "8px",
                                            }}
                                            value={editName}
                                            onChange={(event) =>
                                                setEditName(event.target.value)
                                            }
                                        />
                                        <select
                                            style={{
                                                ...inputStyle,
                                                marginBottom: "8px",
                                            }}
                                            value={editDistrictId}
                                            onChange={(event) =>
                                                setEditDistrictId(
                                                    event.target.value,
                                                )
                                            }
                                        >
                                            {districts.map((district) => (
                                                <option
                                                    key={district.id}
                                                    value={district.id}
                                                >
                                                    {district.name}
                                                </option>
                                            ))}
                                        </select>
                                        <div
                                            style={{
                                                display: "flex",
                                                gap: "8px",
                                                marginBottom: "8px",
                                            }}
                                        >
                                            <input
                                                style={inputStyle}
                                                placeholder="Latitude"
                                                value={editLat}
                                                onChange={(event) =>
                                                    setEditLat(
                                                        event.target.value,
                                                    )
                                                }
                                            />
                                            <input
                                                style={inputStyle}
                                                placeholder="Longitude"
                                                value={editLng}
                                                onChange={(event) =>
                                                    setEditLng(
                                                        event.target.value,
                                                    )
                                                }
                                            />
                                        </div>
                                        {editError && (
                                            <p style={errorTextStyle}>
                                                {editError}
                                            </p>
                                        )}
                                        <div
                                            style={{
                                                display: "flex",
                                                gap: "10px",
                                            }}
                                        >
                                            <button
                                                onClick={() =>
                                                    submitEdit(item.id)
                                                }
                                                disabled={busyId === item.id}
                                                style={{
                                                    color: "#1D9E75",
                                                    background: "transparent",
                                                    border: "none",
                                                    fontWeight: 600,
                                                    cursor: "pointer",
                                                    fontSize: "14px",
                                                }}
                                            >
                                                Save
                                            </button>
                                            <button
                                                onClick={cancelEdit}
                                                style={{
                                                    color: textSecondary,
                                                    background: "transparent",
                                                    border: "none",
                                                    cursor: "pointer",
                                                    fontSize: "14px",
                                                }}
                                            >
                                                Cancel
                                            </button>
                                        </div>
                                    </div>
                                ) : (
                                    <div className="flex justify-between items-center">
                                        <div>
                                            <p
                                                style={{
                                                    color: text,
                                                    fontSize: "15px",
                                                    margin: 0,
                                                }}
                                            >
                                                {item.name}
                                            </p>
                                            <p
                                                style={{
                                                    color: textSecondary,
                                                    fontSize: "12px",
                                                    margin: 0,
                                                }}
                                            >
                                                {item.latitude !== null
                                                    ? `${item.latitude}, ${item.longitude}`
                                                    : "No location set"}
                                            </p>
                                        </div>
                                        <div
                                            style={{
                                                display: "flex",
                                                gap: "10px",
                                            }}
                                        >
                                            {permissions.update && (
                                                <button
                                                    onClick={() =>
                                                        startEdit(item)
                                                    }
                                                    style={{
                                                        color: "#1D9E75",
                                                        background:
                                                            "transparent",
                                                        border: "none",
                                                        fontWeight: 600,
                                                        cursor: "pointer",
                                                        fontSize: "14px",
                                                    }}
                                                >
                                                    Edit
                                                </button>
                                            )}
                                            {permissions.delete && (
                                                <button
                                                    onClick={() =>
                                                        remove(item.id)
                                                    }
                                                    disabled={
                                                        busyId === item.id
                                                    }
                                                    style={{
                                                        color: "#DC2626",
                                                        background:
                                                            "transparent",
                                                        border: "none",
                                                        cursor: "pointer",
                                                        fontSize: "14px",
                                                    }}
                                                >
                                                    Delete
                                                </button>
                                            )}
                                        </div>
                                    </div>
                                )}
                            </div>
                        );
                    })
                )}

                {permissions.create && (
                    <div style={{ marginTop: "16px" }}>
                        <label style={labelStyle}>District</label>
                        <select
                            style={{ ...inputStyle, marginBottom: "12px" }}
                            value={newDistrictId}
                            onChange={(event) => {
                                setNewDistrictId(event.target.value);
                                onDistrictChange(event.target.value);
                            }}
                        >
                            <option value="">Select a district</option>
                            {districts.map((district) => (
                                <option key={district.id} value={district.id}>
                                    {district.name}
                                </option>
                            ))}
                        </select>

                        <label style={labelStyle}>Community name</label>
                        <input
                            style={{ ...inputStyle, marginBottom: "6px" }}
                            value={newName}
                            onChange={(event) => setNewName(event.target.value)}
                            placeholder="e.g. Anyinasu"
                        />
                        <p
                            style={{
                                fontSize: "13px",
                                color: textSecondary,
                                marginBottom: "6px",
                            }}
                        >
                            {suggesting
                                ? "Looking up location…"
                                : "Drag the pin to adjust the exact spot."}
                        </p>

                        {candidates.length > 0 && (
                            <div
                                style={{
                                    border: `1px solid ${border}`,
                                    marginBottom: "12px",
                                }}
                            >
                                {candidates.map((candidate, index) => (
                                    <button
                                        key={`${candidate.label}-${index}`}
                                        type="button"
                                        onClick={() =>
                                            chooseCandidate(candidate)
                                        }
                                        style={{
                                            display: "block",
                                            width: "100%",
                                            textAlign: "left",
                                            padding: "8px 10px",
                                            background: "transparent",
                                            border: "none",
                                            borderBottom:
                                                index === candidates.length - 1
                                                    ? "none"
                                                    : `1px solid ${border}`,
                                            color: text,
                                            fontSize: "14px",
                                            cursor: "pointer",
                                        }}
                                    >
                                        {candidate.label}
                                    </button>
                                ))}
                            </div>
                        )}

                        <div
                            ref={mapRef}
                            style={{
                                height: "220px",
                                border: `1px solid ${border}`,
                                marginBottom: "12px",
                            }}
                        />

                        <div
                            style={{
                                display: "flex",
                                gap: "8px",
                                marginBottom: "12px",
                            }}
                        >
                            <input
                                style={inputStyle}
                                placeholder="Latitude"
                                value={newLat}
                                onChange={(event) =>
                                    setNewLat(event.target.value)
                                }
                            />
                            <input
                                style={inputStyle}
                                placeholder="Longitude"
                                value={newLng}
                                onChange={(event) =>
                                    setNewLng(event.target.value)
                                }
                            />
                        </div>

                        {createError && (
                            <p style={errorTextStyle}>{createError}</p>
                        )}

                        <button
                            onClick={submitCreate}
                            disabled={creating}
                            style={{
                                background: "#1D9E75",
                                color: "#FFFFFF",
                                border: "none",
                                padding: "9px 20px",
                                fontSize: "15px",
                                fontWeight: 600,
                                cursor: creating ? "not-allowed" : "pointer",
                                opacity: creating ? 0.7 : 1,
                                width: "100%",
                            }}
                        >
                            Add community
                        </button>
                    </div>
                )}
            </div>
        </div>
    );
}
