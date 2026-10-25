import { createInertiaApp } from '@inertiajs/react';
import { StrictMode } from 'react';
import { createRoot } from 'react-dom/client';

import { PrivateStateLayout } from '@/contexts/private-state-context';
import { ActivityTimeline } from '@/modules/timeline/components/activity-timeline';
import './ui-timeline-http-style.css';

type TimelineHttpPayload = {
    subject: {
        type: 'client';
        id: number;
        uuid: string;
        label: string;
    };
    authUser: {
        id: number;
        email_verified_at: string;
        must_change_password: boolean;
    };
};

const payloadElement = document.getElementById('ui003-page');

if (!payloadElement?.textContent) {
    throw new Error('Missing UI003 HTTP fixture page payload.');
}

const payload = JSON.parse(payloadElement.textContent) as TimelineHttpPayload;

function TimelineHttpPage() {
    return (
        <main className="mx-auto max-w-5xl space-y-6 p-6">
            <h1 className="text-xl font-semibold">
                UI003 — timeline com cursor HTTP
            </h1>
            <ActivityTimeline
                modelType={payload.subject.type}
                modelId={payload.subject.id}
                title={payload.subject.label}
                pageSize={50}
                readOnly
                allowAttachments={false}
            />
        </main>
    );
}

const pageComponent = Object.assign(TimelineHttpPage, {
    layout: [PrivateStateLayout],
});

await createInertiaApp({
    id: 'app',
    page: {
        component: 'Ui003TimelineHttpFixture',
        props: { auth: { user: payload.authUser }, errors: {} },
        url: '/__ui_timeline',
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
