import AdminLayout from "@/Layouts/AdminLayout";

export default function Dashboard() {
    return (
        <AdminLayout title="Dashboard">
            <div
                className="p-6 bg-white border"
                style={{ borderColor: "#E5E7EB" }}
            >
                <h2
                    style={{
                        fontSize: "20px",
                        fontWeight: 700,
                        color: "#111827",
                    }}
                >
                    Welcome back
                </h2>
                <p
                    style={{
                        fontSize: "17px",
                        color: "#6B7280",
                        marginTop: "4px",
                    }}
                >
                    Farm types, farmer groups, and ledger accounts will show up
                    here as we build out their screens.
                </p>
            </div>
        </AdminLayout>
    );
}
