# Backend Architecture

This document describes the folder structure, module responsibilities, design principles, and request lifecycle of the TaskPad API.

---

## Stack

| Layer | Technology |
|---|---|
| Language | PHP 8.2+ |
| Framework | Laravel 12 |
| Database | MySQL 8.0 |
| Authentication | Laravel Sanctum (API tokens) |
| API Docs | L5-Swagger (OpenAPI 3.0 — PHP 8 attributes) |
| Queue | Laravel Queue (database driver, upgrade to Redis later) |

---

## Design Principles

- **RESTful conventions**: Resources are nouns, HTTP verbs define intent.
- **Thin controllers**: Controllers only validate input, call a service/action, and return a response.
- **Service classes**: Business logic lives in `app/Services/`, not in controllers or models.
- **Form Requests**: All input validation lives in dedicated `app/Http/Requests/` classes.
- **API Resources**: All responses go through `app/Http/Resources/` to ensure consistent JSON shape.
- **Scoped access**: Every query is scoped to the authenticated user's organization or project membership. No data leaks between tenants.

---

## Folder Structure

```text
app/
├── Http/
│   ├── Controllers/
│   │   ├── Auth/
│   │   │   └── AuthController.php          # Register, Login, Logout
│   │   ├── OrganizationController.php
│   │   ├── ProjectController.php
│   │   ├── IssueController.php
│   │   ├── BoardController.php
│   │   ├── SprintController.php
│   │   ├── CommentController.php
│   │   ├── AttachmentController.php
│   │   └── HealthController.php
│   │
│   ├── Requests/
│   │   ├── Auth/
│   │   │   ├── LoginRequest.php
│   │   │   └── RegisterRequest.php
│   │   ├── StoreOrganizationRequest.php
│   │   ├── StoreProjectRequest.php
│   │   ├── StoreIssueRequest.php
│   │   ├── UpdateIssueRequest.php
│   │   ├── StoreSprintRequest.php
│   │   └── StoreCommentRequest.php
│   │
│   └── Resources/
│       ├── UserResource.php
│       ├── OrganizationResource.php
│       ├── ProjectResource.php
│       ├── IssueResource.php
│       ├── BoardColumnResource.php
│       ├── SprintResource.php
│       └── CommentResource.php
│
├── Models/
│   ├── User.php
│   ├── Organization.php
│   ├── OrganizationMember.php
│   ├── Project.php
│   ├── ProjectMember.php
│   ├── Issue.php
│   ├── Board.php
│   ├── BoardColumn.php
│   ├── Sprint.php
│   ├── Comment.php
│   ├── Attachment.php
│   ├── Label.php
│   └── ActivityLog.php
│
├── Services/
│   ├── AuthService.php                     # Token generation, login logic
│   ├── IssueService.php                    # Issue creation, status transitions, code generation (TP-1, TP-2)
│   ├── SprintService.php                   # Sprint start/complete logic, backlog moves
│   ├── BoardService.php                    # Column ordering, issue position updates
│   └── ActivityLogService.php             # Central logging for all entity changes
│
└── Policies/
    ├── ProjectPolicy.php                   # Gate: only project members can access
    ├── IssuePolicy.php                     # Gate: issue scoped to project
    └── OrganizationPolicy.php             # Gate: org-level access checks

routes/
└── api.php                                 # All API routes, grouped by module

database/
├── migrations/                             # One migration file per table
└── seeders/
    └── DatabaseSeeder.php                  # Dev seeds for testing

config/
└── l5-swagger.php                          # Swagger / OpenAPI config
```

---

## Request Lifecycle

Every API request follows this path:

```
HTTP Request
    │
    ▼
routes/api.php           — Route matching, auth middleware applied
    │
    ▼
Form Request             — Validates input, returns 422 if invalid
    │
    ▼
Controller               — Extracts data, calls the Service
    │
    ▼
Policy (optional)        — Gate check: can this user do this action?
    │
    ▼
Service Class            — Core business logic, database writes
    │
    ▼
Eloquent Model           — ORM interaction with the database
    │
    ▼
API Resource             — Formats the Eloquent model into JSON
    │
    ▼
JSON Response            — Returned to the client
```

---

## Authentication Strategy

TaskPad uses **Laravel Sanctum** for API token authentication.

- On login, a token is generated and returned to the client.
- The client stores this token and sends it as `Authorization: Bearer <token>` on every subsequent request.
- Routes requiring auth are protected by the `auth:sanctum` middleware.
- Tokens can be revoked on logout.

> See [auth-flow.md](./auth-flow.md) for the full login/logout flow diagram.

---

## Multi-Tenancy Model

TaskPad uses **soft multi-tenancy** scoped by organization:

- All projects belong to one organization.
- A user must be a member of an organization to access its data.
- A user must be a member of a project to access its issues and boards.
- Controllers and Services must always scope queries by the authenticated user's memberships.

**Example scope pattern:**
```php
// Always scope by authenticated user's project membership
$project = Project::whereHas('members', fn($q) => $q->where('user_id', auth()->id()))
    ->findOrFail($projectId);
```

---

## Issue Code Generation

Each issue gets a human-readable code like `TP-42`.

- The project `key` column (e.g. `TP`) is set when the project is created.
- On issue creation, `IssueService` counts existing issues in the project and increments: `count + 1`.
- The code is stored as `TP-43` in the `code` column of the `issues` table.
- The code is **immutable** after creation.

---

## API Response Format

All responses follow a consistent envelope:

**Success:**
```json
{
  "status": "true",
  "message": "Resource retrieved.",
  "data": { ... }
}
```

**Error / Validation Failure:**
```json
{
  "status": "false",
  "message": "Validation failed.",
  "errors": {
    "email": ["The email has already been taken."]
  }
}
```

**List Response (paginated):**
```json
{
  "status": "true",
  "data": [ ... ],
  "meta": {
    "current_page": 1,
    "last_page": 5,
    "per_page": 20,
    "total": 97
  }
}
```
