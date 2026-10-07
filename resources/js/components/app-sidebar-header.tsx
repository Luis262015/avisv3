import { Breadcrumbs } from '@/components/breadcrumbs';
import { ThemeToggle } from '@/components/theme-toggle';
import { Badge } from '@/components/ui/badge';
import { SidebarTrigger } from '@/components/ui/sidebar';
import { type BreadcrumbItem as BreadcrumbItemType, type SharedData } from '@/types';
import { Link, usePage } from '@inertiajs/react';

export function AppSidebarHeader({ breadcrumbs = [] }: { breadcrumbs?: BreadcrumbItemType[] }) {
    const { tenant } = usePage<SharedData>().props;
    const plan = tenant?.subscription?.plan;

    return (
        <header className="flex h-14 shrink-0 items-center gap-2 border-b px-4 md:px-6">
            <div className="flex min-w-0 flex-1 items-center gap-2">
                <SidebarTrigger className="-ml-2 size-9" aria-label="Mostrar u ocultar el menú" />
                <Breadcrumbs breadcrumbs={breadcrumbs} />
            </div>

            {plan && (
                <Link href="/suscripcion" className="hidden sm:block" aria-label={`Plan ${plan.name}: ver plan y pagos`}>
                    <Badge variant={tenant?.subscription?.is_usable ? 'info' : 'danger'}>Plan {plan.name}</Badge>
                </Link>
            )}
            <ThemeToggle />
        </header>
    );
}
