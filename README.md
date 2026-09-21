# Task Management API

Multi-tenant task management backend built with Laravel 12. It powers the
[Next.js Kanban frontend](https://github.com/cristiangirlea/task-management-next-react)
and exposes the same data to AI agents through an [MCP](https://modelcontextprotocol.io) server.

```
Browser ──► Next.js (task-management-next-react) ──► Laravel REST API (/api)
Claude / other MCP clients ─────────────────────────► Laravel MCP server (/mcp)
                                                         │
                                                   PostgreSQL + Redis
```

## Features

- Workspaces (tenants) with owner and member roles, email invitations, projects and tasks with strict tenant isolation
- Kanban-ready tasks: `status` column, `position` inside the column, `priority` 1–5, due dates, assignee
- Token authentication with Laravel Sanctum (browser sessions and personal access tokens for agents/scripts)
- Consistent JSON envelope and localized messages (English, French)
- MCP server so agents can list, create, update and move tasks on a user's behalf

## Requirements

- PHP 8.4 with `pdo_pgsql` (or `pdo_sqlite` for local work) and the `redis` extension
- Composer
- PostgreSQL 14+ (SQLite works for local development and is used by the test suite)

## Quick start

```bash
composer install
cp .env.example .env
php artisan key:generate

# zero-setup local database
sed -i 's/^DB_CONNECTION=.*/DB_CONNECTION=sqlite/' .env
touch database/database.sqlite

php artisan migrate --seed      # seeds demo@example.com / password in the "Demo Workspace"
php artisan serve               # http://localhost:8000
```

Point the frontend at it with `NEXT_PUBLIC_API_URL=http://localhost:8000/api`.
For the full stack (Postgres, Redis, nginx, frontend) see
[task-management-docker](https://github.com/cristiangirlea/task-management-docker).

## Configuration

| Variable | Purpose |
| --- | --- |
| `DB_*` | Database connection (PostgreSQL by default, SQLite supported) |
| `REDIS_*` | Cache/queue backend when `CACHE_STORE`/`QUEUE_CONNECTION` are set to `redis` |
| `CORS_ALLOWED_ORIGINS` | Comma-separated browser origins allowed to call `/api` and `/mcp` (default `*`) |
| `FRONTEND_URL` | Where the Next.js app lives; invitation links point at `<FRONTEND_URL>/invite/<token>` |
| `MAIL_*` | Mailer for invitation emails (`log` by default) |
| `APP_LOCALE` | `en` or `fr` for API messages |

## Authentication and tenancy

- `POST /api/register` creates a **workspace (tenant)** and its first user (the **owner**), and returns a bearer token.
- Owners invite people by email; invitees accept at `<FRONTEND_URL>/invite/<token>` and join as **members**.
  Owners manage the workspace name, members and invitations; members use projects and tasks.
- Every other route requires `Authorization: Bearer <token>`.
- All project and task queries are scoped to the token owner's tenant by a global Eloquent scope
  (`App\Models\Scopes\TenantScope`); policies (`App\Policies\*`) are a second check.
  Records from another tenant are simply invisible (404), never 403, so IDs cannot be enumerated.
- `POST /api/tokens` issues long-lived personal access tokens (for MCP clients, scripts, CI).

## API

All responses use one envelope:

```json
{ "status": "success", "message": "Tasks retrieved successfully", "data": [] }
{ "status": "error",   "message": "Validation failed.", "errors": { "title": ["Task title is required."] } }
```

`DELETE` endpoints return `204 No Content`. Dates are ISO 8601.

| Method | Path | Body / query | Notes |
| --- | --- | --- | --- |
| POST | `/api/register` | `name, email, password, password_confirmation, workspace_name?` | 201, `data: {user, token}` |
| POST | `/api/login` | `email, password` | `data: {user, token}` |
| POST | `/api/logout` | | revokes the current token |
| GET / PUT / DELETE | `/api/user` | `name?, email?, password?` | current user (includes `tenant`) |
| GET / PUT | `/api/tenant` | `name?, slug?, domain?, settings?` | current workspace (PUT: owners only) |
| GET | `/api/tenant/members` | | members with roles |
| DELETE | `/api/tenant/members/{id}` | | owners only; removes the account, their tasks become unassigned |
| GET / POST | `/api/tenant/invitations` | `email` | pending invitations / invite (owners only, sends an email) |
| DELETE | `/api/tenant/invitations/{id}` | | revoke |
| GET | `/api/invitations/{token}` | | public preview of an invitation |
| POST | `/api/invitations/{token}/accept` | `name, password, password_confirmation` | public; creates the member and returns `{user, token}` |
| GET / POST | `/api/tokens` | `name` | list tokens / create one (plain-text token returned once) |
| DELETE | `/api/tokens/{id}` | | revoke |
| GET / POST | `/api/projects` | `name, description?` | `tasks_count` included |
| GET / PUT / DELETE | `/api/projects/{id}` | `name?, description?` | |
| GET | `/api/tasks` | `?project_id=&status=` | ordered by `position` |
| POST | `/api/tasks` | `title, project_id, description?, status?, priority?, due_date?, user_id?` | new tasks go to the bottom of their column |
| GET / PUT / DELETE | `/api/tasks/{id}` | any task field | changing `status` moves the task to the bottom of the new column |
| POST | `/api/tasks/reorder` | `status, task_ids[]` | puts the listed tasks in `status`, positioned by array index |

Task `status` is one of `pending`, `in_progress`, `completed`.

## MCP server

The app ships an [MCP](https://modelcontextprotocol.io) server (built with the official
[`laravel/mcp`](https://laravel.com/docs/mcp) package) at `POST /mcp`, using the Streamable HTTP
transport. It is protected by the same Sanctum tokens as the REST API, so an agent always acts
as one user inside one workspace and sees exactly what that user sees.

| Tool | What it does |
| --- | --- |
| `list_projects` | Projects in the workspace with task counts |
| `create_project` | Create a project |
| `list_members` | People in the workspace, for assigning tasks |
| `list_tasks` | Tasks, filterable by `project_id` and `status`, in board order |
| `create_task` | Create a task at the bottom of a column |
| `update_task` | Change title, description, priority, due date, assignee, status or project |
| `move_task` | Drag a task to a column and position, renumbering both columns |
| `delete_task` | Soft-delete a task (marked destructive) |

Connect a client:

1. Sign in and create a token: `POST /api/tokens` with `{"name": "claude"}` (or use the token returned by `/api/login`).
2. Point the client at `https://<your-host>/mcp` with an `Authorization: Bearer <token>` header. For example with Claude Code:

   ```bash
   claude mcp add --transport http task-board https://<your-host>/mcp \
     --header "Authorization: Bearer <token>"
   ```

   Any MCP client that supports Streamable HTTP with custom headers works the same way.
3. Ask the agent things like "what is overdue in the Launch project?" or "move task 12 to in progress, top of the column".

The server is intentionally **not** registered as a local stdio server: without an authenticated
user there is no tenant to scope to. Clients that require OAuth instead of a static token can be
supported later through Laravel Passport, which `laravel/mcp` integrates with.

Implementation: `app/Mcp/Servers/TaskBoardServer.php`, tools in `app/Mcp/Tools`, route in `routes/ai.php`,
tests in `tests/Feature/Mcp`.

## Development

```bash
vendor/bin/pest          # test suite (SQLite in-memory, no services needed)
vendor/bin/pint          # code style (Laravel preset)
php artisan make:service Foo         # scaffolding helpers shipped with the app
php artisan make:repository Foo
php artisan make:response-handler Foo
```

CI (`.github/workflows/ci.yml`) runs Pint and Pest on every push and pull request.

### Layout

```
app/Http/Controllers   thin controllers → services
app/Http/Requests      form requests
app/Http/Resources     JSON shapes (shared by the REST API and the MCP tools)
app/Mcp                MCP server and tools
app/Models/Concerns    BelongsToTenant trait (global scope + auto tenant_id)
app/Policies           per-tenant authorization
app/Services           TaskService (create / update / move / reorder), TenantService
app/Repositories       query layer
app/Validation         TaskRules: tenant-aware validation shared by HTTP and MCP
resources/lang         en / fr messages
```

## Roadmap

- Password reset, email verification, workspace switching (one workspace per account today)
- Real-time board updates (Laravel Reverb)
- Pagination on list endpoints
- Upgrade to Laravel 13
- OAuth (Passport) for MCP clients that cannot send a static bearer token
