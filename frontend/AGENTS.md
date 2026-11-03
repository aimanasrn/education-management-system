<!-- BEGIN:nextjs-agent-rules -->

## This is NOT the Next.js you know

This version has breaking changes — APIs, conventions, and file structure may all differ from your training data. Read the relevant guide in `node_modules/next/dist/docs/` (resolved from this file's directory; in monorepos the `next` package may not be visible from the repo root) before writing any code. Heed deprecation notices.

This block is written and re-added by `next dev` — verify at `node_modules/next/dist/server/lib/generate-agent-files.js`. Removing it from a diff only re-creates the uncommitted change; committing it with your work keeps the tree clean.

<!-- END:nextjs-agent-rules -->

## UI components

- Use the official shadcn/ui registry (`@shadcn`, https://ui.shadcn.com/) as this project's only UI component system.
- All controls, navigation, cards, feedback, overlays, and loading/empty states must compose the installed components in `src/components/ui`. Add missing components with `npx shadcn@latest add <component>`.
- Do not add another UI component library or create parallel custom implementations of components provided by shadcn/ui. Native semantic HTML, page layout, typography, and app-specific compositions are allowed.
- Follow `components.json` for the installed Base UI preset, aliases, and icon library. Use semantic theme tokens, built-in variants, and `FieldGroup`/`Field` for forms.
