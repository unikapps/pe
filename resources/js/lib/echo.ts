import { configureEcho } from '@laravel/echo-vue';

/**
 * Connect Echo to Reverb.
 *
 * In Docker (locally and on Coolify) the web server proxies /app to Reverb, so
 * the browser connects to the origin the page came from and the built image
 * works on any domain. `php artisan dev` runs Reverb on its own port instead,
 * which the VITE_REVERB_* variables in .env describe.
 *
 * The app key is public (every browser receives it), so the server renders it
 * into a meta tag rather than baking it into the bundle.
 */
export function initializeEcho(): void {
    const key = document.querySelector<HTMLMetaElement>(
        'meta[name="reverb-key"]',
    )?.content;

    if (!key) {
        return;
    }

    const devHost = import.meta.env.VITE_REVERB_HOST as string | undefined;
    const secure = devHost
        ? import.meta.env.VITE_REVERB_SCHEME === 'https'
        : window.location.protocol === 'https:';
    const port = devHost
        ? Number(import.meta.env.VITE_REVERB_PORT ?? 8080)
        : Number(window.location.port || (secure ? 443 : 80));

    configureEcho({
        broadcaster: 'reverb',
        key,
        wsHost: devHost ?? window.location.hostname,
        wsPort: port,
        wssPort: port,
        forceTLS: secure,
        enabledTransports: ['ws', 'wss'],
    });
}
