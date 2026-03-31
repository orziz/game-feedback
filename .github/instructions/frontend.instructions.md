---
description: "Use when writing Vue 3 + TypeScript frontend code (web/src), building components, managing state, integrating i18n, or working with type definitions and API boundaries."
name: "Frontend Architecture & Patterns"
applyTo: "web/src/**"
---

# Frontend Architecture & Patterns

## Component Structure

**Enforce**: Vue 3 Single-File Components follow the container + composable + UI pattern:
- **Containers** (views, complex components): Orchestration logic, route handling, state management imports
  - Example: `AdminView.vue`, `PlayerView.vue` — page-level containers
- **Composables** (hooks): Stateful business logic, data fetching, side effects
  - Store composables in `web/src/composables/`
  - Naming: `use*` prefix (e.g., `useFeedbackApp.ts`)
  - Example: Data fetching, form state, user session
- **Presentation Components**: Pure UI, props-driven, minimal logic
  - Store in `web/src/components/`
  - Example layout components in `components/layout/`, admin in `components/admin/`, shared in `components/shared/`

**Advisory**: Keep component sizes manageable; split if exceeding 200 lines of logic.

## TypeScript & Type Safety

**Enforce**:
- All `.ts`, `.vue` files must be valid TypeScript (enforce with `npm run typecheck`)
- Type definitions stored in `web/src/types/` organized by domain (API, UI domain types)
- API response types defined in `web/src/types/API/` mirroring backend module structure
  - Example: `web/src/types/API/Admin/Ticket.d.ts`, `web/src/types/API/Feedback/Ticket.d.ts`
- Never use `any`; use `unknown` or explicit unions if needed
- Props and emits must have explicit type annotations

**Advisory**: Use strict null checks and exhaustive switch statements for better type coverage.

## API Data Flow

**Enforce**:
- All API calls go through `web/src/api/client.ts` (single client instance)
- API client uses type definitions from `web/src/types/API/**`
- Distinguish between request method patterns:
  - **JSON requests (no attachments)**: Use `client.post()` → sends `application/json`
  - **FormData requests (with attachments)**: Use `postForm.submit()` → sends `multipart/form-data` with query params for text fields as fallback (handles environments where multipart loses `$_POST`)
- Example in `SubmitTab.vue`: Check for attachments → choose method
- Never reconstruct URLs; use client methods with action names
- Vite proxy at `/api` → backend at `http://127.0.0.1:8000/index.php` (rewrite preserves query params)

**Advisory**: Consider wrapping repeated API calls in composables for reusability.

## Internationalization (i18n)

**Enforce**:
- i18n managed in `web/src/i18n/`
  - Language packs in `web/src/i18n/locales/*.ts` (auto-loaded via `import.meta.glob`)
  - Supports `zh-CN` and `en`
- All user-facing strings use i18n keys; never hardcode UI text
- Element Plus integration: Sync locale via `element.locale.use()` when language changes
- Structure i18n keys hierarchically (e.g., `admin.users.title`, `feedback.submit.success`)

**Advisory**: Keep keys organized by feature/domain for maintainability.

## Build & Validation

**Always**:
- Run `npm run build` before committing frontend changes (validates bundle, catches TS errors)
- Run `npm run typecheck` to verify TypeScript (uses `vue-tsc`)
- Use `npm run dev` to develop locally (Vite dev server with HMR)

## Environment Quirks & Compatibility

**Advisory**: Be aware of limitations in certain deployment environments:
- FormData submission: Some PHP environments don't populate `$_POST` for multipart requests → backend falls back to query params
- Solution: Frontend includes non-file fields in query string when submitting attachments (see `SubmitTab.vue`)
- Test both JSON and FormData paths during development

## File Attachment Handling

**Enforce**:
- `triggerBlobDownload()` in `web/src/utils/download.ts` revokes object URLs with a delay to prevent premature cancellations in some browsers
- Before assuming blob is a file, parse JSON response — if it's an error from the backend, show error message instead
- Never assume `content-disposition` header alone; verify response content-type

**Advisory**: Test file uploads and downloads on target deployment environments (MAMP, specific PHP versions).
