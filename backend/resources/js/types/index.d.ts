import { Config } from "ziggy-js";

export interface User {
    id: number;
    surname: string;
    first_name: string;
    other_name: string | null;
    phone: string;
    email: string | null;
    is_active: boolean;
}

export type PageProps<
    T extends Record<string, unknown> = Record<string, unknown>,
> = T & {
    auth: {
        user: User;
    };
    ziggy: Config & { location: string };
};
