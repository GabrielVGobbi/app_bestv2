import { createInertiaApp } from '@inertiajs/react';
import { createRoot } from 'react-dom/client';
import { PrivateStateLayout } from '@/contexts/private-state-context';
import { FileAttachments } from '@/modules/uploads/components/file-attachments';
import './ui-uploads-http-style.css';

// The PHP profile supplies one authenticated synthetic actor and one fixed parent.
// Requests use the production Axios transport, API, authorization and database.
const payload = JSON.parse(document.getElementById('ui002-page')!.textContent!);

function UploadsHttpPage() {
    return (
        <main className="mx-auto max-w-5xl space-y-8 p-6">
            <h1 className="text-xl font-semibold">
                UI002 — anexos privados isolados
            </h1>
            <p>Dois componentes reais, com coleções fixas do mesmo registro.</p>
            <section aria-label="Coleção documentos">
                <FileAttachments
                    modelType="client"
                    modelId={payload.clientId}
                    collection="documents"
                    tag="evidence"
                    title="Documentos"
                    readOnly
                />
            </section>
            <section aria-label="Coleção recibos">
                <FileAttachments
                    modelType="client"
                    modelId={payload.clientId}
                    collection="receipts"
                    tag="evidence"
                    title="Recibos"
                    readOnly
                />
            </section>
        </main>
    );
}

await createInertiaApp({
    id: 'app',
    page: {
        component: 'Ui002UploadsHttpFixture',
        props: { auth: { user: payload.user }, errors: {} },
        url: '/__ui_uploads',
        version: null,
        clearHistory: false,
        encryptHistory: false,
        flash: {},
        rememberedState: {},
    },
    resolve: () => UploadsHttpPage,
    setup: ({ el, App, props }) => {
        createRoot(el).render(
            <App {...props} defaultLayout={() => [PrivateStateLayout]} />,
        );
    },
});
