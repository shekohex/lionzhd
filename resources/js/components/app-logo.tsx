import type { SharedData } from '@/types';
import { usePage } from '@inertiajs/react';
import AppLogoIcon from './app-logo-icon';

export default function AppLogo() {
    const { props } = usePage<SharedData>();
    return (
        <>
            <div className="text-sidebar-primary-foreground flex aspect-square size-12 items-center justify-center rounded-md sm:size-8">
                <AppLogoIcon className="size-12 fill-current text-white sm:size-8 dark:text-black" />
            </div>
            <div className="ml-1 grid flex-1 text-left text-sm">
                <span className="mb-0.5 truncate leading-none font-semibold">{props.name}</span>
            </div>
        </>
    );
}
