import { cn } from '@/lib/utils';
import { ReactNode } from 'react';

interface EmptyStateProps {
    title: string;
    description?: string;
    icon?: ReactNode;
    action?: ReactNode;
    className?: string;
}

export default function EmptyState({ title, description, icon, action, className }: EmptyStateProps) {
    return (
        <div
            className={cn(
                'flex flex-col items-center justify-center rounded-lg border border-dashed px-4 py-16 text-center',
                className,
            )}
        >
            {icon && <div className="text-muted-foreground mb-4">{icon}</div>}
            <h3 className="text-lg font-medium">{title}</h3>
            {description && <p className="text-muted-foreground mt-1 max-w-md text-sm">{description}</p>}
            {action && <div className="mt-6">{action}</div>}
        </div>
    );
}
