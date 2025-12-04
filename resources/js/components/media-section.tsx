import { cn } from '@/lib/utils';
import { type ReactNode } from 'react';

interface MediaSectionProps {
    title: string;
    children: ReactNode;
    className?: string;
    action?: ReactNode;
}

export default function MediaSection({ title, children, className, action }: MediaSectionProps) {
    return (
        <section className={cn('flex flex-col gap-4', className)}>
            <div className="flex items-center justify-between">
                <h2 className="text-2xl font-bold">{title}</h2>
                {action}
            </div>
            {children}
        </section>
    );
}
