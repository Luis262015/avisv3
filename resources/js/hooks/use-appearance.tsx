import { useState } from 'react';

export type Appearance = 'light' | 'dark' | 'system';

const prefersDark = () => window.matchMedia('(prefers-color-scheme: dark)').matches;

const applyTheme = (appearance: Appearance) => {
    const isDark = appearance === 'dark' || (appearance === 'system' && prefersDark());

    document.documentElement.classList.toggle('dark', isDark);
};

const mediaQuery = window.matchMedia('(prefers-color-scheme: dark)');

const handleSystemThemeChange = () => {
    const currentAppearance = localStorage.getItem('appearance') as Appearance;
    applyTheme(currentAppearance || 'system');
};

export function initializeTheme() {
    const savedAppearance = (localStorage.getItem('appearance') as Appearance) || 'system';

    applyTheme(savedAppearance);

    // Add the event listener for system theme changes...
    mediaQuery.addEventListener('change', handleSystemThemeChange);
}

export function useAppearance() {
    // Se lee al montar, no en un efecto: así el primer pintado ya sabe el tema
    // y el botón no parpadea entre un icono y otro.
    const [appearance, setAppearance] = useState<Appearance>(() => (localStorage.getItem('appearance') as Appearance | null) || 'system');

    const updateAppearance = (mode: Appearance) => {
        setAppearance(mode);
        localStorage.setItem('appearance', mode);
        applyTheme(mode);
    };

    // El aviso de cambio de tema del sistema lo registra `initializeTheme` una
    // sola vez y no se retira aquí: este hook vive en la cabecera, que se
    // desmonta en cada navegación.
    return { appearance, updateAppearance };
}
