# Application fonts

Geist and Geist Mono are served locally through `resources/css/app.css`. Vite emits
versioned WOFF2 assets; no external font service or installed system font is needed.
Both files support normal weights 100–900. The existing system stacks remain as
fallbacks while fonts load (`font-display: swap`).

Source: [vercel/geist-font](https://github.com/vercel/geist-font/tree/e1f671c727f6de7e97c1e1d26dbb44f349c19459/packages/next/dist/fonts).
License: [SIL Open Font License](OFL.txt).

After changing font assets, run `npm run build` from the project root (or use the
existing Vite development server). Verify the login over HTTP with the browser's
rendered-font inspector: body text and controls must use Geist, including when
external font hosts are blocked. A computed `font-family` alone does not prove
which font rendered the text.
