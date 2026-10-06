import { router } from "@inertiajs/react";
import { useState } from "react";
import { deleteDeviceKey, queueCounts } from "@/lib/offlineStore";
import { withQueueLock } from "@/lib/queueLock";

// signing out never destroys unsent records: the user's own sendable items block it, and the
// shared device key is only deleted when nobody's items are left on the phone
export default function useSafeLogout(currentUser: string | null) {
    const [blocked, setBlocked] = useState(false);

    const logout = async () => {
        let allowed = true;

        try {
            // check and delete in one turn, so no record can be saved in between
            allowed = await withQueueLock(async () => {
                const { own, total } = await queueCounts(currentUser);

                if (own > 0) {
                    return false;
                }

                if (total === 0) {
                    await deleteDeviceKey();
                }

                return true;
            });
        } catch {
            // the phone could not be checked: sign out, but delete nothing
        }

        if (!allowed) {
            setBlocked(true);

            return;
        }

        setBlocked(false);

        router.post(
            route("logout"),
            {},
            {
                onSuccess: () => window.history.replaceState({ loggedOut: true }, ""),
            },
        );
    };

    return { logout, blocked };
}
