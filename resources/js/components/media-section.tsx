import { cn } from '@/lib/utils';
import { type ReactNode } from 'react';

interface MediaSectionProps {
    title: string;
    children: ReactNode;
    className?: string;
}

export default function MediaSection({ title, children, className }: MediaSectionProps) {
    return (
        <section className={cn('flex flex-col gap-4', className)}>
            <h2 className="text-2xl font-bold">{title}</h2>
            {children}
        </section>
    );
}
