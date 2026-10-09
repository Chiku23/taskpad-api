# API Plan

Full REST API endpoint plan for TaskPad. Use this as the implementation checklist.
All routes are prefixed with `/api`. All protected routes require `Authorization: Bearer <token>`.

Legend: ✅ Done | 🔲 Not Started

---

## Auth

| Status | Method | Endpoint | Description |
|---|---|---|---|
| ✅ | `POST` | `/api/register` | Register a new user |
| ✅ | `POST` | `/api/login` | Login and receive an API token |
| 🔲 | `POST` | `/api/logout` | Revoke the current token |
| 🔲 | `GET` | `/api/me` | Get the authenticated user's profile |

---

## Organizations

| Status | Method | Endpoint | Description |
|---|---|---|---|
| 🔲 | `GET` | `/api/organizations` | List all organizations the user belongs to |
| 🔲 | `POST` | `/api/organizations` | Create a new organization |
| 🔲 | `GET` | `/api/organizations/{org}` | Get organization details |
| 🔲 | `PUT` | `/api/organizations/{org}` | Update organization name/avatar |
| 🔲 | `DELETE` | `/api/organizations/{org}` | Delete organization (owner only) |
| 🔲 | `GET` | `/api/organizations/{org}/members` | List all members of the organization |
| 🔲 | `POST` | `/api/organizations/{org}/members` | Invite a user to the organization |
| 🔲 | `PUT` | `/api/organizations/{org}/members/{user}` | Update a member's role |
| 🔲 | `DELETE` | `/api/organizations/{org}/members/{user}` | Remove a member from the organization |

---

## Projects

| Status | Method | Endpoint | Description |
|---|---|---|---|
| 🔲 | `GET` | `/api/organizations/{org}/projects` | List all projects in an organization |
| 🔲 | `POST` | `/api/organizations/{org}/projects` | Create a new project |
| 🔲 | `GET` | `/api/projects/{project}` | Get project details |
| 🔲 | `PUT` | `/api/projects/{project}` | Update project name, key, lead |
| 🔲 | `DELETE` | `/api/projects/{project}` | Archive or delete a project |
| 🔲 | `GET` | `/api/projects/{project}/members` | List project members |
| 🔲 | `POST` | `/api/projects/{project}/members` | Add a member to a project |
| 🔲 | `PUT` | `/api/projects/{project}/members/{user}` | Update member's project role |
| 🔲 | `DELETE` | `/api/projects/{project}/members/{user}` | Remove member from project |

---

## Issues

| Status | Method | Endpoint | Description |
|---|---|---|---|
| 🔲 | `GET` | `/api/projects/{project}/issues` | List all issues (supports filters: status, type, assignee, sprint) |
| 🔲 | `POST` | `/api/projects/{project}/issues` | Create a new issue |
| 🔲 | `GET` | `/api/issues/{issue}` | Get full issue detail |
| 🔲 | `PUT` | `/api/issues/{issue}` | Update issue fields |
| 🔲 | `DELETE` | `/api/issues/{issue}` | Delete an issue |
| 🔲 | `PATCH` | `/api/issues/{issue}/status` | Move issue to a new status/column |
| 🔲 | `PATCH` | `/api/issues/{issue}/assignees` | Set/update assignees |
| 🔲 | `PATCH` | `/api/issues/{issue}/sprint` | Move issue to a sprint or back to backlog |

### Issue Filters (query params on `GET /issues`)

| Param | Type | Description |
|---|---|---|
| `status` | `string` | Filter by status: `backlog`, `todo`, `in_progress`, etc. |
| `type` | `string` | Filter by type: `epic`, `story`, `task`, `bug` |
| `assignee` | `integer` | Filter by assigned user ID |
| `sprint_id` | `integer` | Filter by sprint (omit for backlog) |
| `priority` | `string` | Filter by priority level |
| `label` | `integer` | Filter by label ID |
| `search` | `string` | Full-text search on title |

---

## Boards

| Status | Method | Endpoint | Description |
|---|---|---|---|
| 🔲 | `GET` | `/api/projects/{project}/board` | Get the board with columns and issues |
| 🔲 | `PUT` | `/api/projects/{project}/board/columns` | Reorder board columns |
| 🔲 | `POST` | `/api/projects/{project}/board/columns` | Add a custom column |
| 🔲 | `PUT` | `/api/board-columns/{column}` | Rename a column |
| 🔲 | `DELETE` | `/api/board-columns/{column}` | Remove a custom column |

---

## Sprints

| Status | Method | Endpoint | Description |
|---|---|---|---|
| 🔲 | `GET` | `/api/projects/{project}/sprints` | List all sprints for a project |
| 🔲 | `POST` | `/api/projects/{project}/sprints` | Create a new sprint |
| 🔲 | `GET` | `/api/sprints/{sprint}` | Get sprint details and issues |
| 🔲 | `PUT` | `/api/sprints/{sprint}` | Update sprint name, goal, dates |
| 🔲 | `POST` | `/api/sprints/{sprint}/start` | Activate a planned sprint |
| 🔲 | `POST` | `/api/sprints/{sprint}/complete` | Mark sprint as completed, move unfinished issues to backlog |
| 🔲 | `DELETE` | `/api/sprints/{sprint}` | Delete a sprint (planned only) |

---

## Comments

| Status | Method | Endpoint | Description |
|---|---|---|---|
| 🔲 | `GET` | `/api/issues/{issue}/comments` | List comments on an issue |
| 🔲 | `POST` | `/api/issues/{issue}/comments` | Add a comment |
| 🔲 | `PUT` | `/api/comments/{comment}` | Edit a comment (own comments only) |
| 🔲 | `DELETE` | `/api/comments/{comment}` | Delete a comment (own or admin) |

---

## Labels

| Status | Method | Endpoint | Description |
|---|---|---|---|
| 🔲 | `GET` | `/api/projects/{project}/labels` | List all labels in a project |
| 🔲 | `POST` | `/api/projects/{project}/labels` | Create a label |
| 🔲 | `PUT` | `/api/labels/{label}` | Update label name or color |
| 🔲 | `DELETE` | `/api/labels/{label}` | Delete a label |

---

## Attachments

| Status | Method | Endpoint | Description |
|---|---|---|---|
| 🔲 | `POST` | `/api/issues/{issue}/attachments` | Upload a file to an issue |
| 🔲 | `GET` | `/api/attachments/{attachment}` | Download or get a signed URL for a file |
| 🔲 | `DELETE` | `/api/attachments/{attachment}` | Delete an attachment |

---

## Activity Logs

| Status | Method | Endpoint | Description |
|---|---|---|---|
| 🔲 | `GET` | `/api/issues/{issue}/activity` | Get the activity timeline for an issue |
| 🔲 | `GET` | `/api/projects/{project}/activity` | Get recent activity across a project |

---

## Utility

| Status | Method | Endpoint | Description |
|---|---|---|---|
| ✅ | `GET` | `/api/health` | API health check |
