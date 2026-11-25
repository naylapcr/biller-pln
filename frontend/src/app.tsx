import './assets/styles/app.css';

import { createInertiaApp } from '@inertiajs/react';
import { resolvePageComponent } from 'laravel-vite-plugin/inertia-helpers';
import { createRoot } from 'react-dom/client';

const appName = import.meta.env.VITE_APP_NAME || 'SIPERMON Kinerja Biller';

createInertiaApp({
    title: (title) => (title ? `${title} · ${appName}` : appName),
    // Halaman dipecah per peran: pages/Auth, pages/Biller, pages/Koordinator, pages/SuperAdmin, pages/Umum.
    // Setiap halaman memasang layout perannya sendiri lewat Page.layout (persistent layout).
    resolve: (name) => resolvePageComponent(`./pages/${name}.tsx`, import.meta.glob('./pages/**/*.tsx')),
    setup({ el, App, props }) {
        createRoot(el).render(<App {...props} />);
    },
    progress: {
        color: '#F5C400',
        showSpinner: false,
    },
});
