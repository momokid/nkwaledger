import { useTheme } from "@/Layouts/AuthenticatedLayout";
import { cedis } from "@/lib/format";
import { Link } from "@inertiajs/react";

// shown while some of a loan is not yet accounted for; the button goes to the record form
export default function LoanUseBanner({ amount }: { amount: number }) {
    const { dark } = useTheme();

    return (
        <div
            className="mt-4 p-3"
            style={{ background: dark ? "rgba(180,83,9,0.15)" : "#FEF3C7", color: "#B45309", fontSize: "1.0625rem" }}
        >
            <p style={{ margin: 0 }}>You have not yet recorded how GHS {cedis(amount)} of your loan was used.</p>
            <Link
                href="/my-records/create"
                className="mt-2 inline-block"
                style={{ color: "#B45309", fontWeight: 600, textDecoration: "underline" }}
            >
                Record how I used it
            </Link>
        </div>
    );
}
