import { router } from "@inertiajs/react";
import { useState } from "react";
import { deleteDeviceKey, queueCounts } from "@/lib/offlineStore";

// signing out never destroys unsent records: the user's own items block it, and the
// shared device key is only deleted when nobody's items are left on the phone
export default function useSafeLogout(currentUser: string | null) {
    const [blocked, setBlocked] = useState(false);

    const logout = async () => {
        const { own, total } = await queueCounts(currentUser);

        if (own > 0) {
            setBlocked(true);

            return;
        }

        setBlocked(false);

        router.post(
            route("logout"),
            {},
            {
                onSuccess: () => {
                    if (total === 0) {
                        void deleteDeviceKey();
                    }

                    window.history.replaceState({ loggedOut: true }, "");
                },
            },
        );
    };

    return { logout, blocked };
}
