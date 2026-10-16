import AuthenticatedLayout from "@/Layouts/AuthenticatedLayout";
import LoansList, { LoansListProps } from "@/Components/LoansList";

export default function Index(props: LoansListProps) {
    return (
        <AuthenticatedLayout title="Loans">
            <LoansList base="/agent/loans" {...props} />
        </AuthenticatedLayout>
    );
}
