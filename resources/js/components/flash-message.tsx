import { usePage } from '@inertiajs/react';
import { useEffect, useState } from 'react';

export function FlashMessage() {
    const { flash } = usePage<{ flash: { success?: string; error?: string } }>().props;
    const [visible, setVisible] = useState(true);

    useEffect(() => {
        setVisible(true);
        const timer = setTimeout(() => setVisible(false), 4000);
        return () => clearTimeout(timer);
    }, [flash]);

    if (!visible || (!flash?.success && !flash?.error)) return null;

    return (
        <div className={`fixed top-4 right-4 z-50 rounded-lg px-4 py-3 text-sm font-medium shadow-lg ${
            flash?.success ? 'bg-green-600 text-white' : 'bg-red-600 text-white'
        }`}>
            {flash?.success ?? flash?.error}
        </div>
    );
}
