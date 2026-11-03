# Next.js frontend

See the [project README](../README.md) for Docker setup, native development, and authentication details.

Copy `.env.example` to `.env.local`, run `npm ci`, then `npm run dev`. Laravel must be reachable at the server-only `LARAVEL_API_URL`.

Checks: `npm run lint` and `npm run build`.

The frontend uses [shadcn/ui](https://ui.shadcn.com/) as its only UI component system. Official component sources live in `src/components/ui`; app-specific compositions live in `src/components`. Configuration is in `components.json` (Base UI, Nova preset, Lucide icons, Tailwind v4).

Add components with `npx shadcn@latest add <component>`. Use existing shadcn components for all UI controls and states. ESLint rejects custom native control implementations outside the official component directory. Global CSS contains theme tokens and base styles; screen layouts use Tailwind utilities.

The workspace shell lives in src/components/layout, with dashboard compositions in src/components/dashboard. Server-only workspace-data.ts reads existing Laravel endpoints; workspace-actions.ts provides permission-checked record search. Display preferences use browser localStorage. Navigation and dashboard labels support EN/BM; existing forms remain English. See the root README for campus scope, refresh behavior and pending modules.
