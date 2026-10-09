# Database Schema

Complete table definitions for the TaskPad API. All tables use `id` (ULID or auto-increment bigint), `created_at`, and `updated_at` unless noted.

---

## Table of Contents

1. [users](#1-users)
2. [organizations](#2-organizations)
3. [organization_members](#3-organization_members)
4. [projects](#4-projects)
5. [project_members](#5-project_members)
6. [issues](#6-issues)
7. [issue_assignees](#7-issue_assignees)
8. [boards](#8-boards)
9. [board_columns](#9-board_columns)
10. [sprints](#10-sprints)
11. [comments](#11-comments)
12. [attachments](#12-attachments)
13. [labels](#13-labels)
14. [issue_labels](#14-issue_labels)
15. [activity_logs](#15-activity_logs)

---

## 1. `users`

Stores all registered user accounts.

| Column | Type | Constraints | Description |
|---|---|---|---|
| `id` | `bigint` | PK, auto-increment | Unique user ID |
| `name` | `varchar(255)` | NOT NULL | Full name |
| `email` | `varchar(255)` | UNIQUE, NOT NULL | Login email |
| `password` | `varchar(255)` | NOT NULL | Bcrypt hashed password |
| `avatar_url` | `varchar(512)` | NULLABLE | Profile picture URL |
| `email_verified_at` | `timestamp` | NULLABLE | Email verification timestamp |
| `remember_token` | `varchar(100)` | NULLABLE | Laravel remember-me token |
| `created_at` | `timestamp` | NOT NULL | — |
| `updated_at` | `timestamp` | NOT NULL | — |

---

## 2. `organizations`

Top-level workspace/tenant container. A user can belong to many organizations.

| Column | Type | Constraints | Description |
|---|---|---|---|
| `id` | `bigint` | PK, auto-increment | — |
| `name` | `varchar(255)` | NOT NULL | Organization name |
| `slug` | `varchar(100)` | UNIQUE, NOT NULL | URL-safe identifier (e.g. `acme-corp`) |
| `owner_id` | `bigint` | FK → users.id | User who created the organization |
| `avatar_url` | `varchar(512)` | NULLABLE | Organization logo URL |
| `created_at` | `timestamp` | NOT NULL | — |
| `updated_at` | `timestamp` | NOT NULL | — |

---

## 3. `organization_members`

Pivot table: maps users to organizations with a role.

| Column | Type | Constraints | Description |
|---|---|---|---|
| `id` | `bigint` | PK, auto-increment | — |
| `organization_id` | `bigint` | FK → organizations.id | — |
| `user_id` | `bigint` | FK → users.id | — |
| `role` | `enum('owner','admin','member')` | NOT NULL, default `member` | Permission level within the org |
| `joined_at` | `timestamp` | NOT NULL | When the user joined |

> **Unique constraint**: `(organization_id, user_id)`

---

## 4. `projects`

A project belongs to one organization. Similar to a Jira project.

| Column | Type | Constraints | Description |
|---|---|---|---|
| `id` | `bigint` | PK, auto-increment | — |
| `organization_id` | `bigint` | FK → organizations.id | Parent organization |
| `name` | `varchar(255)` | NOT NULL | Project display name |
| `key` | `varchar(10)` | NOT NULL | Short project key (e.g. `TP`, `CORE`) — used in issue codes |
| `description` | `text` | NULLABLE | Project description |
| `type` | `enum('scrum','kanban')` | NOT NULL, default `kanban` | Board methodology |
| `lead_id` | `bigint` | FK → users.id, NULLABLE | Project lead user |
| `is_archived` | `boolean` | NOT NULL, default `false` | Soft archive flag |
| `created_at` | `timestamp` | NOT NULL | — |
| `updated_at` | `timestamp` | NOT NULL | — |

> **Unique constraint**: `(organization_id, key)`

---

## 5. `project_members`

Controls who has access to a project and at what permission level.

| Column | Type | Constraints | Description |
|---|---|---|---|
| `id` | `bigint` | PK, auto-increment | — |
| `project_id` | `bigint` | FK → projects.id | — |
| `user_id` | `bigint` | FK → users.id | — |
| `role` | `enum('admin','developer','viewer')` | NOT NULL, default `developer` | Project-level permission |

> **Unique constraint**: `(project_id, user_id)`

---

## 6. `issues`

The core entity. Represents any unit of work (Epic, Story, Task, Bug).

| Column | Type | Constraints | Description |
|---|---|---|---|
| `id` | `bigint` | PK, auto-increment | — |
| `project_id` | `bigint` | FK → projects.id | Parent project |
| `board_column_id` | `bigint` | FK → board_columns.id, NULLABLE | Current column on the board |
| `sprint_id` | `bigint` | FK → sprints.id, NULLABLE | Active sprint (null = backlog) |
| `parent_id` | `bigint` | FK → issues.id, NULLABLE | Parent issue (for sub-tasks under an epic) |
| `code` | `varchar(20)` | NOT NULL | Human-readable ID (e.g. `TP-42`) |
| `title` | `varchar(512)` | NOT NULL | Issue summary/title |
| `description` | `longtext` | NULLABLE | Rich-text description |
| `type` | `enum('epic','story','task','bug','subtask')` | NOT NULL, default `task` | Issue type |
| `status` | `enum('backlog','todo','in_progress','in_review','done')` | NOT NULL, default `backlog` | Current workflow status |
| `priority` | `enum('lowest','low','medium','high','highest')` | NOT NULL, default `medium` | Priority level |
| `story_points` | `tinyint` | NULLABLE | Estimation in points |
| `reporter_id` | `bigint` | FK → users.id | User who created the issue |
| `due_date` | `date` | NULLABLE | Target completion date |
| `created_at` | `timestamp` | NOT NULL | — |
| `updated_at` | `timestamp` | NOT NULL | — |

> **Unique constraint**: `(project_id, code)`

---

## 7. `issue_assignees`

Many-to-many: multiple users can be assigned to one issue.

| Column | Type | Constraints | Description |
|---|---|---|---|
| `issue_id` | `bigint` | FK → issues.id | — |
| `user_id` | `bigint` | FK → users.id | — |

> **Primary key**: `(issue_id, user_id)`

---

## 8. `boards`

A board belongs to a project. Kanban projects have one board; Scrum projects may have multiple sprint boards.

| Column | Type | Constraints | Description |
|---|---|---|---|
| `id` | `bigint` | PK, auto-increment | — |
| `project_id` | `bigint` | FK → projects.id | — |
| `name` | `varchar(255)` | NOT NULL | Board name (e.g. `Main Board`) |
| `created_at` | `timestamp` | NOT NULL | — |
| `updated_at` | `timestamp` | NOT NULL | — |

---

## 9. `board_columns`

Defines the columns of a board (workflow states). Issues move between columns.

| Column | Type | Constraints | Description |
|---|---|---|---|
| `id` | `bigint` | PK, auto-increment | — |
| `board_id` | `bigint` | FK → boards.id | Parent board |
| `name` | `varchar(100)` | NOT NULL | Column label (e.g. `In Progress`) |
| `mapped_status` | `enum('backlog','todo','in_progress','in_review','done')` | NOT NULL | Maps this column to the standard issue status |
| `position` | `tinyint` | NOT NULL | Sort order (left to right) |
| `created_at` | `timestamp` | NOT NULL | — |
| `updated_at` | `timestamp` | NOT NULL | — |

---

## 10. `sprints`

Scrum sprints belong to a project and contain issues from the backlog.

| Column | Type | Constraints | Description |
|---|---|---|---|
| `id` | `bigint` | PK, auto-increment | — |
| `project_id` | `bigint` | FK → projects.id | — |
| `name` | `varchar(255)` | NOT NULL | Sprint name (e.g. `Sprint 1`) |
| `goal` | `text` | NULLABLE | Sprint goal description |
| `status` | `enum('planned','active','completed')` | NOT NULL, default `planned` | Sprint lifecycle state |
| `starts_at` | `date` | NULLABLE | Sprint start date |
| `ends_at` | `date` | NULLABLE | Sprint end date |
| `created_at` | `timestamp` | NOT NULL | — |
| `updated_at` | `timestamp` | NOT NULL | — |

---

## 11. `comments`

Threaded comments on issues.

| Column | Type | Constraints | Description |
|---|---|---|---|
| `id` | `bigint` | PK, auto-increment | — |
| `issue_id` | `bigint` | FK → issues.id | Parent issue |
| `user_id` | `bigint` | FK → users.id | Comment author |
| `parent_id` | `bigint` | FK → comments.id, NULLABLE | Parent comment (for thread replies) |
| `body` | `text` | NOT NULL | Comment content |
| `created_at` | `timestamp` | NOT NULL | — |
| `updated_at` | `timestamp` | NOT NULL | — |

---

## 12. `attachments`

File attachments linked to issues.

| Column | Type | Constraints | Description |
|---|---|---|---|
| `id` | `bigint` | PK, auto-increment | — |
| `issue_id` | `bigint` | FK → issues.id | — |
| `uploaded_by` | `bigint` | FK → users.id | — |
| `filename` | `varchar(255)` | NOT NULL | Original file name |
| `path` | `varchar(512)` | NOT NULL | Storage path (S3 key or local path) |
| `mime_type` | `varchar(100)` | NOT NULL | MIME type (e.g. `image/png`) |
| `size_bytes` | `bigint` | NOT NULL | File size in bytes |
| `created_at` | `timestamp` | NOT NULL | — |

---

## 13. `labels`

Reusable labels/tags scoped to a project.

| Column | Type | Constraints | Description |
|---|---|---|---|
| `id` | `bigint` | PK, auto-increment | — |
| `project_id` | `bigint` | FK → projects.id | — |
| `name` | `varchar(100)` | NOT NULL | Label text (e.g. `bug`, `frontend`) |
| `color` | `varchar(7)` | NOT NULL | Hex color (e.g. `#e11d48`) |

> **Unique constraint**: `(project_id, name)`

---

## 14. `issue_labels`

Many-to-many: labels applied to issues.

| Column | Type | Constraints | Description |
|---|---|---|---|
| `issue_id` | `bigint` | FK → issues.id | — |
| `label_id` | `bigint` | FK → labels.id | — |

> **Primary key**: `(issue_id, label_id)`

---

## 15. `activity_logs`

Immutable audit trail. Records every significant state change across all entities.

| Column | Type | Constraints | Description |
|---|---|---|---|
| `id` | `bigint` | PK, auto-increment | — |
| `user_id` | `bigint` | FK → users.id, NULLABLE | User who triggered the event |
| `entity_type` | `varchar(50)` | NOT NULL | Model name (e.g. `issue`, `sprint`) |
| `entity_id` | `bigint` | NOT NULL | ID of the changed entity |
| `action` | `varchar(50)` | NOT NULL | Event type (e.g. `status_changed`, `assigned`, `commented`) |
| `before` | `json` | NULLABLE | Snapshot of changed fields before the action |
| `after` | `json` | NULLABLE | Snapshot of changed fields after the action |
| `created_at` | `timestamp` | NOT NULL | — |

---

## Relationships Summary

```
users
  ├── owns many → organizations  (via owner_id)
  ├── belongs to many → organizations  (via organization_members)
  ├── belongs to many → projects  (via project_members)
  ├── reports many → issues  (via reporter_id)
  ├── assigned to many → issues  (via issue_assignees)
  └── authors many → comments

organizations
  └── has many → projects

projects
  ├── has one → board
  ├── has many → board_columns (through board)
  ├── has many → sprints
  ├── has many → issues
  └── has many → labels

issues
  ├── belongs to → project
  ├── belongs to → board_column
  ├── belongs to → sprint  (nullable = backlog)
  ├── belongs to → issue (parent, for subtasks/epics)
  ├── has many → issue_assignees
  ├── has many → comments
  ├── has many → attachments
  └── has many → issue_labels

boards
  └── has many → board_columns

sprints
  └── has many → issues
```
