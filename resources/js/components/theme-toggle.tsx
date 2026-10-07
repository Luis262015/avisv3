import { Button } from '@/components/ui/button';
import { useAppearance } from '@/hooks/use-appearance';
import { cn } from '@/lib/utils';
import { Moon, Sun } from 'lucide-react';

/**
 * Cambia entre claro y oscuro con un toque.
 *
 * El nombre accesible dice a qué se va a pasar, no en qué se está: es lo que
 * hace el botón.
 */
export function ThemeToggle({ className }: { className?: string }) {
    const { appearance, updateAppearance } = useAppearance();

    const oscuro =
        appearance === 'dark' || (appearance === 'system' && typeof window !== 'undefined' && window.matchMedia('(prefers-color-scheme: dark)').matches);

    return (
        <Button
            type="button"
            variant="ghost"
            size="icon"
            className={cn('size-9', className)}
            onClick={() => updateAppearance(oscuro ? 'light' : 'dark')}
            aria-label={oscuro ? 'Cambiar a tema claro' : 'Cambiar a tema oscuro'}
        >
            {oscuro ? <Sun aria-hidden="true" /> : <Moon aria-hidden="true" />}
        </Button>
    );
}
