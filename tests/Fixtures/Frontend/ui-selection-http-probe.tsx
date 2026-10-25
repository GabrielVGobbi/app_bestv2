import { createInertiaApp } from '@inertiajs/react';
import { StrictMode } from 'react';
import { createRoot } from 'react-dom/client';

import { PrivateStateLayout } from '@/contexts/private-state-context';
import UsersIndex from '@/pages/app/users/index';
import './ui-timeline-http-style.css';

type SelectionHttpPayload = {
    authUser: {
        id: number;
        email_verified_at: string;
        must_change_password: boolean;
    };
    can: { create: boolean; update: boolean; delete: boolean };
};

const payloadElement = document.getElementById('ui004-page');

if (!payloadElement?.textContent) {
    throw new Error('Missing UI004 HTTP fixture page payload.');
}

const payload = JSON.parse(payloadElement.textContent) as SelectionHttpPayload;
const pageComponent = Object.assign(UsersIndex, {
    layout: [PrivateStateLayout],
});

await createInertiaApp({
    id: 'app',
    page: {
        component: 'Ui004SelectionHttpFixture',
        props: {
            auth: { user: payload.authUser },
            can: payload.can,
            errors: {},
        },
        url: '/__ui_selection',
        version: null,
        clearHistory: false,
        encryptHistory: false,
        flash: {},
        rememberedState: {},
    },
    resolve: () => pageComponent,
    setup: ({ el, App, props }) => {
        createRoot(el).render(
            <StrictMode>
                <App {...props} />
            </StrictMode>,
        );
    },
});
