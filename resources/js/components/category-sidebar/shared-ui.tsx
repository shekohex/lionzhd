import { cn } from '@/lib/utils';
import { type ManageCategoryRowProps } from './types';

export function ManageCategoryRow({ item, dragHandle, actions, muted = false, fixedLabel, active = false }: ManageCategoryRowProps) {
    return (
        <div
            className={cn(
                'flex items-start gap-3 rounded-md border bg-background px-3 py-2 transition-colors',
                active && 'border-primary ring-1 ring-primary/20',
                muted && !active && 'opacity-75',
            )}
        >
            {dragHandle}
            <div className="min-w-0 flex-1">
                <p className={cn('text-sm font-medium', active && 'text-primary')}>{item.name}</p>
                <div className="flex flex-wrap items-center gap-2 text-xs text-muted-foreground">
                    {fixedLabel ? <span>{fixedLabel}</span> : null}
                    {!item.canNavigate ? <span>No items currently</span> : null}
                </div>
            </div>
            <div className="flex shrink-0 items-center">
                {actions}
            </div>
        </div>
    );
}

export function iconButtonClass(active = false) {
    return cn(
        'rounded-md border p-1.5 text-muted-foreground transition-all duration-200',
        active ? 'border-primary/40 bg-primary/10 text-primary shadow-sm' : 'hover:bg-muted hover:text-foreground hover:border-muted-foreground/30',
        'disabled:opacity-50 disabled:cursor-not-allowed'
    );
}
