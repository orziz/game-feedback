---
description: "Use when writing PHP backend code (server/src), designing API endpoints, working with repositories, handling requests, data validation, migrations, or admin access control."
name: "Backend Architecture & Patterns"
applyTo: "server/src/**"
---

# Backend Architecture & Patterns

## Layered Structure

**Enforce**: Keep backend code aligned with the current three-layer split:
- **API Layer** (`server/src/API/`): request routing, action metadata, auth, permission checks, response formatting
- **Repository Layer** (`server/src/Repository/`): SQL reads/writes and persistence
- **Support Layer** (`server/src/Support/`): cross-cutting infrastructure such as `Database`, `Request`, `Responder`, `RateLimiter`, `SchemaMigrationManager`, `PermissionMatrix`, `FeatureGate`

**Advisory**: Keep SQL and persistence details in repositories, not in API handlers.

## API Submodule Pattern

**Enforce**:
- API submodules inherit from `BaseApiSubModule` or a domain-specific subclass such as `AdminSubModule`
- Each submodule declares `actionMeta(): array`
- Action names in `actionMeta()` must match real protected methods exactly, for example `list`, `create`, `update`, `delete`
- Do not reintroduce the old `run($action)` or `handleXxx()` dispatch style
- Let the base classes own HTTP method checks, rate limiting, dispatch, auth, feature gating, and permission gating
- Use `Responder::send()` and `Responder::error()` for all API responses

**Advisory**: When adding a new endpoint, copy an existing submodule in the same domain and follow its metadata shape before inventing a new pattern.

## Admin Auth, Permissions, and Features

**Enforce**:
- Admin endpoints should declare `META_AUTH` in `actionMeta()`
- Use `META_PERMISSION` for permission-gated actions and `META_FEATURE` for feature-flag-gated actions
- Page-entry permissions use `*.view`; mutating or elevated actions use `*.manage` or action-specific permissions
- If a new action permission implicitly requires page access, update `AccessControlDefaults::permissionDependencies()` so stored role permissions are normalized consistently
- Use `PermissionMatrix` and `FeatureGate` through the existing factory helpers instead of duplicating permission logic in modules

**Advisory**: Prefer expressing access in metadata and shared support classes instead of sprinkling manual `if` branches through handlers.

## Request Handling & Validation

**Enforce**:
- Read query params via `Request::query()`
- Read JSON bodies via `Request::jsonBody()`
- Read admin tokens via `Request::authorizationHeader()`
- For multipart requests, preserve the existing fallback pattern where text fields may come from query params when `$_POST` is unreliable
- Sanitize all external input with `AppInputSanitizer` before business validation
- Validation order should stay: sanitize -> shape/type checks -> business rules -> persistence

**Advisory**: Reuse enum-backed validation and existing helper methods before adding custom parsing paths.

## Repository & Database Pattern

**Enforce**:
- Repositories receive `PDO` in the constructor; do not introduce hidden global database singletons
- Use the existing repository factories from `BaseApiSubModule` such as `createTicketRepository()`, `createUserRepository()`, and `createAccessControlRepository()`
- Always use prepared statements
- Keep repository methods action-oriented and domain-specific
- Access control data lives in dedicated tables such as `permission_definitions`, `role_definitions`, `role_permissions`, `user_roles`, `feature_definitions`, and `feature_instance_overrides`

**Advisory**: If a change spans tickets, users, and access control, split it across the relevant repositories instead of centralizing everything in one large repository.

## Schema & Migration Rules

**Enforce**:
- The authoritative runtime migration path is `SchemaMigrationManager`, triggered by `SystemInstaller` and `UserSystemMigrator`
- When a schema change must apply to existing installs, add an incremental migration step and bump `CURRENT_SCHEMA_VERSION`
- Keep `server/migrate.sql` synchronized with runtime migrations for manual upgrade scenarios
- When a migration also repairs historical data, make it idempotent

**Advisory**: If you change role, permission, or feature-flag schema or seed data, verify both the PHP migration flow and `server/migrate.sql` tell the same story.

## Access Control Change Checklist

**Enforce**: For any role or permission model change, check all of the following:
- Backend permission definitions and dependency closure in `AccessControlDefaults`
- Repository write paths and historical repair paths in `AccessControlRepository`
- Schema migration coverage for existing installs
- `server/migrate.sql` parity for manual SQL upgrades
- Current-user payloads consumed by the frontend

**Advisory**: Treat permission changes as a full-stack contract change, not a backend-only edit.

## File Upload & Attachment Handling

**Enforce**:
- `AttachmentUploader` owns upload validation and storage behavior
- Keep attachment-related config in backend config, not hardcoded in handlers
- Preserve current compatibility behavior for environments where multipart form fields are incomplete

**Advisory**: If you touch upload or download behavior, verify both local storage and Qiniu-related flows.

## PHP Compatibility & Style

**Enforce**:
- Keep `declare(strict_types=1);`
- Maintain PHP 7.2 compatibility
- Do not introduce typed properties, arrow functions, nullsafe operators, `match`, constructor property promotion, union types, or native enums
- Follow PSR-12 style
- Prefer `final` classes unless there is a real extension point

**Advisory**: Run a PHP syntax check when a compatible PHP binary is available.

## Security & Rate Limiting

**Enforce**:
- Use `RateLimiter` through `actionMeta()` rate-limit metadata where abuse is possible
- Keep admin authentication inside `AdminSubModule` and token helpers
- Sanitize all user input before persistence or filesystem operations

**Advisory**: When adding admin capabilities, think through auth, permission, feature flag, and audit implications together.
