import { IconShoppingCart } from "@tabler/icons-react";

// every "coming soon" sub-item across every role's Marketplace group is built from this
// one function, rather than typed out by hand at each call site
export function disabledPlaceholder(label: string) {
    return { label, href: "#", ready: false };
}

// the one place the "Marketplace" group container itself gets built - each role supplies
// only its own sub-items, already in whichever nav-item shape that role's layout uses
export function buildMarketplaceNavGroup<T>(subItems: T[]) {
    return {
        label: "Marketplace",
        icon: IconShoppingCart,
        children: subItems,
    };
}
