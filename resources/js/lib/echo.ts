import Echo from 'laravel-echo';
import Pusher from 'pusher-js';

declare global {
    interface Window {
        Pusher?: typeof Pusher;
        Echo?: Echo<'reverb'>;
    }
}

const realtimeEnabled = import.meta.env.VITE_REVERB_ENABLED === 'true';

let echo: Echo<'reverb'> | null = null;

if (realtimeEnabled && typeof window !== 'undefined') {
    window.Pusher = Pusher;

    echo = new Echo({
        broadcaster: 'reverb',
        key: import.meta.env.VITE_REVERB_APP_KEY,
        wsHost: import.meta.env.VITE_REVERB_HOST,
        wsPort: Number(import.meta.env.VITE_REVERB_PORT ?? 80),
        wssPort: Number(import.meta.env.VITE_REVERB_PORT ?? 443),
        forceTLS: (import.meta.env.VITE_REVERB_SCHEME ?? 'https') === 'https',
        enabledTransports: ['ws', 'wss'],
    });

    window.Echo = echo;
}

export default echo;
