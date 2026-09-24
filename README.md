# Task Management API

Multi-tenant task management backend built with Laravel 13. It powers the
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
- Password reset, email verification, and throttling on every unauthenticated endpoint
- Consistent JSON envelope and localized messages (English, French)
- MCP server so agents can list, create, update and move tasks on a user's behalf
- Per-seat billing with Stripe (Laravel Cashier): a free tier and a paid Team plan

## Requirements

- PHP 8.4 with `pdo_pgsql` (or `pdo_sqlite` for local work), `bcmath` (required by Cashier) and the `redis` extension
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
| `MAIL_*` | Mailer for invitation emails (`log` by default; `resend` with `RESEND_KEY` in production) |
| `STRIPE_KEY`, `STRIPE_SECRET` | Stripe API keys (test-mode keys locally) |
| `STRIPE_WEBHOOK_SECRET` | Signing secret of the webhook endpoint; signatures are checked when set |
| `STRIPE_PRICE_ID` | The Team plan: a recurring, monthly, per-unit Price |
| `CASHIER_PATH` | `api/stripe`, so the webhook is `POST /api/stripe/webhook` |
| `BILLING_FREE_SEATS` | Members a free workspace may have (default 3), pending invitations included |
| `BILLING_SEAT_PRICE_CENTS` | Seat price shown to customers (default 800); Stripe charges what the Price says |
| `APP_LOCALE` | `en` or `fr` for API messages |

## Authentication and tenancy

- `POST /api/register` creates a **workspace (tenant)** and its first user (the **owner**), and returns a bearer token.
- Owners invite people by email; invitees accept at `<FRONTEND_URL>/invite/<token>` and join as **members**.
  Owners manage the workspace name, members and invitations; members use projects and tasks.
  Listing invitations is owner-only, because an invitation's accept link *is* the invitee's
  credential: anyone who can read it can consume the invitation and take that identity.
- The tenant scope fails closed. A user with no workspace reaches nothing, rather than
  everything, which matters when upgrading a database written by an older release.
- Changing or resetting a password revokes the account's other tokens.
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
| POST | `/api/login` | `email, password` | `data: {user, token}`; throttled |
| POST | `/api/forgot-password` | `email` | always 200, identical for unknown addresses (no account enumeration) |
| POST | `/api/reset-password` | `token, email, password, password_confirmation` | revokes every existing token on success |
| GET | `/api/email/verify/{id}/{hash}` | signed link from the email | marks the address verified, redirects to `<FRONTEND_URL>/verify-email?status=…` |
| POST | `/api/email/verification-notification` | | resend (auth, throttled) |
| POST | `/api/logout` | | revokes the current token |
| GET / PUT / DELETE | `/api/user` | `name?, email?, password?` | current user (includes `tenant`) |
| GET / PUT | `/api/tenant` | `name?, slug?, domain?, settings?` | current workspace (PUT: owners only) |
| GET | `/api/tenant/members` | | members with roles |
| DELETE | `/api/tenant/members/{id}` | | owners only; removes the account, their tasks become unassigned |
| GET / POST | `/api/tenant/invitations` | `email` | pending invitations / invite (owners only, sends an email) |
| DELETE | `/api/tenant/invitations/{id}` | | revoke |
| GET | `/api/invitations/{token}` | | public preview of an invitation |
| POST | `/api/invitations/{token}/accept` | `name, password, password_confirmation` | public; creates the member and returns `{user, token}` |
| GET | `/api/billing` | | plan, seats used/limit, status (any member) |
| POST | `/api/billing/checkout` | | owners; `data.url` is a Stripe Checkout page for the Team plan |
| POST | `/api/billing/portal` | | owners; `data.url` is the Stripe billing portal (card, invoices, cancel) |
| POST | `/api/stripe/webhook` | Stripe event | Cashier's webhook, verified by signature |
| GET / POST | `/api/tokens` | `name` | list tokens / create one (plain-text token returned once) |
| DELETE | `/api/tokens/{id}` | | revoke |
| GET / POST | `/api/projects` | `name, description?` | `tasks_count` included |
| GET / PUT / DELETE | `/api/projects/{id}` | `name?, description?` | |
| GET | `/api/tasks` | `?project_id=&status=&assigned_to=&overdue=&due_before=&search=&limit=` | ordered by `position`; omitting `limit` returns every match |
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
| `workspace_overview` | One-call summary: every project with per-column and overdue counts |
| `list_tasks` | Tasks, filtered by project, status, assignee, overdue, due date or free text; bounded, and reports how many matched |
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
3. Ask the agent things like "what is overdue?", "what is assigned to me?", "find the invoicing work" or
   "move task 12 to in progress, top of the column". `list_tasks` answers each of those in a single filtered
   call and caps what it returns, so a large workspace does not flood the agent's context; when a result is
   truncated the reply says how many matched so the agent can narrow it.

The server is intentionally **not** registered as a local stdio server: without an authenticated
user there is no tenant to scope to. Clients that require OAuth instead of a static token can be
supported later through Laravel Passport, which `laravel/mcp` integrates with.

Implementation: `app/Mcp/Servers/TaskBoardServer.php`, tools in `app/Mcp/Tools`, route in `routes/ai.php`,
tests in `tests/Feature/Mcp`.

## Billing

The workspace is the paying customer (Laravel Cashier's billable model is `Tenant`).

- **Free**: up to `BILLING_FREE_SEATS` (3) members. Pending invitations hold a seat, so
  inviting past the limit, or accepting an invitation into a full workspace, returns
  **402** with a message the frontend shows next to an upgrade link.
- **Team**: no seat limit, billed per member per month. The Stripe subscription's quantity
  follows the member count: it is updated when someone joins or leaves, and Stripe's
  webhook writes the confirmed value back.
- A cancelled subscription stays Team until the paid period ends. A **past-due** one also
  stays Team while Stripe retries the card (`Cashier::keepPastDueSubscriptionsActive()`),
  and `GET /api/billing` reports `has_payment_problem` so the owner can fix it.

Setting it up in Stripe test mode:

1. Create a product with a recurring, monthly, per-unit Price (e.g. $8) and put its id in
   `STRIPE_PRICE_ID`; add the test keys to `STRIPE_KEY`/`STRIPE_SECRET`.
2. Forward webhooks locally with the Stripe CLI and copy the printed secret into
   `STRIPE_WEBHOOK_SECRET`:
   `stripe listen --forward-to localhost:8000/api/stripe/webhook`.
   In production, `php artisan cashier:webhook --url=https://<host>/api/stripe/webhook`
   creates the endpoint with the events Cashier handles.
3. Upgrade from Settings in the frontend and pay with the test card `4242 4242 4242 4242`.

## Rate limits

Unauthenticated endpoints are throttled in `AppServiceProvider` to blunt credential
stuffing, mass signups and mail floods:

| Limiter | Applies to | Limit |
| --- | --- | --- |
| `login` | `POST /api/login` | 5/min per email+IP, 20/min per IP |
| `register` | registration and invitation acceptance | 10/hour per IP |
| `mail` | forgot/reset password | 3/min per email+IP, 20/hour per IP |
| `verification` | resending the verification email | 3/min per user |

The login limiter is keyed on the email *and* the IP so that flooding one address
cannot lock its owner out from elsewhere.

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
app/Services           TaskService (create / update / move / reorder), TenantService,
                       InvitationService, BillingService (plans and seats)
app/Repositories       query layer
app/Validation         TaskRules: tenant-aware validation shared by HTTP and MCP
resources/lang         en / fr messages
```

## Roadmap

- Workspace switching (one workspace per account today)
- Two-factor authentication
- Real-time board updates (Laravel Reverb)
- Cursor pagination on the REST list endpoints (today they take a `limit`)
- OAuth (Passport) for MCP clients that cannot send a static bearer token
