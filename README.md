# TaskPad API

Backend RESTful API service for **TaskPad** — a Jira-inspired agile project management and issue-tracking platform built with Laravel 12 and OpenAPI / Swagger.

---

## Overview

TaskPad API provides the foundational backend architecture for agile task tracking, workflows, and team collaboration. It is designed to serve the TaskPad web frontend with secure authentication, workspace management, backlog and board workflows, and customizable issue lifecycles.

---

## Core Features (Roadmap & Status)

- [x] **API Health Monitoring**: Uptime check endpoint.
- [x] **Authentication & Users**: User registration and login endpoints.
- [x] **OpenAPI / Swagger Documentation**: Interactive API testing with `l5-swagger` (PHP 8 attributes).
- [ ] **Projects & Workspaces**: Multi-project management with project keys (e.g., `TP-101`).
- [ ] **Kanban & Scrum Boards**: Backlog, sprint management, and customizable board columns.
- [ ] **Issue Tracking**: Epics, Stories, Tasks, and Bugs with priorities, assignees, and attachments.
- [ ] **Activity & Audit Logs**: Status transitions, comments, and change history.

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

## API Endpoints

### 1. Health Check

Verifies API server status and availability.

- **Method:** `GET`
- **Path:** `/api/health`

**Response (`200 OK`):**
```json
{
  "status": "OK"
}
```

---

### 2. User Registration

Creates a new user account for the workspace.

- **Method:** `POST`
- **Path:** `/api/register`

**Request Parameters:**

| Parameter | Type | Required | Description |
|---|---|---|---|
| `name` | `string` | Yes | User's full name |
| `email` | `string` | Yes | Unique email address |
| `password` | `string` | Yes | Account password |
| `confirmpassword` | `string` | Yes | Password confirmation |

**Response (`200 OK` - Success):**
```json
{
  "status": "true",
  "message": "user created."
}
```

**Response (`200 OK` - Validation Failure):**
```json
{
  "status": "false",
  "message": "please fill all the fields."
}
```

```json
{
  "status": "false",
  "message": "confirm password does not match."
}
```

```json
{
  "status": "false",
  "message": "email already registered."
}
```

---

### 3. User Login

Authenticates user credentials and initiates a session.

- **Method:** `POST`
- **Path:** `/api/login`

**Request Parameters:**

| Parameter | Type | Required | Description |
|---|---|---|---|
| `email` | `string` | Yes | Registered email address |
| `password` | `string` | Yes | Account password |

**Response (`200 OK`):**
```json
{
  "email": "user@example.com",
  "password": "secretpassword"
}
```

---

## Architecture & Directory Structure

```text
taskpad-api/
├── app/
│   ├── Http/
│   │   └── Controllers/
│   │       ├── Auth/
│   │       │   └── AuthController.php    # Auth operations & Swagger annotations
│   │       ├── Controller.php            # Base controller & OpenApi metadata
│   │       └── HealthController.php      # Health check endpoint
│   └── Models/
│       └── User.php                      # User Eloquent model
├── config/
│   └── l5-swagger.php                    # OpenAPI / Swagger configuration
├── routes/
│   └── api.php                           # REST API routes
└── storage/
    └── api-docs/                         # Generated OpenAPI JSON & YAML artifacts
```
