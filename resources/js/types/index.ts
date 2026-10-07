import { LucideIcon } from 'lucide-react';

export interface Auth {
    user: User;
}

export interface BreadcrumbItem {
    title: string;
    href: string;
}

export interface NavGroup {
    title: string;
    items: NavItem[];
    /**
     * Si el grupo nace desplegado. Se reserva para lo que se usa a diario: con
     * nueve grupos abiertos a la vez, llegar al último exige desplazarse.
     * Lo que el usuario abra o cierre después manda sobre esto.
     */
    defaultOpen?: boolean;
}

export interface NavItem {
    title: string;
    url: string;
    icon?: LucideIcon | null;
    isActive?: boolean;
}

export interface PlanLimits {
    stores: number | null;
    users: number | null;
    products: number | null;
    invoices: number | null;
}

export interface Plan {
    id: number;
    slug: string;
    name: string;
    tagline: string | null;
    price_monthly: number;
    price_yearly: number;
    currency: string;
    trial_days: number;
    limits: PlanLimits;
    modules: string[];
    is_free: boolean;
    is_public: boolean;
    is_featured: boolean;
    is_active: boolean;
    sort_order: number;
    subscriptions_count?: number;
}

export type ModuleCatalog = Record<string, { label: string; description: string }>;

export interface Subscription {
    id: number;
    status: 'trialing' | 'active' | 'past_due' | 'cancelled';
    status_label: string;
    billing_cycle: 'monthly' | 'yearly';
    billing_cycle_label: string;
    ends_at: string | null;
    days_left: number | null;
    is_usable: boolean;
    is_expiring_soon: boolean;
    plan?: Plan;
}

export interface Payment {
    id: number;
    amount: number;
    currency: string;
    billing_cycle_label: string;
    method_label: string;
    status: 'pending' | 'approved' | 'rejected';
    status_label: string;
    reference: string | null;
    notes: string | null;
    rejection_reason: string | null;
    has_proof: boolean;
    period_start: string | null;
    period_end: string | null;
    created_at: string;
    reviewed_at: string | null;
    plan?: { id: number; name: string };
    tenant?: { id: number; name: string; slug: string };
    reviewer?: string | null;
}

/** La empresa en curso; null cuando el sistema corre sin arrendamiento. */
export interface TenantContext {
    name: string;
    slug: string;
    suspended: boolean;
    modules: string[];
    subscription: Subscription | null;
}

export interface SharedData {
    name: string;
    auth: Auth;
    flash: { success?: string; error?: string };
    tenant: TenantContext | null;
    [key: string]: unknown;
}

export interface User {
    id: number;
    name: string;
    email: string;
    avatar?: string;
    email_verified_at: string | null;
    created_at: string;
    updated_at: string;
    [key: string]: unknown;
}

export interface Auth {
    user: User;
    roles: string[];
    permissions: string[];
}

export interface PaginatedData<T> {
    data: T[];
    current_page: number;
    last_page: number;
    per_page: number;
    total: number;
    from: number;
    to: number;
    links: { url: string | null; label: string; active: boolean }[];
}
