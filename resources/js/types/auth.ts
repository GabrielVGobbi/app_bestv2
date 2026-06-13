import type { Role } from './app/role';

export type User = {
    id: number;
    name: string;
    email: string;
    avatar?: string;
    email_verified_at: string | null;
    must_change_password?: boolean;
    two_factor_enabled?: boolean;
    roles?: Pick<Role, 'id' | 'name' | 'slug'>[];
    created_at: string;
    updated_at: string;
    [key: string]: unknown;
};

export type Auth = {
    user: User;
};

export type TwoFactorSetupData = {
    svg: string;
    url: string;
};

export type TwoFactorSecretKey = {
    secretKey: string;
};
