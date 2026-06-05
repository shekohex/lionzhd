import '../css/app.css';

import { createInertiaApp } from '@inertiajs/react';
import { NuqsAdapter } from 'nuqs/adapters/react';
import type { ComponentType } from 'react';
import { initializeTheme } from './hooks/use-appearance';

const appName = import.meta.env.VITE_APP_NAME || 'Laravel';
const pages = import.meta.glob<{ default: ComponentType }>('./pages/**/*.tsx');

createInertiaApp({
    title: (title) => (title ? `${title} - ${appName}` : `${appName}`),
    resolve: async (name) => (await pages[`./pages/${name}.tsx`]()).default,
    strictMode: true,
    withApp: (app) => <NuqsAdapter fullPageNavigationOnShallowFalseUpdates>{app}</NuqsAdapter>,
    progress: {
        color: '#4B5563',
    },
});

// This will set light / dark mode on load...
initializeTheme();
