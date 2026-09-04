# WPForge Site Control (UIUX)

A modern dark control panel for the [WPForge](https://github.com/avangardistic/WPForge)
WordPress API — built with Vite, React, TypeScript, and Tailwind CSS.

## Getting started

```bash
npm install
npm run dev      # http://localhost:5173
npm run build    # production build into dist/
npm run lint
```

## Live API connection

The dashboard talks directly to a WPForge REST API (e.g. `https://<site>/wp-json/wpforge/v1`):

1. Click **Connect API** in the top bar.
2. Enter the site URL, your WordPress username, and an **Application Password**
   (WordPress → Users → Profile → Application Passwords). Credentials are sent
   only to your site and stored in `localStorage` for the current browser.
3. Once connected you get live data:
   - Site header — real WP / PHP / plugin versions from `GET /status`
   - Health cards — system health and database checks from `GET /health`
   - Capability rail — per-area grants for the signed-in user from `GET /capabilities`
   - **Content**, **Media**, and **Plugins & Themes** views from `GET /posts`, `/media`, `/plugins`
   - **Audit Log** stream, polled every few seconds from `GET /logs`

When disconnected the app falls back to simulated demo data so the layout can be
previewed without a site. WordPress sends CORS headers allowing these calls from
any origin; the WPForge plugin must be installed and active on the target site.

## Structure

```
src/
├── App.tsx                # state orchestration + view switching
├── components/            # TopBar, StatusRail, panels, connection modal, stream
├── data/                  # demo defaults (site, capabilities)
├── hooks/
│   ├── useActivityStream.ts  # simulated demo stream (disconnected mode)
│   └── useWpForge.ts         # live connection, polling, data fetch
├── lib/
│   ├── api.ts             # typed WPForge REST client + credential persistence
│   └── format.ts          # relative time, audit-log → activity mapping
└── types/activity.ts
```
