# Trendline Subscription Lifecycle Engine

Laravel 12 API for subscription plans, prices, payments, state changes, access checks, and a daily reconciliation command.

What’s in the box: REST API only (no auth, no UI), code split under `app/Modules`, idempotent payment writes, row lock on the subscription during payment handling, status history, `outbox_messages` rows in the same DB transaction as lifecycle updates, queued log lines for lifecycle events, seed data, and PHPUnit coverage.

---

## What This Solves

- Plans and per-plan prices (monthly/yearly, `AED` / `USD` / `EGP`)
- Trial length from the plan; states `trialing`, `active`, `past_due`, `canceled`
- 3-day grace after a failed payment while `active`
- Reconciliation cancels unpaid trials past `trial_ends_at` and `past_due` rows past `grace_period_ends_at`
- `GET .../access` answers yes/no with a short `reason` code

---

## Quick Review

```bash
composer install
cp .env.example .env   # macOS / Linux
copy .env.example .env # Windows
php artisan key:generate
php artisan migrate:fresh --seed
php artisan serve
```

Second terminal:

```bash
php artisan queue:work --tries=1
```

Optional:

```bash
php artisan schedule:work
```

1. Import `postman/SubscriptionLifecycleEngine.postman_collection.json`.
2. `GET /api/v1/plans`
3. `GET /api/v1/subscriptions/1` and `.../access` (seeded trialing row).
4. `POST /api/v1/payments/failure` for the seeded **active** subscription — that’s `id` **2** if you started from an empty DB and ran `migrate:fresh --seed`.
5. `GET /api/v1/subscriptions/2/access` — still allowed during grace.
6. `POST /api/v1/payments/success` — back to `active`.
7. `php artisan subscriptions:reconcile`
8. `php artisan test`

There is also subscription **3** (`pastdue@example.com`), already `past_due` with grace open. IDs 1–3 are only guaranteed in that fresh-seed order.

---

## Architecture Overview

**Layout** — `app/Modules`: Plans, Subscriptions, Payments, Lifecycle, Audit, Shared. One app, one database; the split is folders and namespaces, not separate deployables.

**Where state changes** — `SubscriptionLifecycleManager` performs the actual status updates and writes the outbox row, then schedules the Laravel event after commit (`DB::afterCommit`). Controllers stay thin; actions open transactions where needed.

**Events** — Examples: `SubscriptionStarted`, `SubscriptionActivated`, `SubscriptionPaymentFailed`, `SubscriptionMovedToPastDue`, `TrialExpired`, `SubscriptionCanceled`. History listener runs sync; `LogSubscriptionLifecycleEvent` implements `ShouldQueue` so logging does not block the request.

**Outbox** — Each transition inserts into `outbox_messages` before the after-commit dispatch. Nothing in this repo reads that table to push to a bus; it’s there for traceability and as a place to hang a publisher later if you add one.

---

## State Model

States: `trialing`, `active`, `past_due`, `canceled`.

**Trial** — Starts as `trialing`. If `trial_days > 0`, `trial_ends_at = starts_at + trial_days` and access needs `now < trial_ends_at`.

**Zero-day trial** — `trial_days = 0` still uses `trialing` with `trial_ends_at = starts_at`: no access until a successful payment; reconciliation can cancel if it stays unpaid.

**Activation** — Success payment → `active`. Next period end is `billing_cycle->addTo(max(current_period_ends_at, paid_at))` so you don’t shorten an already-paid window.

**Past due** — Failed payment from `active` → `past_due`, `grace_period_ends_at = failed_at + 3 days`. Access allowed until grace ends. More failures while `past_due` do not move grace.

**Recovery** — Success while in grace → `active`, grace cleared.

**Cancel** — Manual cancel or reconciliation. Payment endpoints do not bring a canceled row back.

---

## Access Policy

Allowed: valid trial, valid paid period, or valid grace. Denied: `canceled`, or expired trial / period / grace. Response includes `granted` and `reason`.

---

## Idempotency and Concurrency Safety

**Payments** — Same `idempotency_key` returns the same row; different payload for that key → `422`. Same `provider_reference` also collapses to the existing attempt. Subscription is `lockForUpdate()` for the insert path. Unique violations from races are caught and folded back to the existing row instead of a `500`.

**Reconciliation** — Chunked queries, per-row transaction with `lockForUpdate()`, lifecycle methods return early if state no longer matches; safe to run twice.

---

## Queue Usage

Only `LogSubscriptionLifecycleEvent` is queued. Lifecycle writes and history (sync listener) stay on the request/command thread.

---

## Data Model

Tables: `plans`, `plan_prices`, `subscriptions`, `payment_attempts`, `subscription_status_histories`, `outbox_messages`.

Notable: unique `plans.code`, unique `(plan_id, billing_cycle, currency)`, unique `idempotency_key`, unique nullable `provider_reference`, composite `(status, trial_ends_at)` and `(status, grace_period_ends_at)` on `subscriptions`. Amounts are integers (minor units).

---

## API Summary

Prefix `/api/v1`.

- Plans: `GET/POST /plans`, `GET/PATCH /plans/{plan}`
- Prices: `GET/POST /plans/{plan}/prices`, `PATCH /plans/{plan}/prices/{price}`
- Subscriptions: `GET/POST /subscriptions`, `GET /subscriptions/{subscription}`, `POST .../cancel`, `GET .../access`
- Payments: `GET .../payment-attempts`, `POST /payments/success`, `POST /payments/failure`

Creates → `201`. Validation / business rules → `422`.

---

## Reconciliation

```bash
php artisan subscriptions:reconcile
```

Also registered `daily()` in the scheduler. Output is JSON with counts of canceled trials and grace expirations.

---

## Setup

PHP 8.2+, Composer, MySQL or SQLite.

```bash
composer install
cp .env.example .env   # macOS / Linux
copy .env.example .env # Windows
php artisan key:generate
```

Set DB in `.env`, then `php artisan migrate` and `php artisan db:seed`, or `php artisan migrate:fresh --seed` for a clean run.

Run API + worker + optional scheduler + tests:

```bash
php artisan serve
php artisan queue:work --tries=1
php artisan schedule:work
php artisan test
```

---

## Demo Data

`SubscriptionEngineDemoSeeder`: users `reviewer@example.com` and `pastdue@example.com` (password `password`), plans `algo-pro` (7-day trial) and `algo-core` (no trial), sample prices, three subscriptions (trialing / active / past_due) and two payment attempts for the active and past-due cases.

---

## Postman

`postman/SubscriptionLifecycleEngine.postman_collection.json` — folders for plans, prices, subscriptions, payments, and a few end-to-end flows. Matches the seed above.

---

## Testing Coverage

```bash
php artisan test
```

Feature tests around plans/prices, starting subs (including zero-day trial), pay success/fail, grace boundaries, idempotency keys, cancel + pay, repeated fail while past_due, reconciliation idempotency, access resolver, and history/outbox side effects. Unit tests on `AccessResolver`.

---

## Assumptions

- Subscriptions are only created via `POST /subscriptions`.
- A user may have more than one subscription.
- Canceled subs are not reopened via payment APIs.
- Reconciliation does not invent failures; it only applies time-based rules.
- Cancel is immediate and logged.

---

## Tradeoffs

- No auth — reviewers can hit the API as-is.
- No PSP: success/failure bodies simulate the provider.
- Outbox is write-only here; no worker ships events out of process.
- Zero-day trials use `trialing` with an immediate `trial_ends_at` instead of a fifth state.

---

## Submission Notes

Built as a hiring-style backend exercise: most of the interesting logic sits in `SubscriptionLifecycleManager` and `RecordPaymentAttemptAction`, with tests aimed at the rules that usually break in production (grace edges, idempotency, reconcile twice, cancel interactions).
