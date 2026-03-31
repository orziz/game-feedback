---
description: "Use when writing PHP backend code (server/src), designing API endpoints, working with repositories, handling requests, data validation, or managing the service layer."
name: "Backend Architecture & Patterns"
applyTo: "server/src/**"
---

# Backend Architecture & Patterns

## Layered Structure

**Enforce**: Follow the three-layer architecture:
- **API Layer** (`server/src/API/`): Endpoint routing, request validation, response formatting
  - Base classes: `BaseApiModule.php` (module container), `BaseApiSubModule.php` (endpoint handler)
  - Organize by domain: `Admin/`, `Feedback/`, `System/`
  - Example: `AdminModule.php` contains `Auth`, `Ticket`, `User` submodules
- **Repository Layer** (`server/src/Repository/`): Data access, database queries, entity persistence
  - Example: `TicketRepository.php`, `UserRepository.php`
  - All database reads/writes go through repositories
- **Support Layer** (`server/src/Support/`): Cross-cutting concerns (DB, auth, file uploads, sanitization, rate limiting)
  - Core utilities: `Database.php`, `Request.php`, `Responder.php`
  - Specialized: `AdminToken.php` (JWT), `AttachmentUploader.php` (file handling), `RateLimiter.php`
  - Entry points: `SystemInstaller.php` (setup), `UserSystemMigrator.php` (multi-tenancy)

**Advisory**: Keep business logic in repositories; avoid mixing data access with API formatting.

## API Module Pattern

**Enforce**:
- Every endpoint inherits from `BaseApiModule` or `BaseApiSubModule`
- Submodules override `run($action)` to dispatch to action methods
- Action methods follow naming: `handle{ActionName}()` (e.g., `handleCreate()`, `handleUpdate()`)
- Responses always use `Responder` class (consistent JSON format)
- Example: `Admin/Ticket.php::handleList()` → queries `TicketRepository::findByFilters()` → `Responder::success($data)`

**Advisory**: Avoid database logic inside API handlers; delegate to repositories.

## Request Handling & Parameter Parsing

**Enforce**: Handle multipart/form-data environments where `$_POST` may be empty:
1. **First**: Check if request is multipart (via `CONTENT_TYPE` header)
   - If multipart, extract text fields from query params (frontend sends them as fallback)
   - Never attempt JSON parsing on multipart requests
2. **Otherwise**: Read `$_POST` if available
3. **Fallback**: Parse JSON body only if neither multipart nor `$_POST`

Example flow in `Feedback/Ticket.php` (submit handler):
```php
// Check multipart first
if (strpos($_SERVER['CONTENT_TYPE'] ?? '', 'multipart/form-data') !== false) {
    // Extract from query: $action, $type, $severity, $title, $description, $contact
    // Files come from $_FILES
} else if ($_POST) {
    // Use $_POST directly
} else {
    // Parse JSON body
}
```

**Advisory**: Always validate and sanitize inputs via `AppInputSanitizer` before use.

## Data Validation & Enums

**Enforce**:
- Enum-backed validation: `TicketStatus`, `TicketSeverity`, `TicketType`, `UserRole` in `server/src/Enums/`
- Backend API provides `EnumOptionsProvider` to return valid enum values to frontend
- Validation flow: Sanitize → Validate enum → Check business rules → Persist
- Attach enum metadata (labels, colors) in API responses for UI rendering

**Advisory**: Coordinate enum values with frontend type definitions in `web/src/types/API/`.

## Database & Repository Patterns

**Enforce**:
- Repositories are single-responsibility classes (e.g., `TicketRepository` handles all ticket data operations)
- Methods are action-descriptive: `findByFilters()`, `create()`, `updateStatus()`, not generic CRUD
- Database access through `Database::getInstance()` (singleton connection)
- SQL migrations in `server/migrate.sql` (one-way, versions tracked in database)
- Always use prepared statements (parameterized queries)

**Advisory**: Avoid N+1 queries; batch fetch related entities when possible.

## File Upload & Storage

**Enforce**: `AttachmentUploader` handles all file operations:
- Validate file types, sizes before upload
- Support multiple upload domains (Qiniu): `qiniu_upload_host` can be comma-separated list for fallback
- Configurable timeouts: `qiniu_connect_timeout`, `qiniu_upload_timeout`
- Diagnostic info (attempted hosts, timing) included in error responses
- Files organized by date: `storage/uploads/YYYY/MM/`

**Advisory**: Monitor upload success rates and timeouts; adjust domain fallback and timeout config if needed.

## PHP Standards & Type Hints

**Enforce**:
- PHP 8.4+: Use strict typing, type hints on all parameters and return types
- Syntax check: `/Applications/MAMP/bin/php/php8.4.1/bin/php -l <file>` (use this since `php` not in PATH)
- Follow PSR-12 style guide (spaces, naming conventions)
- Use `final` on classes that shouldn't be extended; prefer composition

**Advisory**: Run type checking before committing backend changes.

## Environment & Configuration

**Enforce**:
- Config files in `server/config/`:
  - `app.php`: Application-level settings
  - `database.php`: Database connection (PDO DSN, credentials)
- Database access via Unix socket (MAMP uses `/Applications/MAMP/Library/logs/fastcgi/nginxFastCGI.sock`)
- Never commit credentials; use environment variables or `.env` pattern

**Advisory**: Keep config separate from code; externalize all environment-specific values.

## Multi-Game/Multi-Tenant Support

**Enforce**:
- Each ticket belongs to a `gameKey` (defaults to `default` if not specified)
- Queries must filter by `gameKey` in multi-game deployments
- Admin can manage games via `/api?action=admin.game&action=list` (if implemented)
- Frontend passes `gameKey` via URL query on Player view, backend respects it when creating/listing tickets

**Advisory**: Test multi-game separation with different gameKey values before deployment.

## Rate Limiting & Security

**Enforce**:
- Use `RateLimiter` for API endpoints (prevents abuse)
- Configuration: Limits per IP, time window, action
- `AdminToken` (JWT-based auth): Validate before admin operations
- All user input sanitized via `AppInputSanitizer` (XSS, SQL injection prevention)

**Advisory**: Monitor rate limit violations; adjust thresholds based on usage patterns.
