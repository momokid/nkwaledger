import AdminLayout from "@/Layouts/AdminLayout";
import LoansList, { LoansListProps } from "@/Components/LoansList";

export default function Index(props: LoansListProps) {
    return (
        <AdminLayout title="Loans">
            <LoansList base="/admin/loans" {...props} />
        </AdminLayout>
    );
}
