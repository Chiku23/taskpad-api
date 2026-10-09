# TaskPad Documentation

This directory contains the architecture and planning documentation for the TaskPad API backend.
Use these docs as a reference while building each module.

---

## Contents

| File | Description |
|---|---|
| [database-schema.md](./database-schema.md) | Full database table definitions, columns, types, and relationships |
| [architecture.md](./architecture.md) | Backend folder structure, module responsibilities, and request lifecycle |
| [api-plan.md](./api-plan.md) | Complete REST API endpoint plan with request/response contracts |
| [auth-flow.md](./auth-flow.md) | Authentication and session token flow diagrams |

---

## Build Order

Follow this order when building modules to avoid dependency issues:

1. **Users & Auth** — Foundation of every other module.
2. **Organizations** — Workspace/tenant container.
3. **Projects** — Belong to organizations, scoped by members.
4. **Issues** — Core entity; depends on projects and users.
5. **Boards & Columns** — Visual representation of issue states.
6. **Sprints** — Container for scrum-style issue scheduling.
7. **Comments** — Threaded discussion per issue.
8. **Activity Logs** — Audit trail for changes across all entities.
