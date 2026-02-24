# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Project

Chess Studio — a full-stack app for importing, viewing, and analyzing chess games. Symfony 7.2 PHP backend with PostgreSQL, React 18 TypeScript frontend with Tailwind CSS 4, Stockfish integration (server-side binary + client-side WASM), real-time updates via Mercure.

## Commands

### Development (Docker)
```bash
docker compose up -d                # start all services
docker compose exec node npm run watch  # rebuild assets on change
docker compose logs messenger-worker    # debug async analysis jobs
```

### Testing
```bash
vendor/bin/phpunit                     # all tests
vendor/bin/phpunit tests/Service/      # specific directory
vendor/bin/phpunit --filter testName   # single test
```

### Database
```bash
bin/console doctrine:migrations:migrate
bin/console make:migration
```

### Frontend build
```bash
npm run dev       # dev build
npm run build     # production build
```

## Architecture

### Backend (`src/`)
- **Controller/** — REST API endpoints at `/api/*` using `#[Route]` attributes. `DefaultController` is a catch-all that serves the React SPA for all non-API routes.
- **Entity/** — Doctrine ORM entities: `User`, `Game` (PGN + metadata + owner), `Analysis` (engine evaluation per game).
- **Service/** — Business logic: `PgnImportService` (PGN string → Game entity), `FormImportService` (DTO → Game with generated PGN), `StockfishService` (runs Stockfish binary, parses UCI output, normalizes scores to white's perspective).
- **Message/Handler/** — Async analysis via Symfony Messenger. `AnalyzeGameMessage` is dispatched to the `async` transport (Doctrine), consumed by a worker that runs Stockfish on every position and publishes results via Mercure.
- **DTO/** — `GameFormInput` for the manual game import form.

### Frontend (`assets/`)
- **app.tsx** — Entry point with React Router. Routes: `/login`, `/register`, `/games`, `/games/import`, `/games/:id`.
- **components/pages/** — Page-level components. `GamePage` is the main viewer (board + moves + analysis in a 3-column grid).
- **components/import/** — Import sub-components (PGN paste, file upload, manual form, image placeholder).
- **components/ui/** — Shared UI primitives (Button, Card, Textarea) built on Radix UI + Tailwind.
- **hooks/** — `useAuth` (context-based session auth), `useStockfish` (WASM worker managing UCI protocol), `useMercure` (SSE subscription), `useTheme` (light/dark toggle persisted to localStorage).
- **lib/chess.ts** — Shared chess utilities including `PIECE_TO_FRENCH` mapping and `sanToFrench()` for French notation (C/F/T/D/R instead of N/B/R/Q/K).

### Key data flow
1. Games are imported via `/api/games/import` (PGN paste), `/api/games/import/file` (file upload), or `/api/games/import/form` (manual entry). All three produce a `Game` entity with a valid PGN.
2. Client-side analysis runs Stockfish WASM in a Web Worker (`useStockfish`), scores normalized to white's perspective.
3. Server-side "deep analysis" dispatches `AnalyzeGameMessage` → Messenger worker → `StockfishService` analyzes each position → results stored in `Analysis` entity → Mercure pushes update to frontend.

## Conventions

- **DRY (Don't Repeat Yourself).** Extract shared logic into services or shared components. If the same code appears in two places, refactor it into a single source of truth. Backend: use Service classes. Frontend: use shared components in `components/ui/` or utility functions in `lib/`.
- **KISS (Keep It Simple).** Prefer the simplest solution that works. Don't abstract prematurely — only extract when duplication actually exists.
- **No inline comments.** Do not write comments inside function/method bodies unless the logic is genuinely complex and non-obvious. Use PHPDoc blocks for documenting method contracts (parameters, return types, exceptions). In TypeScript, use JSDoc only when types alone don't convey intent.
- **UI language is French.** All user-facing strings (labels, buttons, messages, errors) must be in French.
- **Chess notation is French.** Pieces: C (Cavalier), F (Fou), T (Tour), D (Dame), R (Roi). Use `sanToFrench()` from `lib/chess.ts`.
- **Stockfish scores are always from white's perspective.** Positive = white winning. Negate when parsing output for black-to-move positions (both frontend and backend).
- **PGN header parsing uses regex** — not a full parser. The `onspli/chess` library is available for move replay and FEN extraction.
- **Authentication is session-based.** API routes under `/api/*` require `ROLE_USER` except `/api/login` and `/api/me`. Admin routes under `/api/admin/*` require `ROLE_USER_MANAGER`. Public registration is disabled — only admins create accounts.
- **Async jobs use Symfony Messenger** with Doctrine transport. The worker runs in its own Docker container.
- Path alias `@/*` maps to `assets/*` in both TypeScript and Webpack.
- Theme supports light/dark mode via `.dark` class on `<html>` with CSS custom properties in `app.css`.
