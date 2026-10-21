# TaskPad API

Backend RESTful API service for **TaskPad** — a Jira-inspired agile project management and issue-tracking platform built with Laravel 12 and OpenAPI / Swagger.

---

## Overview

TaskPad API provides the foundational backend architecture for agile task tracking, workflows, and team collaboration. It serves the TaskPad web frontend with secure Sanctum authentication, organization tenancy, project management, Kanban and Scrum board workflows, sprint lifecycles, and customizable issue tracking with activity audit trails.

---

## Core Features

- [x] **API Health Monitoring**: Uptime check endpoint.
- [x] **Authentication & Users**: Sanctum-based registration, login, token revocation, and user profile management.
- [x] **Organizations & Workspaces**: Multi-tenant organizations with role-based member management (`owner`, `admin`, `member`).
- [x] **Projects & Members**: Organization projects with keys (`TP`), lead assignment, member roles, and slug/key routing.
- [x] **Issue Tracking**: Epics, Stories, Tasks, Bugs, and Subtasks with automatic key-based code generation (`TP-101`), priorities, story points, due dates, assignee sync, and label tagging.
- [x] **Kanban & Scrum Boards**: Auto-provisioned workflow boards with customizable, reorderable columns mapped to statuses (`backlog`, `todo`, `in_progress`, `in_review`, `done`).
- [x] **Sprints Management**: Scrum sprint lifecycle (`planned` -> `active` -> `completed`), sprint activation guard, and automated backlog rollover for unfinished issues on completion.
- [x] **Threaded Comments**: Issue discussions with support for nested replies and author/admin permission guards.
- [x] **Labels**: Color-coded, project-scoped tags for flexible categorization and filtering.
- [x] **File Attachments**: File upload and download for issues with secure project access verification.
- [x] **Audit & Activity Logs**: Immutable activity timelines capturing issue and sprint events with before/after state diffs.
- [x] **OpenAPI / Swagger Documentation**: Interactive API documentation generated with `l5-swagger` (PHP 8 attributes).

---

## Requirements

- **PHP**: `^8.2`
- **Composer**: `^2.0`
- **Database**: MySQL / MariaDB (or SQLite)

---

## Getting Started

### 1. Installation

```bash
cd taskpad-api
composer install
```

### 2. Environment Configuration

Copy `.env.example` to `.env` and generate the application key:

```bash
cp .env.example .env
php artisan key:generate
```

Configure your database connection in `.env`:

```env
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=taskpad
DB_USERNAME=root
DB_PASSWORD=
```

### 3. Run Migrations

```bash
php artisan migrate
```

### 4. Start Development Server

```bash
php artisan serve
```

The API service runs at `http://localhost:8000`.

---

## API Documentation (Swagger UI)

Interactive OpenAPI 3.0 documentation is powered by `l5-swagger`:

- **Interactive UI:** `http://localhost:8000/api/documentation`
- **JSON Specification:** `http://localhost:8000/docs/api-docs.json`

To regenerate Swagger documentation after modifying controller attributes:

```bash
php artisan l5-swagger:generate
```

---

## API Endpoints Summary

All routes are prefixed with `/api`. Protected routes require `Authorization: Bearer <token>`.

### Authentication
- `POST /api/register` — Register a new account
- `POST /api/login` — Login and receive Bearer token
- `GET /api/me` — Authenticated user profile
- `POST /api/logout` — Revoke token

### Organizations
- `GET /api/organization` — List organizations
- `POST /api/organization/create` — Create organization
- `GET /api/organization/{orgSlug}` — Organization details
- `POST /api/organization/{orgSlug}` — Update organization
- `DELETE /api/organization/{orgSlug}` — Delete organization
- `GET /api/organization/{orgSlug}/members` — List members
- `POST /api/organization/{orgSlug}/members` — Add member
- `PUT /api/organization/{orgSlug}/members/{user}` — Update member role
- `DELETE /api/organization/{orgSlug}/members/{user}` — Remove member

### Projects
- `GET /api/organization/{orgSlug}/projects` — List organization projects
- `POST /api/organization/{orgSlug}/projects` — Create project
- `GET /api/project/{projectSlug}` — Project details
- `PUT /api/project/{projectSlug}` — Update project
- `DELETE /api/project/{projectSlug}` — Delete project
- `GET /api/project/{projectSlug}/members` — List project members
- `POST /api/project/{projectSlug}/members` — Add member to project
- `PUT /api/project/{projectSlug}/members/{user}` — Update member role
- `DELETE /api/project/{projectSlug}/members/{user}` — Remove member from project

### Issues
- `GET /api/project/{projectSlug}/issues` — List issues (filters: `status`, `type`, `assignee`, `sprint_id`, `priority`, `label`, `search`)
- `POST /api/project/{projectSlug}/issues` — Create issue
- `GET /api/issues/{issueId}` — Issue details
- `PUT /api/issues/{issueId}` — Update issue
- `DELETE /api/issues/{issueId}` — Delete issue
- `PATCH /api/issues/{issueId}/status` — Update issue status
- `PATCH /api/issues/{issueId}/assignees` — Sync assignees
- `PATCH /api/issues/{issueId}/sprint` — Move issue to sprint or backlog

### Boards
- `GET /api/project/{projectSlug}/board` — Board with columns and issues
- `PUT /api/project/{projectSlug}/board/columns` — Reorder columns
- `POST /api/project/{projectSlug}/board/columns` — Add custom column
- `PUT /api/board-columns/{columnId}` — Rename column
- `DELETE /api/board-columns/{columnId}` — Delete custom column

### Sprints
- `GET /api/project/{projectSlug}/sprints` — List project sprints
- `POST /api/project/{projectSlug}/sprints` — Create sprint
- `GET /api/sprints/{sprintId}` — Sprint details and issues
- `PUT /api/sprints/{sprintId}` — Update sprint
- `POST /api/sprints/{sprintId}/start` — Start planned sprint
- `POST /api/sprints/{sprintId}/complete` — Complete sprint (moves unfinished to backlog)
- `DELETE /api/sprints/{sprintId}` — Delete planned sprint

### Comments
- `GET /api/issues/{issueId}/comments` — List comments (threaded)
- `POST /api/issues/{issueId}/comments` — Add comment or reply
- `PUT /api/comments/{commentId}` — Edit comment (author only)
- `DELETE /api/comments/{commentId}` — Delete comment (author or admin)

### Labels
- `GET /api/project/{projectSlug}/labels` — List project labels
- `POST /api/project/{projectSlug}/labels` — Create label
- `PUT /api/labels/{labelId}` — Update label name or color
- `DELETE /api/labels/{labelId}` — Delete label

### Attachments
- `POST /api/issues/{issueId}/attachments` — Upload attachment
- `GET /api/attachments/{attachmentId}` — Download attachment
- `DELETE /api/attachments/{attachmentId}` — Delete attachment

### Activity Logs
- `GET /api/issues/{issueId}/activity` — Issue activity timeline
- `GET /api/project/{projectSlug}/activity` — Project activity feed

### Utility
- `GET /api/health` — API health check

---

## Architecture & Directory Structure

```text
taskpad-api/
├── app/
│   ├── Http/
│   │   └── Controllers/
│   │       ├── Auth/
│   │       │   └── AuthController.php        # User auth & Sanctum tokens
│   │       ├── ActivityLogController.php     # Issue and project activity feeds
│   │       ├── AttachmentController.php      # File upload & download management
│   │       ├── BoardController.php           # Kanban/Scrum boards & column reordering
│   │       ├── CommentController.php         # Issue comments & threaded replies
│   │       ├── Controller.php                # Base controller & OpenApi metadata
│   │       ├── HealthController.php          # Health check endpoint
│   │       ├── IssueController.php           # Issue tracking & lifecycle operations
│   │       ├── LabelController.php           # Project label management
│   │       ├── OrganizationController.php    # Organizations & membership
│   │       ├── ProjectController.php         # Projects & project members
│   │       └── SprintController.php          # Scrum sprints lifecycle management
│   └── Models/
│       ├── ActivityLog.php                   # Immutable audit log entries
│       ├── Attachment.php                    # Issue file attachments
│       ├── Board.php                         # Project board model
│       ├── BoardColumn.php                   # Workflow status board columns
│       ├── Comment.php                       # Threaded issue comments
│       ├── Issue.php                         # Core issue entity
│       ├── Label.php                         # Project categorization labels
│       ├── Organization.php                  # Organization model
│       ├── OrganizationMember.php            # Org membership & roles
│       ├── Project.php                       # Project entity
│       ├── ProjectMember.php                 # Project membership & roles
│       ├── Sprint.php                        # Sprint entity
│       └── User.php                          # User entity
├── database/
│   └── migrations/                           # Database table migrations
├── docs/
│   ├── api-plan.md                           # Complete API endpoint checklist
│   └── database-schema.md                    # Database schema specifications
├── routes/
│   └── api.php                               # REST API routes
└── storage/
    └── api-docs/                             # Generated Swagger JSON & YAML specs
```
