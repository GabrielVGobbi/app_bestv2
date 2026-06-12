export interface Address {
    id: number;
    uuid: string;
    name: string | null;
    full_address: string;
    zipcode: string | null;
    street: string | null;
    number: string | null;
    district: string | null;
    neighborhood: string | null;
    city: string | null;
    city_code: string | null;
    state: string | null;
    complement: string | null;
    country: string | null;
    state_registration: string | null;
    is_default: boolean;
    is_primary: boolean;
}
