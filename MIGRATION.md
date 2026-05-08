# Marlu BackEnd - Migration Guide

This file tracks the conversion from legacy `marluapp` to the new layered Laravel API.

## Architecture (after Phase 0)

```
app/
  Http/
    Controllers/{feature}/...   # thin: validate via FormRequest, delegate to Service
    Middleware/                  # ForceJson, JwtAuthenticate, RoleAuthorize, RequestId, ObservabilityLogger
    Requests/{feature}/...       # FormRequests with validation rules
    Resources/{feature}/...      # API Resources for response shape
  Services/{feature}/...         # business logic (orchestration, not data access)
  Repositories/{feature}/...     # data access (Mongo/SQL queries, projections, bulkWrite)
  Models/                        # jenssegers/mongodb collection models
config/
  jwt.php   cors.php   auth.php  # JWT config; CORS locked to FE; api guard = jwt
routes/
  api.php                        # Versioned: Route::prefix('v1')
```

## Auth flow

1. `POST /api/v1/auth/sign-in` (rate-limited) → returns `{ data: { access_token, expires_in, user } }`.
2. Send subsequent requests with `Authorization: Bearer <access_token>`.
3. On 401, FE axios interceptor calls `POST /api/v1/auth/refresh` once, then retries.
4. `POST /api/v1/auth/logout` invalidates the current token.

Passwords use bcrypt (`Employee_Password_Hash`). Legacy MD5 (`Employee_Password`)
is migrated transparently on first valid login. After ≥30 days, drop the MD5
fallback in `AuthService::login`.

## Indexes

Run `php artisan db:seed --class=IndexSeeder` once to create the Mongo indexes
needed by the new dashboards. Re-running is idempotent. Indexes are built in
the background.

## Per-feature recipe

For every legacy controller in marluapp:

1. Build `Repositories/<feature>/*Repository.php` with projections + indexes.
2. Build `Services/<feature>/*Service.php` containing rules and aggregates.
3. Add `Requests/<feature>/*Request.php` with `rules()`/`authorize()`.
4. Add `Resources/<feature>/*Resource.php` (or array shape) for outputs.
5. Add `Controllers/<feature>/*Controller.php` exposing thin actions delegating
   to the Service.
6. Register routes in `routes/api.php` under `Route::prefix('v1')->middleware('jwt')`.
   Add `role:Admin,Office` for restricted endpoints.

## Environment variables

See `.env.example`. Required for v1:

- `JWT_SECRET` (run `php artisan jwt:secret`)
- `CORS_ALLOWED_ORIGINS` (comma-separated FE origins)
- `DB_CONNECTION=mongodb`, `DB_DATABASE`, `DB_HOST`, `DB_PORT`
- `FRONTEND_URL` (used by CSV export to embed deep links)

## What is NOT yet migrated

The new controllers/services/repositories provide the contract; the actual
business rules are partially stubbed for features not yet ported. Each remaining
feature follows the 6-step recipe above. Track progress per feature here:

| Feature      | BE controller | BE service | BE repo | FE api/hooks | Parity QA |
| ------------ | ------------- | ---------- | ------- | ------------ | --------- |
| auth         | done          | done       | n/a     | done         | manual    |
| employees    | done          | done       | done    | done         | done      |
| sales        | done          | done       | done    | done         | partial   |
| wages        | partial       | n/a        | n/a     | (legacy)     | partial   |
| schedules    | done          | stub       | stub    | done         | -         |
| kpis         | done          | stub       | stub    | done         | -         |
| maps         | done          | stub       | n/a     | done         | -         |
| estimates    | done          | done       | done    | done         | -         |
| jobs         | done          | done       | n/a     | done         | -         |
| invoices     | done          | done       | n/a     | done         | -         |
| mentions     | done          | done       | n/a     | done         | -         |
| price-book   | done          | done       | n/a     | done         | -         |
| templates    | done          | n/a        | n/a     | done         | -         |
| others       | done          | done       | n/a     | done         | -         |
| admin        | done          | n/a        | n/a     | done         | -         |
