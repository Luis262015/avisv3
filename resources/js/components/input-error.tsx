import { cn } from '@/lib/utils';
import { HTMLAttributes } from 'react';

export default function InputError({ message, className = '', ...props }: HTMLAttributes<HTMLParagraphElement> & { message?: string }) {
    return message ? (
        <p role="alert" {...props} className={cn('text-sm font-medium text-red-700', className)}>
            {message}
        </p>
    ) : null;
}
