export interface TourStep {
    target: string;
    text: string;
    image?: string;
}

const TOURS: Record<string, TourStep[]> = {
    farmer: [
        {
            target: "Dashboard",
            text: "This is your home. You see your money and your farm at a glance.",
            image: "/tour/farmer-dashboard.jpg",
        },
        { target: "My Farm", text: "Add your farms and farm units here.", image: "/tour/farmer-my-farm.jpg" },
        {
            target: "Record",
            text: "Tap here each time you earn, spend or lose something.",
            image: "/tour/farmer-record.jpg",
        },
        {
            target: "My Money",
            text: "Everything you earned and spent is listed here.",
            image: "/tour/farmer-my-money.jpg",
        },
        { target: "Credit", text: "See your loans and how you used them.", image: "/tour/farmer-credit.jpg" },
        {
            target: "Reports",
            image: "/tour/farmer-reports.jpg",
            text: "Print a report to show a lender or an agent. That is all. Open Help any time to see this again.",
        },
    ],
    agent: [
        { target: "Dashboard", text: "Your farmers and what needs your attention." },
        { target: "Farmers", text: "Find a farmer, open their books and add their farms." },
        { target: "Farm Units", text: "See the farms and animals of the farmers you serve." },
        { target: "Approvals", text: "Records and changes waiting for a yes or no." },
        { target: "Reports", text: "Reports for each farmer." },
    ],
};

export const tourFor = (role: string): TourStep[] => TOURS[role] ?? [];
