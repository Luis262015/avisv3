import { Appearance, useAppearance } from '@/hooks/use-appearance';
import { cn } from '@/lib/utils';
import { LucideIcon, Monitor, Moon, Sun } from 'lucide-react';
import { HTMLAttributes } from 'react';

export default function AppearanceToggleTab({ className = '', ...props }: HTMLAttributes<HTMLDivElement>) {
    const { appearance, updateAppearance } = useAppearance();

    const tabs: { value: Appearance; icon: LucideIcon; label: string }[] = [
        { value: 'light', icon: Sun, label: 'Claro' },
        { value: 'dark', icon: Moon, label: 'Oscuro' },
        { value: 'system', icon: Monitor, label: 'Según el equipo' },
    ];

    return (
        <div role="radiogroup" aria-label="Tema" className={cn('bg-muted inline-flex flex-wrap gap-1 rounded-lg p-1', className)} {...props}>
            {tabs.map(({ value, icon: Icon, label }) => (
                <button
                    key={value}
                    type="button"
                    role="radio"
                    aria-checked={appearance === value}
                    onClick={() => updateAppearance(value)}
                    className={cn(
                        'flex min-h-10 items-center rounded-md px-3.5 py-1.5 transition-colors',
                        appearance === value
                            ? 'bg-card shadow-xs'
                            : 'text-neutral-500 hover:bg-neutral-200/60 hover:text-foreground',
                    )}
                >
                    <Icon className="-ml-1 h-4 w-4" aria-hidden="true" />
                    <span className="ml-1.5 text-sm">{label}</span>
                </button>
            ))}
        </div>
    );
}
