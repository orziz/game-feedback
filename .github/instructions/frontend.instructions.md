---
description: "Use when writing Vue 3 + TypeScript frontend code (web/src), building components, managing Pinia state, integrating i18n, or working with typed API boundaries and admin permission gating."
name: "Frontend Architecture & Patterns"
applyTo: "web/src/**"
---

# Frontend Architecture & Patterns

## Stack & UI Conventions

**Enforce**:
- Frontend code uses Vue 3 + TypeScript + Pinia
- UI components and discrete feedback APIs use Naive UI, not Element Plus
- Global message and dialog access should go through `web/src/ui/discrete.ts`
- Shared API access goes through `web/src/api/client.ts`

**Advisory**: Follow existing component and store patterns before introducing a new UI abstraction layer.

## Component Structure

**Enforce**:
- Keep page-level orchestration in views or higher-level container components
- Put reusable admin UI in `web/src/components/admin/`
- Keep presentational components props-driven where practical
- Split repeated stateful logic into composables or stores when it is reused across screens

**Advisory**: If a component starts mixing session state, permission logic, data fetching, and rendering heavily, consider moving part of it into a store or composable.

## TypeScript & API Types

**Enforce**:
- All `.ts` and `.vue` files must pass `npm run typecheck`
- API contracts live in `web/src/types/API/` and must stay aligned with backend payloads
- Avoid `any`; prefer explicit types, `unknown`, or narrowed unions
- Keep props, emits, and local helper signatures typed

**Advisory**: When backend payloads change, update types first so the compiler helps find UI fallout.

## API Data Flow

**Enforce**:
- Use the generated proxy style exposed by `api` from `web/src/api/client.ts`
- Do not hand-build `/api?s=...` URLs in components
- Use JSON requests for normal payloads and `postForm` for multipart payloads
- Preserve the existing multipart fallback pattern where text fields can also be sent as query params
- Keep auth token wiring through `setApiTokenGetter()`

**Advisory**: If multiple components call the same admin endpoint and share state, prefer lifting that logic into a Pinia store.

## Pinia State & Admin Session Rules

**Enforce**:
- Shared admin session state lives in `useAdminStore`
- Permission checks should use store helpers such as `hasPermission()` and `hasFeature()`
- After changing feature flags or role assignments that can affect the current operator, refresh `currentUser`
- Keep store state as the source of truth for admin permissions, feature flags, and cleanup config state

**Advisory**: Avoid duplicating permission snapshots in local component refs when the store already owns that state.

## Permission-Driven UI

**Enforce**:
- Gate page entry with the corresponding `*.view` permission and required feature flag
- Gate mutating actions with the corresponding `*.manage` or action-specific permission
- Do not hardcode UI access as “super admin only” when the backend already models it as permissions plus feature flags
- When a page becomes newly visible because a feature flag or permission changes, refresh any dependent data instead of relying on default placeholder state

**Advisory**: Treat permission and feature visibility as reactive state, not as a one-time check at mount.

## Internationalization

**Enforce**:
- All user-facing strings must use i18n keys
- Keep locale entries synchronized between `zh-CN` and `en`
- Add new keys in the existing feature-oriented structure under `web/src/i18n/locales/`

**Advisory**: When changing admin UX, update error and success messages in both locales in the same patch.

## Build & Verification

**Always**:
- Run `npm run typecheck` after meaningful frontend edits
- Run `npm run build` before wrapping up frontend work

## File Downloads & Attachments

**Enforce**:
- Use `triggerBlobDownload()` for blob downloads
- Keep current defensive handling for backend error payloads that may arrive where a file was expected
- Preserve attachment upload/download compatibility behavior across browsers and PHP environments

**Advisory**: Re-test attachment flows whenever request formatting or admin ticket actions change.
