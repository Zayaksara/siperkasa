export type Role = 'user' | 'admin' | 'pic';

export type User = {
    id: number;
    nim_nip: string;
    name: string;
    email: string | null;
    role: Role;
    is_active: boolean;
    avatar?: string;
    [key: string]: unknown;
};

export type Auth = {
    user: User;
};

export type Passkey = {
    id: number;
    name: string;
    authenticator: string | null;
    created_at_diff: string;
    last_used_at_diff: string | null;
};

export type TwoFactorConfigContent = {
    title: string;
    description: string;
    buttonText: string;
};
