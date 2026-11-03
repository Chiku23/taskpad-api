# API Plan

Full REST API endpoint plan for TaskPad. Use this as the implementation checklist.
All routes are prefixed with `/api`. All protected routes require `Authorization: Bearer <token>`.

Legend: ✅ Done | 🔲 Not Started

---

## Auth

| Status | Method | Endpoint        | Description                          |
| ------ | ------ | --------------- | ------------------------------------ |
| ✅     | `POST` | `/api/register` | Register a new user                  |
| ✅     | `POST` | `/api/login`    | Login and receive an API token       |
| ✅     | `POST` | `/api/logout`   | Revoke the current token             |
| ✅     | `GET`  | `/api/me`       | Get the authenticated user's profile |

---

## Organizations

All organization routes support both `/api/organization` (singular) and `/api/organizations` (plural).

| Status | Method   | Endpoint                                  | Access Level       | Description                                                            |
| ------ | -------- | ----------------------------------------- | ------------------ | ---------------------------------------------------------------------- |
| ✅     | `GET`    | `/api/organization`                      | Authenticated User | List all organizations the user belongs to (with role, counts, owner)  |
| ✅     | `POST`   | `/api/organization/create`                | Authenticated User | Create a new organization and assign creator as `owner`                |
| ✅     | `GET`    | `/api/organization/{orgSlug}`             | Member / Admin / Owner | Get organization details (with `user_role`, counts, owner)        |
| ✅     | `POST` / `PUT` | `/api/organization/{orgSlug}`       | Admin / Owner      | Update organization name or slug                                       |
| ✅     | `DELETE` | `/api/organization/{orgSlug}`             | Owner only         | Delete organization and all associated data                            |
| ✅     | `GET`    | `/api/organization/{orgSlug}/members`     | Member / Admin / Owner | List all members with user details (id, name, email)              |
| ✅     | `POST`   | `/api/organization/{orgSlug}/members`     | Admin / Owner      | Add a member by email (`admin` or `member` role)                      |
| ✅     | `PUT`    | `/api/organization/{orgSlug}/members/{user}` | Admin / Owner   | Update a member's role (owner cannot be altered; admin cannot alter admin) |
| ✅     | `DELETE` | `/api/organization/{orgSlug}/members/{user}` | Admin / Owner   | Remove a member (owner cannot be removed; admin cannot remove admin)  |

---

## Projects

All project routes support both `/api/project` (singular) and `/api/projects` (plural).

| Status | Method   | Endpoint                                 | Access Level                | Description                                                       |
| ------ | -------- | ---------------------------------------- | --------------------------- | ----------------------------------------------------------------- |
| ✅     | `GET`    | `/api/organization/{orgSlug}/projects`   | Org Member / Admin / Owner  | List all projects in org (with lead info, members count, user role) |
| ✅     | `POST`   | `/api/organization/{orgSlug}/projects`   | Org Member / Admin / Owner  | Create project in org (assigns lead as project owner)             |
| ✅     | `GET`    | `/api/project/{projectSlug}`             | Project Member / Org Admin / Org Owner | Get project details (with lead, org, members count, user role) |
| ✅     | `PUT`    | `/api/project/{projectSlug}`             | Project Admin / Owner OR Org Admin / Owner | Update project name, key, description, lead, archive status |
| ✅     | `DELETE` | `/api/project/{projectSlug}`             | Project Admin / Owner OR Org Admin / Owner | Delete a project                                                  |
| ✅     | `GET`    | `/api/project/{projectSlug}/members`     | Project Member / Org Admin / Org Owner | List project members with user info (id, name, email)            |
| ✅     | `POST`   | `/api/project/{projectSlug}/members`     | Project Admin / Owner OR Org Admin / Owner | Add project member (user must be org member first)                |
| ✅     | `PUT`    | `/api/project/{projectSlug}/members/{user}` | Project Admin / Owner OR Org Admin / Owner | Update member's project role (`admin` or `member`)             |
| ✅     | `DELETE` | `/api/project/{projectSlug}/members/{user}` | Project Admin / Owner OR Org Admin / Owner | Remove member from project (owner cannot be removed)              |

---

## Issues

| Status | Method   | Endpoint                         | Description                                                        |
| ------ | -------- | -------------------------------- | ------------------------------------------------------------------ |
| ✅     | `GET`    | `/api/projects/{project}/issues` | List all issues (supports filters: status, type, assignee, sprint) |
| ✅     | `POST`   | `/api/projects/{project}/issues` | Create a new issue                                                 |
| ✅     | `GET`    | `/api/issues/{issue}`            | Get full issue detail                                              |
| ✅     | `PUT`    | `/api/issues/{issue}`            | Update issue fields                                                |
| ✅     | `DELETE` | `/api/issues/{issue}`            | Delete an issue                                                    |
| ✅     | `PATCH`  | `/api/issues/{issue}/status`     | Move issue to a new status/column                                  |
| ✅     | `PATCH`  | `/api/issues/{issue}/assignees`  | Set/update assignees                                               |
| ✅     | `PATCH`  | `/api/issues/{issue}/sprint`     | Move issue to a sprint or back to backlog                          |

### Issue Filters (query params on `GET /issues`)

| Param       | Type      | Description                                              |
| ----------- | --------- | -------------------------------------------------------- |
| `status`    | `string`  | Filter by status: `backlog`, `todo`, `in_progress`, etc. |
| `type`      | `string`  | Filter by type: `epic`, `story`, `task`, `bug`           |
| `assignee`  | `integer` | Filter by assigned user ID                               |
| `sprint_id` | `integer` | Filter by sprint (omit for backlog)                      |
| `priority`  | `string`  | Filter by priority level                                 |
| `label`     | `integer` | Filter by label ID                                       |
| `search`    | `string`  | Full-text search on title                                |

---

## Boards

| Status | Method   | Endpoint                                | Description                           |
| ------ | -------- | --------------------------------------- | ------------------------------------- |
| ✅     | `GET`    | `/api/projects/{project}/board`         | Get the board with columns and issues |
| ✅     | `PUT`    | `/api/projects/{project}/board/columns` | Reorder board columns                 |
| ✅     | `POST`   | `/api/projects/{project}/board/columns` | Add a custom column                   |
| ✅     | `PUT`    | `/api/board-columns/{column}`           | Rename a column                       |
| ✅     | `DELETE` | `/api/board-columns/{column}`           | Remove a custom column                |

---

## Sprints

| Status | Method   | Endpoint                          | Description                                                 |
| ------ | -------- | --------------------------------- | ----------------------------------------------------------- |
| ✅     | `GET`    | `/api/projects/{project}/sprints` | List all sprints for a project                              |
| ✅     | `POST`   | `/api/projects/{project}/sprints` | Create a new sprint                                         |
| ✅     | `GET`    | `/api/sprints/{sprint}`           | Get sprint details and issues                               |
| ✅     | `PUT`    | `/api/sprints/{sprint}`           | Update sprint name, goal, dates                             |
| ✅     | `POST`   | `/api/sprints/{sprint}/start`     | Activate a planned sprint                                   |
| ✅     | `POST`   | `/api/sprints/{sprint}/complete`  | Mark sprint as completed, move unfinished issues to backlog |
| ✅     | `DELETE` | `/api/sprints/{sprint}`           | Delete a sprint (planned only)                              |

---

## Comments

| Status | Method   | Endpoint                       | Description                        |
| ------ | -------- | ------------------------------ | ---------------------------------- |
| ✅     | `GET`    | `/api/issues/{issue}/comments` | List comments on an issue          |
| ✅     | `POST`   | `/api/issues/{issue}/comments` | Add a comment                      |
| ✅     | `PUT`    | `/api/comments/{comment}`      | Edit a comment (own comments only) |
| ✅     | `DELETE` | `/api/comments/{comment}`      | Delete a comment (own or admin)    |

---

## Labels

| Status | Method   | Endpoint                         | Description                  |
| ------ | -------- | -------------------------------- | ---------------------------- |
| ✅     | `GET`    | `/api/projects/{project}/labels` | List all labels in a project |
| ✅     | `POST`   | `/api/projects/{project}/labels` | Create a label               |
| ✅     | `PUT`    | `/api/labels/{label}`            | Update label name or color   |
| ✅     | `DELETE` | `/api/labels/{label}`            | Delete a label               |

---

## Attachments

| Status | Method   | Endpoint                          | Description                             |
| ------ | -------- | --------------------------------- | --------------------------------------- |
| ✅     | `POST`   | `/api/issues/{issue}/attachments` | Upload a file to an issue               |
| ✅     | `GET`    | `/api/attachments/{attachment}`   | Download or get a signed URL for a file |
| ✅     | `DELETE` | `/api/attachments/{attachment}`   | Delete an attachment                    |

---

## Activity Logs

| Status | Method | Endpoint                           | Description                            |
| ------ | ------ | ---------------------------------- | -------------------------------------- |
| ✅     | `GET`  | `/api/issues/{issue}/activity`     | Get the activity timeline for an issue |
| ✅     | `GET`  | `/api/projects/{project}/activity` | Get recent activity across a project   |

---

## Utility

| Status | Method | Endpoint      | Description      |
| ------ | ------ | ------------- | ---------------- |
| ✅     | `GET`  | `/api/health` | API health check |
