# Transportation Sys - Implementation Plan

**Version**: 4.1
**Date**: 2026-11-09
**Status**: Approved — decisions resolved (v4.0 content preserved; restructured into the implementation-plan skeleton)
**Constitution**: 1.1.0

---

## Table of Contents

1. [Executive Summary](#executive-summary)
2. [Key Decisions](#key-decisions)
3. [Architecture Overview](#architecture-overview)
4. [Database Schema](#database-schema)
5. [Permissions & Settings Catalogue](#permissions--settings-catalogue)
6. [Booking & Quota Rules](#booking--quota-rules)
7. [API Endpoints](#api-endpoints)
8. [Booking Transaction Pipeline](#booking-transaction-pipeline)
9. [Payment Confirmation & Allocation Implementation](#payment-confirmation--allocation-implementation)
10. [Frontend Structure](#frontend-structure)
11. [Build Order](#build-order)
12. [Deployment](#deployment)
13. [Environment Variables](#environment-variables)
14. [Verification Checklist](#verification-checklist)
15. [Files to Create](#files-to-create)
16. [Appendix](#appendix)

---

## Executive Summary

Transportation Sys is a B2B transportation seat-booking ERP. Admin users publish trips with numbered seats. Users belonging to client companies browse trips and reserve specific seats within their company's seat allowance. Admins manage companies, trips, quotas, requests, operational records and finance. The MVP includes accounting and finance.

There are two user-facing portals. The **Admin portal** serves Super Admin and Admin users; Super Admin has complete access and creates roles/permissions for Admin users. The **Company portal** lets company users view trips, book seats, manage their own bookings and requests, and view their company's approved documents (invoices, receipts) and permitted account information. Expected initial scale: approximately **14 companies and up to 60 users**, with at least one booking per user per day.

### Core Product Rules

- **Tenancy**: Company-based — every company user, booking, payment, credit and document belongs to exactly one company; companies never see each other's data.
- **Accounts**: Admin-created only; no public sign-up; maximum **two active users per company**; accounts provisioned by **email invitation link** (set-password).
- **Seats**: Every seat is a numbered seat on one trip; a seat can never be double-booked (database + transaction enforce it, not just the UI).
- **Quota**: Counted in **seats** (not bookings); manual admin reset; one-time bonus seats approved via seat requests.
- **Pricing**: The **entered/effective price is the final amount the company pays**; company-specific trip overrides allowed (per-trip only).
- **VAT**: 14% tracked **internally only**; global on/off by Super Admin; **never shown or added to anything company-facing**.
- **Payments**: Offline cash/bank-transfer records only; pending until a permitted finance user confirms; a payment may allocate across multiple booking obligations; refunds (cash) and credits (company account) both supported.
- **Documents**: Per-booking invoices, monthly company invoices and payment receipts; **paid items only; no tax lines**; bilingual Arabic/English PDFs; visible to the company only after approval.
- **Financial integrity**: Confirmed transactions are immutable; corrections are linked adjustments; revenue basis is confirmed payments received.
- **Period control**: Monthly financial close; closed records locked; corrections via adjustments; **Super Admin may reopen a closed month (audited), must be re-closed after**.
- **Scope exclusions**: No fleet/drivers, no online payment gateway, no live ETA e-invoicing integration, no data import, no SMS/push (email only), no payment-evidence uploads.
- **Language**: Arabic and English, including full RTL support; no user-facing text hard-coded.

---

## Key Decisions

All decisions were resolved in the owner interview (2026-11-09) and are recorded with full IDs in the [Appendix — Constitution Alignment](#constitution-alignment). Changes require owner approval and an update here first.

| Decision | Choice | Rationale |
|----------|--------|-----------|
| Authentication architecture | Keep Supabase Auth (RD-01) | As originally planned; token/session verified by Laravel; P0-01 spike is a hard go/no-go gate |
| Frontend library | **Vue 3 + Inertia** (RD-02) | Simplicity and template clarity with Laravel+Inertia |
| Business timezone | **Africa/Cairo** (RD-03) | Cutoffs, trips, invoices and month boundaries in local time; timestamps stored UTC |
| VAT model | Prices final; VAT internal-only (RD-04) | Company never pays above entered price; 14% is an internal accounting metric with a global toggle; no exemptions |
| Quota counting unit | **Seats**, not bookings (RD-05) | Consistent with seat limit, bonus *seats*, "used seats" display |
| Cancellation & quota | Cancellation returns seat **and** quota usage (RD-06) | Matches free-both intuition within cutoff |
| Seat change | Supported within cutoff (RD-07) | Move booked seat to a free seat on same trip; snapshot preserved; audited |
| Booking cutoff | **Hours before departure** (RD-08) | Global default + optional per-trip override; server-enforced |
| Allocation semantics | Payments allocate to **booking obligations** only (RD-09) | Invoices derived from settled bookings; no double-counting; invoice targets = future extension |
| Per-booking invoice trigger | **Auto-draft on full settlement** (RD-10) | Stays draft until admin approval |
| Monthly invoice trigger | **Manual admin action** (RD-11) | No server cron dependency |
| Monthly invoice membership | **Payment-confirmation date** (RD-12) | Matches paid-items rule and revenue recognition |
| Partially paid booking | Produces a payment **receipt** for confirmed amount (RD-13) | Documents wait for full settlement |
| Prepare vs approve | Same person allowed (RD-14) | Separate permissions exist; fits small ops team |
| Partial-payment policy | Per company allow/disallow only (RD-15) | No minimums or milestones in MVP |
| Refund vs credit | Finance user chooses **per case** (RD-16) | Both always available |
| Cost basis | Non-cancelled seats at departure; recognized in **departure month** (RD-17) | Matches owner's worked example |
| Closed month | **Reopenable by Super Admin**, audited (RD-18) | Re-close required after corrections |
| Invoice numbering | `INV-YYYY-####` / `RCT-YYYY-####` per type per year (RD-19) | Gapless per issuer/document type |
| User provisioning | **Email invitation link** (RD-20) | Supabase Admin API invite; admins never set passwords |
| Payment evidence | Reference number + notes only (RD-21) | No uploads in MVP |
| Company pricing reuse | Per-trip override only (RD-22) | Reusable contracts excluded |

---

## Architecture Overview

```
┌──────────────────────────────────────────────────────────────────────┐
│              Browser UI (Inertia + Vue 3 — Arabic/English RTL)       │
├──────────────────────────────────────────────────────────────────────┤
│                      Admin Portal   |   Company Portal               │
│   (Super Admin roles/permissions, companies, trips, finance)         │
│   (trip browsing, numbered seat picker, bookings, documents)         │
└──────────────────────────────────┬───────────────────────────────────┘
                                   │
                                   ▼
┌──────────────────────────────────────────────────────────────────────┐
│                        Laravel Application                           │
│  - Supabase Auth adapter (token/session verification, invitations)   │
│  - Authorization (policies, gates, configurable permissions)         │
│  - Operational domain services                                       │
│  - Financial domain services / immutable ledger                      │
│  - Document service (invoices + receipts) / PDF renderer             │
│  - Reporting and export services                                     │
│  - Email notification service (outbox)                               │
│         Laravel is the ONLY application-level client of PostgreSQL   │
└───────────────┬──────────────────┬──────────────────┬────────────────┘
                │                  │                  │
                ▼                  ▼                  ▼
┌──────────────────────────────────────────────────────────────────────┐
│                              Supabase                                │
├───────────────┬────────────────────┬────────────────┬────────────────┤
│     Auth      │    PostgreSQL      │    Storage     │   (via env)    │
│  (identities, │  (RLS enabled,     │ (private PDF   │                │
│   invitations,│   deny-by-default, │  bucket: docs, │                │
│   password)   │   constraints)     │  non-guessable │                │
│               │                    │  paths)        │                │
└───────────────┴────────────────────┴────────────────┴────────────────┘
                                          │
                                          ▼
                     ┌────────────────────────────────────┐
                     │         SMTP / email provider      │
                     │  (Laravel Mail; outbox/retry only) │
                     └────────────────────────────────────┘

Future-only boundary: ETA e-invoicing adapter (not connected in MVP)
```

### Technology Stack

| Layer | Technology | Notes |
|-------|------------|-------|
| Application | Laravel monolith | Full-stack application; no separate Node backend |
| Page delivery | Inertia | Connects Laravel routes/controllers to Vue pages |
| Frontend | **Vue 3 + Inertia** (resolved) | Vite build; TypeScript recommended |
| Database | Supabase PostgreSQL | Laravel is the only application-level DB client |
| Authentication | Supabase Auth | Custom Laravel adapter; **P0-01 go/no-go gate** |
| File storage | Supabase Storage | Private bucket for approved document PDFs; no other files in MVP |
| Secrets | Server env variables; Supabase Vault optional | Never expose service-role or DB credentials to the browser |
| Email | Laravel Mail + SMTP provider | Email only; failures do not roll back business actions |
| Hosting | Pxxl free tier | 2 web-service projects, 0.5 GB RAM and 0.5 vCPU per project, 50 GiB egress, 300 build min, no database slot (DB stays on Supabase) |
| Dev workflow | opencode + coding models; Spec-Kit later | One bounded task per agent session |

---

## Database Schema

### Schema Principles

- Every schema change is a **migration**; nobody edits the database by hand. A fresh environment builds from migrations alone.
- **Row-level security (RLS) enabled on every application table, deny-by-default; no public/anonymous policies.** Laravel connects with a privileged server-side role; Laravel policies/gates remain the primary authorization layer; RLS is the defense-in-depth safety net so the automatic Supabase API cannot bypass Laravel.
- **Rules live in the database where possible**: primary/foreign keys, unique constraints (trip + seat number, template + departure), check constraints on statuses and counts.
- The **Laravel application owns business logic** — quota checks, price resolution, payment allocation, refunds, document approval, cutoffs, permissions — enforced in transaction-safe services; DB constraints are the final safety net.
- Money is stored with fixed-precision `NUMERIC` types (default `NUMERIC(12,2)`; document exceptions), **never floating point**, with explicit currency fields (EGP in MVP).
- Timestamps stored **UTC**; the configured business timezone (Africa/Cairo) applies to cutoffs, trip display, invoice dates and month boundaries.
- Usage is **calculated, never drifted**: a company's used seats derive from its non-cancelled bookings since the last quota reset, never kept as a mutable counter.
- **Snapshot on write**: applied seat price, internal VAT enabled state/rate and calculated amounts snapshot on each booking/financial document so later price or tax-setting changes never rewrite history.
- Bookings, requests and financial records are **never hard-deleted**; they change status. The audit log is append-only. No hard deletion of decided seat requests or booking history.

### Core Conventions (types and enums)

| Convention | Values |
|------------|--------|
| Booking status | active / cancelled (within cutoff; releases seat + quota) — plus settlement overlay: unsettled / partially_settled / fully_settled |
| Payment state | `pending` → `confirmed` (optional `rejected`); immutable after confirmation |
| Document type | `booking_invoice`, `monthly_invoice`, `payment_receipt` |
| Document state | `draft` → `approved` (+ `voided`); approval snapshots contents |
| Monthly closing status | `open` → `closed` → `reopened` → `closed` (re-close required) |
| Company user state | active / inactive; max two active per company |
| Trip status | includes published / cancelled with affected-booking handling |
| Seat request | pending → approved / rejected (decision final, immutable) |
| Refund/credit kind | cash refund / company account credit (per-case choice) |
| Currency | EGP (explicit field on every money record) |
| Time authority | UTC storage; business timezone Africa/Cairo (configurable setting) |
| Decimal precision | NUMERIC default (12,2) for amounts; explicit currency column |
| Internal VAT | global enabled flag + 14% rate — **internal reporting metadata only; never rendered company-side** |

### Tables

#### 1. Identity, roles and permissions (RBAC)

| Table | Purpose / important data |
|---|---|
| `admin_users` | Local admin profile linked to Supabase Auth identity; name, email, active state |
| `admin_roles` | Custom admin roles created by Super Admin; protected system Super Admin role (flag/identifier) |
| `admin_permissions` | Stable permission catalogue (see [Permissions & Settings Catalogue](#permissions--settings-catalogue)) |
| `admin_role_permissions` | Role ↔ permission mapping with grant metadata |
| `admin_user_roles` | Roles assigned to admins, with assigning actor + timestamp |

**Integrity requirements**

- Unique normalized Supabase-auth-identity → local-profile mappings.
- Super Admin protection must not depend on a display label: protected system-role flag; ordinary Admin operations cannot remove/demote it.
- Role/permission assignments record who granted them and when.

#### 2. Companies and company users (tenancy)

| Table | Purpose / important data |
|---|---|
| `companies` | Company identity, legal/tax details (invoice-required), contacts, **seat limit (seats)**, partial-payment policy (allow/disallow), `quota_reset_at`, active state |
| `company_users` | Local company-user profile linked to Supabase Auth identity; company ownership, language, active state; provisioned via invitation link |

**Integrity requirements**

- Max **two active** company users per company: enforced in a transaction-safe service; plus a database-labeled constraint/trigger strategy where practical.
- Company seats are integer units; partial-payment policy is a constrained enum.

#### 3. Trips, templates, seats and prices (operational)

| Table | Purpose / important data |
|---|---|
| `trip_templates` | Recurrence: route, departure time, weekdays, seat count, default price/cost, validity range |
| `trips` | Individual departure: route, origin, destination, departure time, seat count, standard seat price, per-seat cost, currency, status, **cutoff hours override**, optional template link; unique `(template_id, departure_start)` for idempotent recurrence |
| `company_trip_prices` | Company-specific negotiated price for a trip (the final company price), effective state, audit metadata |
| `trip_seats` | One row per numbered seat; unique `(trip_id, seat_number)`; occupancy state or booking relation |
| `bookings` | Header: company, trip, user, status, seat count, **total = unit price × seats**, currency, **internal VAT snapshot (enabled flag + rate)** |
| `booking_seats` | Seat assignment rows; a seat cannot appear in more than one active booking |
| `seat_requests` | Company request: requested seats, reason, status, reviewer, decision note; decided requests immutable |

**Integrity requirements**

- Unique seat number per trip; seat claim protected by transaction + conditional update/lock.
- Recurring generation idempotency via unique template/departure key.
- Booking snapshots: effective unit price, internal VAT enabled state/rate, total — internal VAT fields are reporting metadata; no VAT math is visible to companies.
- Seat **changes** keep the same booking id/price snapshot; assignment history recorded in the audit log.

#### 4. Financial core (payments, allocations, ledger, credits)

| Table | Purpose / important data |
|---|---|
| `payment_records` | Manual payment: company, amount, currency, received date, method (cash/bank), **reference number**, notes, recorded by, state (`pending` → `confirmed`; `rejected` optional), confirmer |
| `payment_allocations` | Confirmed payment → settlement target rows; `target_type` + `target_id` (MVP: `booking_obligation`; invoice targets = future extension), amount, allocated at/by; splitting across bookings; no double-counting with document lines |
| `financial_transactions` | Immutable ledger: booking charge (amount due), confirmed payment, cash refund, credit issued/applied, adjustment/reversal; source + linked-transaction references |
| `company_credits` | Credit balance maintained through immutable credit movements; historical events never overwritten |

**Financial integrity requirements**

- `NUMERIC` amounts + explicit currency on every money record.
- A confirmed payment, refund, credit or adjustment is **immutable**; corrections are new linked transactions.
- Allocation invariant: `allocations + refunds ≤ confirmed amount` of that payment (SQL checks + service-level enforcement).
- A booking cannot be settled beyond its remaining amount due; excess received money stays unallocated (available for later allocation, subject to refund/credit actions).
- Refunds cannot exceed the refundable balance; credit application cannot exceed available credit.
- Cross-company isolation at schema/policy and service level.

#### 5. Documents (invoices, receipts, periods)

| Table | Purpose / important data |
|---|---|
| `documents` | Type (`booking_invoice`, `monthly_invoice`, `payment_receipt`), company, scope (booking id and/or period start/end), `INV-YYYY-####`/`RCT-YYYY-####` numbers, totals snapshot, state (`draft`/`approved`/`voided`), prepare/approve metadata, private PDF path |
| `document_lines` | Paid line items with price snapshots (seats, trips, unit price, line total); **no VAT columns rendered customer-side**; internal metadata retained for reporting |
| `monthly_closings` | Period, status (`open`, `closed`, **`reopened`**), reopen metadata: actor, reason, timestamp; re-close event |

**Integrity requirements**

- All documents contain **paid items only**; the covering logic guarantees a paid amount never appears on both an invoice and a receipt for the same obligation.
- Document totals are snapshots; later company/trip/settings changes never rewrite approved documents.
- While a period is `closed`, records in that period reject direct mutation; adjustments post in an open (or reopened) period linked to the original; **reopen is permission-gated, requires reason, is audited, and the period must be re-closed afterwards.**
- Generated document PDFs never live in a public bucket.

#### 6. CRM, configuration, audit and supporting

| Table | Purpose / important data |
|---|---|
| `company_contacts` | Named contacts and channels |
| `crm_notes` | Company notes with author + timestamp |
| `crm_activities` | Activity log entries per company (interaction kind, actor, timestamp) |
| `settings` | Global booking-cutoff hours, **internal VAT enabled flag + rate (14%)**, currency, business timezone, **invoice issuer profile** (legal name, tax number, address, contact, logo/stamp, numbering prefixes) |
| `audit_logs` | Actor, action, subject, before/after data, context/correlation id, timestamp; **append-only** |
| `outbox_events` | Reliable email/document delivery events for retry/inspection without coupling SMTP success to business commits |

Settings changes affect **future** transactions only via immutable booking/document snapshots.

### Row Level Security (RLS)

- **RLS enabled on every application table; deny-by-default; no public/anonymous policies.** The browser never accesses the database directly — Laravel is the only application-level client.
- Laravel connects with a least-privilege server-side role using a pooled connection (mode validated against real transaction behavior in P0-01); elevated credentials stay server-only.
- Verify RLS behavior with the actual database role Laravel uses (pooler included) — see [Verification Checklist](#verification-checklist).
- Cross-company data isolation is additionally guarded by Laravel policies/gates; Inertia page visibility is convenience only, never authorization.

### Indexing and Data Access

- Index foreign keys and common filters: company, trip, departure date, booking status, payment confirmation date/state, document company/date/state, transaction company/date/type, audit actor/date.
- Least-privilege DB access; keep elevated credentials server-side.
- Review query plans for company booking history, trip occupancy, finance transaction lists and monthly reports.
- Paginate lists; chunk exports; never load complete tables into memory.

### Storage

- **Bucket**: private `documents` bucket (public buckets forbidden for this purpose)
- **Paths**: non-guessable object paths per document (e.g. `docs/{companyId}/{documentId}/{uuid}.pdf`)
- Approved document PDFs only; no other files in MVP; downloads require authorization (signed paths / Laravel-authorized streaming).

### Database seed and verification

Repeatable seeders for: protected Super Admin role + initial permission catalogue; global settings (EGP, Africa/Cairo, cutoff default, internal VAT 14% + on/off flag, issuer profile placeholders); test companies/users (local/test only); example one-off/recurring trips, seats, price overrides; test-only payment/document data for automated tests. **Never seed real credentials or real company data in VCS.** See [Files to Create](#files-to-create) and [Build Order](#build-order).

---

## Permissions & Settings Catalogue

### Permission Catalogue (stable, created and assigned by Super Admin)

| Permission | Governs |
|------------|---------|
| `manage companies` | Company create/edit/deactivate, legal details |
| `manage company users` | Create/deactivate company users, invitations (2-active-user limit enforced) |
| `configure quotas` | Seat limit + partial-payment policy configuration |
| `reset quotas` | Manual quota reset (atomic, audited) |
| `manage trips` | Trip create/edit/cancel, seat rows |
| `manage templates` | Recurring templates, generate-trips action |
| `manage trip prices` | Company-specific price overrides per trip |
| `manage trip costs` | Per-seat cost entry/update per trip |
| `review requests` | Seat-request approve/reject decisions |
| `view crm` | Contacts, notes, activity history |
| `view audit` | Audit log viewer |
| `record payments` | Payment entry (pending state) |
| `confirm payments` | Pending → confirmed transition |
| `allocate payments` | Allocation across booking obligations |
| `issue refunds credits` | Cash refunds and company credits issuance |
| `prepare documents` | Invoice/receipt preparation (draft) |
| `approve documents` | Approval (same person allowed to hold both permissions) |
| `view financial reports` | Finance reporting screens |
| `export reports` | Excel/PDF/CSV exports |
| `close periods` | Monthly close execution |
| `reopen periods` | Closed-month reopen (essentially Super Admin; audited + reason) |

- Super Admin has full financial visibility and always retains full access; Super Admin is a **protected system role** (identifier/flag, not a display label).
- Permission bundles are **configurable** — no hard-coded Accountant role.
- Every permission and role change is permission-checked and audited (who granted, when).

### Global Settings (settings table — not environment variables)

| Setting | Value policy |
|---------|--------------|
| Booking cutoff default | Hours before departure (per-trip override allowed) |
| Internal VAT enabled flag | On/off by Super Admin (14%) |
| Internal VAT rate | 14% (internal reporting only; never company-facing) |
| Currency | EGP |
| Business timezone | Africa/Cairo |
| Invoice issuer profile | Legal name, tax number, address, contact details, logo/stamp (data entry later, per RD-19 note) |
| Numbering prefixes | `INV-YYYY-####`, `RCT-YYYY-####` — gapless per document type per calendar year |

Settings changes affect **future** transactions only (immutable booking/document snapshots preserve history).

---

## Booking & Quota Rules

### Quota Model

| Rule | Definition |
|------|------------|
| Counting unit | **Seats** — each reserved seat consumes one allowance unit (a 3-seat booking consumes 3) |
| Usage calculation | Seats in **non-cancelled bookings created after the latest `quota_reset_at`** — always computed; never a drift-prone counter |
| Effective allowance | Company base seat limit + approved one-time bonus seats |
| Manual reset | Resets the usage window; **clears granted unused bonus seats**; keeps active bookings; atomic, permission-checked, audited |
| Display | Base limit, bonus seats, used seats and remaining seats visible to the company and authorised admins |

### Booking Cutoff

| Rule | Definition |
|------|------------|
| Mechanism | **Cutoff = N hours before departure**; global default in settings + optional per-trip override |
| Enforcement | Server-side only, in the business timezone (Africa/Cairo); no bookings, seat changes or cancellations after cutoff |
| Cancellation | Within cutoff: releases physical seat + consumed quota usage; if the amount was already paid, triggers the refund/credit decision path |
| Seat change | Within cutoff: move a booked seat to a free seat on the same trip; same booking id and price snapshot preserved; every swap audited |

### Booking Lifecycle and Settlement States

```
                (within cutoff)                 (after cutoff)
   no booking ───────────────► booked ────────────────────────► changes/cancels
                                 │  ▲                             are REFUSED
                                 │  └──── seat change (same id,
                                 │        price snapshot kept)
                                 ▼
                    ┌─────────────────────────────┐
                    │ settlement overlay          │
                    │ unsettled ─► partially_settled ─► fully_settled
                    │                            │
                    │   fully_settled fires the   │
                    │   settled-event EXACTLY ONCE │
                    │   → booking invoice auto-draft│
                    └─────────────────────────────┘
booked ──(cancel within cutoff)──► cancelled (seat + quota released;
                                    if paid → refund/credit decision)
```

- Failed booking leaves **no partial seat assignment or financial charge** (all-atomic transaction).
- Concurrent requests for the same seat: exactly one succeeds (transaction + conditional update + unique constraint).
- Concurrent requests cannot exceed quota.
- Financial history is preserved for paid or partially paid bookings.
- Seat requests: company submits seat count + reason; permitted admin approves/rejects with optional note; **approval grants one-time bonus seats, atomic with the state change**; decision final, immutable, audited; company receives email.

### Pricing Resolution

| Rule | Definition |
|------|------------|
| Standard price | Per trip (EGP) |
| Company override | Optional negotiated price for a particular company on a particular trip (per-trip only) |
| Effective price | Resolved **on the server at booking time**; it is the **final company-facing amount** |
| Uniformity | All seats on the same trip share one effective price for a given company |
| Snapshot | Booking stores effective unit price, total, currency, and internal VAT state/rate |
| Immutability | Later price or VAT-setting changes never rewrite existing bookings |

---

## API Endpoints

> These route/page contracts are derived from the workflows defined in this plan and are the source for the P0-07 shared contract finalization (route naming, page structure, error format). Laravel `web`/Inertia routes + named actions; every route is permission-checked server-side.

### Authentication

All endpoints (except `/health` and sign-in pages) require an authenticated session mapped through the Supabase Auth adapter to `admin_users` or `company_users`. Inactive or unknown identities are rejected.

```
Authorization handled by Laravel session middleware (Supabase Auth adapter verifies token/session)
```

### Error Response Format (convention to finalize in P0-07)

```json
{
  "error": {
    "code": "ERROR_CODE",
    "message": "Human-readable, localized (Arabic/English) message",
    "request_id": "uuid"
  }
}
```

### Public / Auth

| Method | Path | Description | Permission |
|--------|------|-------------|------------|
| GET | `/health` | Health check (no auth) | public |
| GET | `/login` | Sign-in page (language-aware) | public |
| POST | `/login` | Sign-in via Supabase Auth adapter | public |
| POST | `/logout` | Sign-out / session revocation | any authenticated |
| GET | `/invite/{token}` | Set-password screen (email invitation; RD-20) | one-time token |
| POST | `/invite/{token}` | Complete invitation, set password | one-time token |

### Admin — Companies, Users, Quotas

| Method | Path | Description | Permission |
|--------|------|-------------|------------|
| GET | `/admin/companies` | Company list, search/filter | manage companies |
| POST | `/admin/companies` | Create company (legal/tax details, seat limit, partial-payment policy) | manage companies |
| GET | `/admin/companies/{id}` | Company detail: legal info, contacts, users, quota meter | manage companies |
| PATCH | `/admin/companies/{id}` | Edit company | manage companies |
| POST | `/admin/companies/{id}` | Deactivate/reactivate company | manage companies |
| POST | `/admin/companies/{id}/users` | Create company user + send email invitation (2-user limit enforced) | manage company users |
| POST | `/admin/company-users/{id}/state` | Activate/deactivate company user | manage company users |
| POST | `/admin/companies/{id}/quota-reset` | Manual quota reset (clears window + unused bonus seats) | reset quotas |
| PATCH | `/admin/companies/{id}/limits` | Seat limit / partial-payment policy | configure quotas |

### Admin — Trips, Templates, Pricing, Costs

| Method | Path | Description | Permission |
|--------|------|-------------|------------|
| GET | `/admin/trips` | Trip list with date/status filters | manage trips |
| POST | `/admin/trips` | Create one-off trip (creates numbered seat rows) | manage trips |
| GET | `/admin/trips/{id}` | Trip detail: numbered seats, occupancy, affected bookings | manage trips |
| PATCH | `/admin/trips/{id}` | Edit trip (price/cost/cutoff override) | manage trips |
| POST | `/admin/trips/{id}/cancel` | Cancel trip + affected-bookings summary | manage trips |
| GET/POST | `/admin/trip-templates` | Recurring template list / create | manage templates |
| PATCH | `/admin/trip-templates/{id}` | Edit template | manage templates |
| POST | `/admin/trip-templates/{id}/generate` | Generate next-14-days trips (idempotent) | manage templates |
| POST | `/admin/trips/{id}/company-price` | Set company-specific price override | manage trip prices |
| POST | `/admin/trips/{id}/cost` | Set per-seat cost | manage trip costs |

### Admin — Seat Requests, CRM, RBAC, Settings, Audit

| Method | Path | Description | Permission |
|--------|------|-------------|------------|
| GET | `/admin/seat-requests` | Request queue with status filters | review requests |
| POST | `/admin/seat-requests/{id}/decide` | Approve/reject + optional note (one-time bonus grant on approve) | review requests |
| GET/POST | `/admin/companies/{id}/contacts` | Company contacts CRUD | view crm |
| GET/POST | `/admin/companies/{id}/notes` | CRM notes | view crm |
| GET | `/admin/companies/{id}/activities` | Activity history | view crm |
| GET/POST | `/admin/roles` | Roles list / create role | Super Admin |
| POST | `/admin/roles/{id}/permissions` | Assign/revoke permissions | Super Admin |
| POST | `/admin/roles/{id}/users` | Assign/revoke admin user role | Super Admin |
| POST | `/admin/admin-users` | Create admin user | Super Admin |
| GET/PUT | `/admin/settings` | Global settings incl. issuer profile (RD-13/RD-19) | Super Admin |
| GET | `/admin/audit` | Audit log viewer with user/action/date filters | view audit |

### Company — Trips, Booking, Seat Requests

| Method | Path | Description | Permission |
|--------|------|-------------|------------|
| GET | `/company` | Home: upcoming trips + current quota summary | company user |
| GET | `/company/trips` | Trip browsing with route/departure filters | company user |
| GET | `/company/trips/{id}` | Trip detail + numbered seat map (available/taken/own states) | company user |
| POST | `/company/trips/{id}/bookings` | Book specific seats (transactional; quota/cutoff enforced) | company user |
| GET | `/company/bookings` | Booking history | company user |
| GET | `/company/bookings/{id}` | Booking detail + settlement status | company user |
| POST | `/company/bookings/{id}/cancel` | Cancel within cutoff (releases seat + quota; refund path if paid) | company user |
| POST | `/company/bookings/{id}/change-seat` | Move booked seat to a free seat on same trip (within cutoff) | company user |
| POST | `/company/seat-requests` | Submit extra-seat request + reason | company user |
| GET | `/company/seat-requests` | Own request history/status | company user |

### Company — Documents and Account

| Method | Path | Description | Permission |
|--------|------|-------------|------------|
| GET | `/company/documents` | Approved documents list (invoices/receipts; own company only) | company user |
| GET | `/company/documents/{id}/download` | Authorized PDF download/stream (post-approval only) | company user |
| GET | `/company/payments` | Own company payment history + allocation breakdown | company user |
| GET | `/company/credits` | Credit balance and movement history | company user |

### Finance (Admin)

| Method | Path | Description | Permission |
|--------|------|-------------|------------|
| GET | `/admin/payments` | Payment list/filter (own permission scope) | record payments |
| POST | `/admin/payments` | Record cash/bank payment (pending state; RD-21 fields) | record payments |
| POST | `/admin/payments/{id}/confirm` | Confirm receipt → triggers auto-drafts (invoice/receipt covering) | confirm payments |
| POST | `/admin/payments/{id}/allocations` | Allocate confirmed amount across booking obligations (split) | allocate payments |
| POST | `/admin/credits` | Issue company account credit | issue refunds credits |
| POST | `/admin/refunds` | Cash refund (linked new ledger transaction) | issue refunds credits |
| GET | `/admin/documents` | Document list (type/company/period/status/number filters) | prepare documents |
| GET | `/admin/documents/{id}` | Document detail/preview (pre-approval) | prepare documents |
| POST | `/admin/documents/{id}/approve` | Approve document (immutable snapshot at approval) | approve documents |
| POST | `/admin/documents/{id}/void` | Void draft/approved document per workflow | approve documents |
| POST | `/admin/documents/monthly` | Generate monthly company invoice (manual trigger; company + period; RD-11/RD-12) | prepare documents |
| POST | `/admin/documents/{id}/resend` | Email approved document (retryable) | approve documents |

### Finance Reports & Period Control

| Method | Path | Description | Permission |
|--------|------|-------------|------------|
| GET | `/admin/reports/finance` | The agreed metrics only: confirmed revenue, revenue by company, transactions, net profit, prev-month comparison, YTD (view financial reports) | view financial reports |
| GET | `/admin/reports/operational` | Bookings by company, trip occupancy, quota use, seat-request status | operational scope |
| POST | `/admin/reports/export` | Excel/PDF/CSV export (permission-scoped, chunked) | export reports |
| POST | `/admin/periods/{yyyy-mm}/close` | Monthly close (validate → lock → closing record + audit) | close periods |
| POST | `/admin/periods/{yyyy-mm}/reopen` | Reopen closed month (reason mandatory, audited, re-close required) | reopen periods |
| POST | `/admin/adjustments` | Post linked adjustment/correction transaction in an open period | issue refunds credits |

---

## Booking Transaction Pipeline

Annotated outline for the booking flow — the authoritative business steps that BE-11 (plus BE-10 pricing and BE-07 quota) implement atomically:

```
async def book_seats(trip_id, seat_numbers[], acting_company_user):
    """
    Book specific numbered seats for the acting user's company.

    Steps (single DB transaction unless noted):
    1. Verify identity + company (Supabase Auth adapter → local
       company_users map); reject unknown/inactive identities.
    2. Load trip; validate status (published) and seat count fits trip.
    3. Compute cutoff: trip departure − cutoff_hours (global default or
       per-trip override), evaluated in business timezone (Africa/Cairo);
       refuse if now ≥ cutoff.
    4. Verify chosen seats exist on the trip AND are not components of
       another active booking (unique (trip_id, seat_number) + booking_seats).
    5. Compute company quota usage: seats in non-cancelled bookings
       created after quota_reset_at.
    6. Verify requested seat count ≤ remaining allowance
       (base limit + active one-time bonus seats − used seats).
    7. Resolve effective price: company_trip_prices override if present,
       else standard trip price — server-side resolution.
    8. Compute total = effective_unit_price × seats (NUMERIC);
       snapshot unit price, total, currency + internal VAT state/rate.
    9. Claim seats with conditional update under the transaction/row lock
       (two concurrent requests for the same seat → exactly one wins).
    10. Create booking (status active, settlement = unsettled) +
        booking_seats rows linking the claimed seats.
    11. Insert immutable booking-charge financial transaction
        (amount due = total) referencing the snapshot.
    12. Write audit entry (who/what/when + snapshot) and enqueue
        confirmation email via outbox (failure never rolls back).
    13. Commit. Any failure anywhere → full rollback: no seat claims,
        no charge, no partial state.
    """
```

- Concurrent same-seat requests: exactly one successful booking (step 9 guarantee).
- Quota cannot be exceeded through concurrent requests (steps 4–6 under the transaction).
- Cutoff is enforced server-side using the configured business timezone (step 3).
- Post-commit side effects (emails via outbox) are fire-and-forget and recoverable.

---

## Payment Confirmation & Allocation Implementation

Annotated outline for the financial core — BE-14/BE-15/BE-16/BE-18 behavior with the non-negotiable invariants:

```
async def confirm_payment(payment_record, confirming_admin):
    """
    1. Permission check: confirm payments.
    2. Validate state: pending → confirmed only (never confirmed → pending);
       immutable after confirmation.
    3. Set confirmed with confirmer + timestamp; audited.
    4. Post confirmed-payment ledger transaction (revenue basis).
    5. Receipt/invoice drafffting (covering rules below) via BE-18.
    6. Payment-confirmed email via outbox to company (own language).
    """

async def allocate(payment_record, targets[booking_obligation, amount][]):
    """
    Invariants (SQL checks + service checks under a transaction):
    a. Payment must be CONFIRMED; pending payments are never allocatable.
    b. Sum(allocations) + Sum(refunds) ≤ confirmed amount of the payment.
    c. For each booking target: amount ≤ remaining amount due on that
       booking; a booking may not be settled beyond its due.
    d. Excess received money stays unallocated (no forced over-allocation);
       it remains available for later allocation or refund/credit actions.
    e. A booking may not be treated as fully paid until its due amount is
       settled; the fully-settled event fires EXACTLY ONCE and auto-drafts
       the booking invoice (RD-10, BE-18).
    f. Per company partial-payment policy (allow/disallow) governs partial
       allocation acceptance (RD-15).
    """
```

### Document Covering Rules (no double presentation)

- Payment allocations target **booking obligations only** in MVP (RD-09): invoices are *derived* from settled bookings; invoice-level allocation targets exist only as a future extension; monthly documents group paid items by **payment-confirmation date** (RD-12).
- A booking's **newly fully-settled amount** appears on its auto-drafted booking invoice.
- **Remaining partial-settlement allocations** of the same payment appear on the payment's **receipt** (drafted on confirmation — RD-13).
- Consequently a paid amount never appears on both an invoice and a receipt for the same obligation — enforced by the covering logic in BE-18 and checked by DB-09 constraints.

### Refunds, Credits, Corrections

- Refund/credit issuance: finance user chooses **cash refund vs company credit per case** (RD-16); both always available; totals bounded by refundable/creditable balances; credit application against future obligations cannot exceed available credit.
- Confirmed payments, refunds, credits and adjustments are **immutable** — no controller, job or admin screen may edit or delete them; corrections are **new linked adjustment transactions** posted in an **open** (or reopened) period (RD-18).
- Every transition and financial posting is permission-checked and audited.

---

## Frontend Structure

```
resources/js/
├── app.js                          # Inertia + Vue bootstrap
├── Pages/
│   ├── Auth/
│   │   ├── login.vue               # Sign-in (language-aware)
│   │   ├── invite.vue              # Set-password (invitation flow, RD-20)
│   │   └── session-expired.vue
│   ├── Admin/
│   │   ├── Dashboard.vue           # Landing (scoped by permissions)
│   │   ├── Companies/
│   │   │   ├── index.vue           # List + search/filter
│   │   │   ├── show.vue            # Legal info, contacts, users, quota meter
│   │   │   └── form.vue            # Create/edit (limits, partial-payment policy)
│   │   ├── CompanyUsers/
│   │   │   └── form.vue            # Create/deactivate + 2-user-limit feedback
│   │   ├── Quota/
│   │   │   └── reset.vue           # Manual reset confirmation (unlikely warnings)
│   │   ├── Trips/
│   │   │   ├── index.vue           # List with date/status filters
│   │   │   ├── form.vue            # Create/edit (route, departure, seats, price,
│   │   │   │                       #  per-seat cost, cutoff override)
│   │   │   └── show.vue            # Numbered seats, occupancy, affected bookings
│   │   ├── Templates/
│   │   │   ├── index.vue           # Recurring template management
│   │   │   └── generate.vue        # Generate-trips action
│   │   ├── Pricing/
│   │   │   └── override.vue        # Company price override per trip
│   │   ├── SeatRequests/
│   │   │   ├── index.vue           # Queue with status filters
│   │   │   └── review.vue          # Approve/reject dialog + note
│   │   ├── Crm/
│   │   │   └── company.vue         # Contacts, notes, activity tabs
│   │   ├── Roles/
│   │   │   └── index.vue           # Roles/permissions/assignments (Super Admin)
│   │   ├── Settings/
│   │   │   └── index.vue           # Global settings incl. issuer profile
│   │   ├── Payments/
│   │   │   ├── index.vue           # List + pending queue
│   │   │   ├── entry.vue           # Record payment (RD-21 fields)
│   │   │   └── allocate.vue        # Allocation screen (booking targets)
│   │   ├── Refunds/
│   │   │   └── form.vue            # Refund/credit dialog (per-case choice)
│   │   ├── Documents/
│   │   │   ├── index.vue           # List (type/company/period/status/number)
│   │   │   ├── show.vue            # Detail/preview, prepare/approve actions
│   │   │   └── monthly-generate.vue# Monthly invoice generator (manual trigger)
│   │   ├── Reports/
│   │   │   ├── finance.vue         # Agreed metrics only + filters + exports
│   │   │   ├── operational.vue     # Bookings by company, occupancy, quota, requests
│   │   │   └── audit.vue           # Audit log viewer
│   │   └── Period/
│   │       └── close.vue           # Monthly close page + reopen + adjustment history
│   └── Company/
│       ├── Home.vue                # Upcoming trips + quota summary
│       ├── Trips/
│       │   ├── index.vue           # Browsing with route/departure filters
│       │   └── show.vue            # Seat picker + booking confirmation
│       ├── Bookings/
│       │   ├── index.vue           # History
│       │   ├── show.vue            # Detail, cancel, change-seat + cutoff messaging
│       │   └── confirm.vue         # Confirmation summary (effective price, totals)
│       ├── SeatRequests/
│       │   └── index.vue           # Request form + history/status
│       ├── Documents/
│       │   └── index.vue           # Approved documents + authorized PDF view
│       └── Account/
│           ├── payments.vue        # Own payment + allocation breakdown
│           └── credits.vue         # Credit balance + movements
├── Components/
│   ├── ui/                         # Design tokens: buttons, forms, dialogs, tables,
│   │                               #  status labels, empty/loading/error states
│   ├── booking/
│   │   ├── seat-picker.vue         # Numbered seats: available/taken/selected/own
│   │   └── booking-summary.vue     # Effective price + totals (no VAT lines)
│   ├── quota/
│   │   └── quota-meter.vue         # Base + bonus + used/remaining (seat-counted)
│   ├── finance/
│   │   ├── payment-card.vue
│   │   └── allocation-breakdown.vue
│   ├── documents/
│   │   ├── document-list-row.vue
│   │   └── document-status-badge.vue
│   ├── company/
│   │   ├── company-card.vue
│   │   └── delete-confirmations.vue
│   └── shared/
│       ├── filters.vue
│       ├── date-picker.vue
│       └── amount-display.vue      # EGP formatting; never tax lines
├── Layouts/
│   ├── admin-layout.vue            # Admin shell + permission-aware nav
│   ├── company-layout.vue          # Company shell + navigation
│   └── auth-layout.vue
├── Composables/
│   ├── useAuth.js                  # Session identity + role awareness
│   ├── useFormatters.js            # Dates (Africa/Cairo), currency EGP
│   ├── usePermission.js            # UI action visibility (never authorization)
│   └── useLocale.js                # Arabic/English switch + persistent preference
├── i18n/
│   ├── en.js
│   └── ar.js
└── types/index.js                  # Typed page props (TypeScript recommended)
```

### Key UI Components

- **Seat Picker** — numbered seats with free/taken/own/selected states; works on a phone and with a keyboard (constitution V.3); booking cutoff messaging visible.
- **Quota Meter** — base limit + bonus + used/remaining (seat-counted); reset confirmation warns it clears the usage window and unused bonus seats.
- **Booking Confirmation Summary** — effective seat price × seats = total; **no VAT lines anywhere** (they do not exist client-side); clear settled/partial/payment states.
- **Allocation Screen** — confirmed payment split across eligible booking obligations with remaining-due feedback and invariant errors (can't exceed confirmed available; can't overpay a booking).
- **Document Approval Flow** — preview before approval; prepare/approve action visibility per permission; monthly generator (company + period); approved-only company visibility.
- **Reports** — exactly the agreed finance metrics with company/date filters and exports; no additional dashboard metric cards without owner approval.
- Every screen ships **loading, empty, success and error states**; forms preserve input after recoverable errors; amounts in EGP; pending payments never render as confirmed.

---

## Build Order

Phases combine DB + backend + frontend slices; original plan task IDs are preserved for traceability (register: P0-* / DB-* / BE-* / FE-* / REL-* from the v4.0 task register).

### Phase 0: Decisions, Repository and Technical Validation

| Task | Description | ID |
|------|-------------|-----|
| 0.1 | Validate Supabase Auth + Laravel/Inertia **go/no-go** (invitation flow, session, logout, inactive handling) — hard gate before account flows | P0-01 |
| 0.2 | Repository structure, branching/commit conventions, env template, secret handling | P0-02 |
| 0.3 | Minimal Laravel + Inertia (Vue) app; health-check deployed to Pxxl; memory/CPU measured | P0-03 |
| 0.4 | Supabase PostgreSQL pooler + transaction rollback/locks/unique constraints proof | P0-04 |
| 0.5 | Scheduling/queue options on Pxxl; manual/synchronous fallback decision (monthly invoices are manual anyway) | P0-05 |
| 0.6 | Bilingual RTL PDF generation + private storage/download proof under 0.5 GB RAM | P0-06 |
| 0.7 | Route/page contracts, error format, permission naming conventions | P0-07 |

**Checkpoint**: All spikes proven; **P0-01 concluded with "go"**; Vue scaffold deployed; DB transaction semantics verified; PDF renders in Arabic with real examples.

### Phase 1: Foundation — DB Environment and Base Schema

| Task | Description | ID |
|------|-------------|-----|
| 1.1 | Configure Supabase project, region, DB roles, Laravel connection | DB-01 |
| 1.2 | Migration conventions + clean-DB test workflow | DB-02 |
| 1.3 | Identity/admin/roles/permissions schema | DB-03 |
| 1.4 | Companies + company-users schema (2-user strategy, policy columns) | DB-04 |

**Cumulative**: DB-11 (constraints/indexes) and DB-12 (RLS) apply per domain as tables land; final DB-wide verification closes in Phase 8.

**Checkpoint**: Empty database builds from migrations alone; Laravel completes and rolls back transactions; both portals' identity tables exist.

### Phase 2: Authentication and RBAC

| Task | Description | ID |
|------|-------------|-----|
| 2.1 | Laravel foundation: logging, validation, error handling | BE-01 |
| 2.2 | Supabase Auth adapter + identity mapping + invitation flow | BE-02 |
| 2.3 | Protected Super Admin + role/permission services | BE-03 |
| 2.4 | Policies/gates + audit of permission/role changes | BE-04 |
| 2.5 | Account provisioning incl. Supabase invite + two-active-user enforcement | BE-05 |
| 2.6 | Seeders: Super Admin, permission catalogue, settings (EGP, Cairo, VAT flag, cutoff) | DB-13 |
| 2.7 | UI: sign-in/sign-out, invitation set-password screens, access/error states | FE-05 |
| 2.8 | UI: Super Admin role/permission screens | FE-06 |

**Checkpoint**: Company user #3 refused at limit; unknown/inactive identities blocked; URL entry without permission rejected; admin/company separation verified.

### Phase 3: Companies, Users and Quotas

| Task | Description | ID |
|------|-------------|-----|
| 3.1 | Company CRUD, legal details, partial-payment policy | BE-06 |
| 3.2 | Quota calculation (seat-counted), manual reset (clears bonus + window), bonus logic | BE-07 |
| 3.3 | UI: company list/detail/create/edit + user management (2-user feedback) | FE-07 |
| 3.4 | UI: quota view/reset confirmation | FE-08 |

**Checkpoint**: Company users read only their own company; reset atomic/audited; bonus seats affect the effective limit once.

### Phase 4: Trips, Recurrence, Pricing and Costs

| Task | Description | ID |
|------|-------------|-----|
| 4.1 | Trips/templates/seats/price-override schema | DB-05 |
| 4.2 | Trip CRUD + numbered seat creation | BE-08 |
| 4.3 | Recurring templates + idempotent 14-day generation | BE-09 |
| 4.4 | Effective price resolution + internal VAT snapshot on booking | BE-10 |
| 4.5 | UI: trip list/detail/create/edit/cancel | FE-09 |
| 4.6 | UI: recurring templates + generate-trips screens | FE-10 |
| 4.7 | UI: company trip-price override interface | FE-11 |
| 4.8 | Data dictionary + schema relationship diagram (handoff) | DB-14 |

**Checkpoint**: Duplicate recurring trips impossible; override price applied only to that company; decimal arithmetic validated.

### Phase 5: Booking, Seat Requests and Booking Lifecycle

| Task | Description | ID |
|------|-------------|-----|
| 5.1 | Bookings/booking-seats/seat-requests schema | DB-06 |
| 5.2 | Booking transaction: quota lock, cutoff, seat concurrency, atomic commit | BE-11 |
| 5.3 | Cancellation + seat change + cutoff release semantics | BE-12 |
| 5.4 | Seat requests + decision + atomic bonus grant | BE-13 |
| 5.5 | UI: company trip browsing + detail | FE-12 |
| 5.6 | UI: numbered seat picker + booking confirmation | FE-13 |
| 5.7 | UI: booking history/detail/cancel/change-seat screens | FE-14 |
| 5.8 | UI: seat request form + history + admin review queue | FE-15 |

**Checkpoint**: Concurrent same-seat requests → exactly one success; quota breach impossible under concurrency; cutoff and seat-release verified; concurrent users from two companies never see each other's data.

### Phase 6: Financial Core — Payments, Allocation, Refunds/Credits

| Task | Description | ID |
|------|-------------|-----|
| 6.1 | Payment/allocation/ledger schema | DB-07 |
| 6.2 | Credits/refunds/adjustments/monthly-close schema | DB-08 |
| 6.3 | Payment recording (reference only) + pending/confirm states | BE-14 |
| 6.4 | Allocation across booking obligations + partial-payment rules + invariants | BE-15 |
| 6.5 | Credit ledger, cash refund + credit applications (per-case choice) | BE-16 |
| 6.6 | Immutable adjustment/correction workflow | BE-17 |
| 6.7 | UI: payment entry + pending queue | FE-16 |
| 6.8 | UI: payment confirmation + allocation interface | FE-17 |
| 6.9 | UI: refund/credit forms + company credit history | FE-18 |

**Checkpoint**: Pending payments never inflate revenue; allocation beyond confirmed amount impossible; fully-settled booking event fires exactly once; credits/refunds bounded; confirmed records un-editable.

### Phase 7: Documents — Invoices, Receipts, PDFs

| Task | Description | ID |
|------|-------------|-----|
| 7.1 | Documents (invoice/receipt) + document-lines schema | DB-09 |
| 7.2 | Document building: auto-draft invoices on full settlement; receipts on confirmation; monthly paid-items by confirmation month | BE-18 |
| 7.3 | Numbering (`INV/RCT-YYYY-####`), approval, immutable snapshots | BE-19 |
| 7.4 | Bilingual PDF renderer + private storage + authorized retrieval; non-tax-document wording | BE-20 |
| 7.5 | Document email delivery + retry (outbox) | BE-21 |
| 7.6 | UI: document list/detail/preview/prepare/approve + monthly generator trigger | FE-19 |
| 7.7 | UI: company document list + approved PDF view/download | FE-20 |

**Checkpoint**: No unpaid amount described as paid; covering rule verified (no double presentation); company sees only its approved documents; rendering fits 0.5 GB RAM.

### Phase 8: Reporting and Monthly Close

| Task | Description | ID |
|------|-------------|-----|
| 8.1 | Full DB integrity/performance layer verified (constraints/indexes cruiser-check) | DB-11 |
| 8.2 | Full RLS enablement + tests for every app table | DB-12 |
| 8.3 | Operational reports + exports | BE-22 |
| 8.4 | Finance reports: confirmed revenue, company totals, transactions | BE-23 |
| 8.5 | Net profit (non-cancelled-seat cost, departure-month recognition) + internal VAT component | BE-24 |
| 8.6 | Prev-month/YTD comparisons, filters, exports | BE-25 |
| 8.7 | Monthly close + audited reopen/re-close + adjustment-only corrections | BE-26 |
| 8.8 | UI: operational reports + audit log viewer | FE-21 |
| 8.9 | UI: finance reports, filters, comparisons, exports | FE-22 |
| 8.10 | UI: monthly close + reopen + adjustment history screens | FE-23 |

**Checkpoint**: Unconfirmed payments never inflate revenue; report totals reconcile to confirmed ledger; closed-period mutation impossible except audited reopen → adjustment → re-close.

### Phase 9: CRM, Notifications, Audit, Frontend Quality

| Task | Description | ID |
|------|-------------|-----|
| 9.1 | CRM/settings/audit/outbox schema | DB-10 |
| 9.2 | Minimal CRM services + access policies | BE-27 |
| 9.3 | Audit coverage across operational/financial workflows | BE-28 |
| 9.4 | Bilingual email templates + dispatch (outbox) | BE-29 |
| 9.5 | UI: CRM contacts/notes/activity | FE-24 |
| 9.6 | Responsive + accessibility + bilingual RTL review | FE-25 |
| 9.7 | Critical-journey integration tests | FE-26 |

**Checkpoint**: Company users cannot access CRM/audit; audit entries complete (actor/action/subject/before-after); failed emails retryable without rollback; full Arabic/English RTL review passes.

### Phase 10: Backend Test Sign-off and Release

| Task | Description | ID |
|------|-------------|-----|
| 10.1 | Backend feature/permission/tenant/financial test sign-off | BE-30 |
| 10.2 | End-to-end operational workflows | REL-01 |
| 10.3 | Finance reconciliation + document acceptance | REL-02 |
| 10.4 | Security + tenant-isolation review | REL-03 |
| 10.5 | Backup/restore rehearsal (migration/rollback/rebuild) | REL-04, DB-15 |
| 10.6 | Hosting memory/queue/SMTP/storage validation under realistic use | REL-05 |
| 10.7 | Admin/finance/company user guides | REL-06 |
| 10.8 | Production configuration: issuer details, Super Admin, onboarding | REL-07 |
| 10.9 | Final regression + bilingual review + MVP release approval | REL-08 |

**Checkpoint**: MVP release candidate — both portals, CRM, notifications, exports, audit, backups and end-to-end tests pass (M7).

---

## Deployment

### Deliverables

- Deployment runbook (`docs/deploy.md`) for Pxxl (Laravel + Inertia/Vue) and Supabase project setup
- Health-check endpoint + monitoring/access logging
- Migration flow (deploys run migrations; no dashboard edits)
- Scheduler/worker strategy: **manual-trigger fallbacks** for recurring generation and any batch operations (monthly invoices are manual by decision RD-11); P0-05 decides whether scheduled commands are viable on Pxxl — never rely on cron or a permanent queue worker without the manual fallback
- Backup scripts: scheduled database dumps required before go-live (free Supabase plan has no automatic backups), independent of platform guarantees

### Deployment Requirements

- Validate Pxxl's actual memory (0.5 GB) **and CPU (0.5 vCPU)** limits — never trust advertised numbers (P0-03/P0-06)
- Chunked exports; paginated lists; no full-table loads
- Document generation must fit within hosting RAM; manual generation workflow as fallback
- Secrets only in server env (see [Environment Variables](#environment-variables)); never baked into bundles
- Monitor Supabase DB/storage/egress usage; set upgrade thresholds before limits are hit
- Rehearse rebuild/restore (DB-15) and document rollback procedure

---

## Environment Variables

### Backend (`.env` — server only)

```bash
# Application
APP_NAME="Transportation Sys"
APP_ENV=production            # local|staging|test|production
APP_KEY=
APP_URL=
APP_TIMEZONE=UTC              # storage timezone; business timezone lives in settings
APP_LOCALE=ar
APP_FALLBACK_LOCALE=en

# Supabase (server-only; NEVER expose to the browser)
SUPABASE_URL=https://xxx.supabase.co
SUPABASE_ANON_KEY=            # token/JWKS verification (adapter)
SUPABASE_SERVICE_ROLE_KEY=    # Admin API: invitations, identity mapping
SUPABASE_JWT_SECRET=          # token verification (per P0-01 selected method)

# Database (Supabase PostgreSQL via selected pooler)
DB_CONNECTION=pgsql
DB_HOST=aws-x.pooler.supabase.com
DB_PORT=6543
DB_DATABASE=postgres
DB_USERNAME=
DB_PASSWORD=

# Storage (private documents bucket)
STORAGE_BUCKET=documents
FILESYSTEM_DISK=

# Mail (outbox sends; failures never roll back business actions)
MAIL_MAILER=smtp
MAIL_HOST=
MAIL_PORT=
MAIL_USERNAME=
MAIL_PASSWORD=
MAIL_FROM_ADDRESS=
MAIL_FROM_NAME=
```

### Production Notes

- `SUPABASE_SERVICE_ROLE_KEY` and `DB_PASSWORD` are **server-only**; they never reach the browser, frontend bundles, client logs or the repository.
- Business rules (internal VAT flag/rate, cutoff default, currency, timezone, issuer profile, numbering prefixes) live in the **settings table**, not env — settings changes affect future transactions only via snapshots.
- Supabase Vault optional for secrets storage; per-tenant data isolation verified with the actual Laravel DB role (pooler included).

---

## Verification Checklist

### Definition of Done (per feature)

- [ ] Works for admin and company users with correct permissions (tested: Super Admin, Admin with and without the feature, accounting role, company user from another company)
- [ ] Acceptance criteria pass; automated tests cover changed business rules and important failure paths
- [ ] DB changes are migrations; run cleanly on an empty database; new tables have RLS
- [ ] Text exists in Arabic and English; RTL layout checked where UI/docs are affected
- [ ] Sensitive actions audited (who/what/when + before/after)
- [ ] Financial records preserve precision, historical snapshots and immutability; customer-facing outputs never show VAT and never present unpaid amounts as paid
- [ ] Change fits in 0.5 GB RAM / 0.5 vCPU; small diff; no unrelated refactors
- [ ] Committed with the plan task ID; plan/spec references updated if behavior changed

### Data Integrity Verification

- [ ] A seat cannot appear in more than one active booking (unique `(trip_id, seat_number)` + booking_seats checks)
- [ ] Recurring generation is idempotent — unique `(template_id, departure_start)` prevents duplicate trips
- [ ] Max two active company users enforced (service transaction + DB strategy)
- [ ] Quota usage derived from non-cancelled bookings since last reset (no drift-prone counters)
- [ ] Booking snapshots captured: effective unit price, internal VAT state/rate, totals, currency
- [ ] Payment invariants: `allocations + refunds ≤ confirmed amount`; booking never settled beyond remaining due; excess stays unallocated
- [ ] Document rules: paid items only; covering logic prevents double presentation; numbering gapless per type/year; approved documents immutable
- [ ] Immutable financial records: confirmed payments/refunds/credits/adjustments resist edit/delete from every surface
- [ ] Close/`reopen` completes with audit metadata and computes correctly afterwards
- [ ] Append-only audit log; outbox events recorded for every delivery

### Security Verification

- [ ] Supabase service-role key, DB password and SMTP credentials never in frontend bundles, client logs or repositories
- [ ] Laravel policies/gates guard every route and mutation (manual URL entry tested)
- [ ] RLS enabled on all tables; **no public policies**; deny-by-default standalone access (unauthenticated API)
- [ ] Company isolation: company A cannot read/write company B bookings, payments, credits or documents
- [ ] CSRF on; Form Request validation everywhere; booking/login/financial rate limits active
- [ ] Private document PDFs served only after authorization (non-guessable paths)
- [ ] Company users cannot access CRM or audit details
- [ ] No secret or storage link exposed in client assets

### RLS / Tenant Isolation Test Pattern

```sql
-- As unstable/anonymous access (Laravel is the ONLY application-level client):
SELECT * FROM bookings; -- via anonymous role → 0 rows (deny-by-default RLS)

-- As Laravel's server role (apply elevated role):
SELECT * FROM bookings LIMIT 10; -- returns rows only through the
                                 -- application connection; Laravel policies
                                 -- then scope reads/writes per company.
```

Application tests must assert: unauthenticated public API access reaches no data; server-side blockers reject cross-company reads/writes; RLS behavior verified with the **actual pooled DB role** used by Laravel (DB-12).

### Finance Reconciliation

- [ ] Report totals reconcile to confirmed ledger entries; pending payments never inflate revenue
- [ ] Net profit ties to the approved basis: confirmed payments received − non-cancelled-seats cost recognized in departure month; derivable internal VAT component only from booking-time snapshots
- [ ] Month boundaries/timezone rules consistent (Africa/Cairo, UTC storage)
- [ ] Exports restricted by permission; chunked; fit hosting memory

---

## Files to Create

### Backend / Application (Laravel — repo root)

```
/
├── app/
│   ├── Http/
│   │   ├── Controllers/
│   │   │   ├── Auth/          # login, logout, invite completion
│   │   │   ├── Admin/         # companies, users, trips, templates, pricing,
│   │   │   │                  # seat requests, CRM, roles, settings, audit
│   │   │   ├── Company/       # trips browse, booking, cancel/change, requests,
│   │   │   │                  # documents, payments/credits (own)
│   │   │   └── Finance/       # payments, allocations, refunds/credits, docs,
│   │   │                      # reports, exports, periods (close/reopen)
│   │   ├── Middleware/        # Supabase Auth adapter middleware, locale, gates
│   │   └── Requests/          # Form Requests per mutation (validation)
│   ├── Models/                # ~24 Eloquent models matching schema tables
│   ├── Policies/              # per-model tenant/permission policies
│   ├── Services/
│   │   ├── Auth/SupabaseAdapter.php       # token/session verification + invites
│   │   ├── Booking/BookingService.php     # transactional booking pipeline
│   │   ├── Booking/QuotaService.php       # seat-counted usage + reset + bonus
│   │   ├── Trips/TripService.php
│   │   ├── Trips/RecurrenceService.php    # idempotent 14-day generation
│   │   ├── Pricing/PriceResolver.php      # standard/override + VAT snapshot
│   │   ├── Finance/LedgerService.php      # immutable postings
│   │   ├── Finance/PaymentService.php     # record/confirm
│   │   ├── Finance/AllocationService.php  # split allocations + invariants
│   │   ├── Finance/RefundCreditService.php
│   │   ├── Finance/AdjustmentService.php
│   │   ├── Documents/DocumentBuilder.php  # paid-item selection + covering
│   │   ├── Documents/NumberingService.php # INV/RCT-YYYY-####
│   │   ├── Documents/ApprovalService.php
│   │   ├── Documents/PdfRenderer.php      # bilingual RTL PDFs (bounded RAM)
│   │   ├── Reporting/FinanceReports.php   # confirmed-revenue basis
│   │   ├── Reporting/OperationalReports.php
│   │   ├── Reporting/ExportService.php    # chunked Excel/PDF/CSV
│   │   ├── Period/CloseService.php        # close + audited reopen
│   │   ├── Crm/CrmService.php
│   │   └── Notifications/OutboxService.php
│   └── Support/               # helpers: money (NUMERIC), tz, audit, audit context
├── routes/
│   ├── web.php                # public + health + auth + invites
│   ├── web-admin.php          # admin portal (permission groups per section)
│   ├── web-company.php        # company portal
│   └── web-finance.php        # finance documents (+ period, reports)
├── resources/js/              # (structure shown under Frontend Structure)
├── database/
│   ├── migrations/
│   │   ├── 00001_settings_company_users_employees_role_admins.php   # DB-03
│   │   ├── 00002_companies_company_users.php                        # DB-04
│   │   ├── 00003_trips_templates_seats_price_overrides.php          # DB-05
│   │   ├── 00004_bookings_booking_seats_seat_requests.php           # DB-06
│   │   ├── 00005_payment_records_allocations_financial_transactions.php # DB-07
│   │   ├── 00006_company_credits_refunds_adjustments_monthly_closings.php # DB-08
│   │   ├── 00007_documents_document_lines.php                       # DB-09
│   │   ├── 00008_company_contacts_crm_notes_activities.php          # DB-10
│   │   ├── 00009_settings_audit_logs_outbox_events.php              # DB-10
│   │   ├── 00010_foreign_keys_unique_check_constraints.php          # DB-11
│   │   └── 00011_row_level_security_policies.php                    # DB-12
│   └── seeders/
│       ├── SuperAdminSeeder.php      # protected system role
│       ├── PermissionCatalogSeeder.php
│       ├── SettingsSeeder.php        # EGP, Cairo, cutoff, VAT 14% flag, placeholders
│       └── TestDataSeeder.php        # local/test envs only; never production data
├── tests/                     # Feature/Unit suites per Definition of Done
├── .env.example               # template only; real secrets never committed
├── composer.json
├── package.json
├── vite.config.js
├── tailwind.config.js
└── phpunit.xml
```

### Frontend (Inertia/Vue — under resources/js)

```
resources/js/                   # (full tree shown in Frontend Structure)
```

### project/docs

```
docs/
├── transportation-sys-master-implementation-plan.md  # this plan
└── deploy.md                                  # deployment runbook (Deployment)
```

---

## Appendix

### Constitution Alignment

The constitution (v1.1.0) predates the resolved decision log. The following items are **superseded by owner-approved decisions (Section [Key Decisions](#key-decisions))** and are flagged for an owner-only constitution amendment (the constitution file itself has not been modified in this restructuring):

| Constitution wording | Resolved decision | Status |
|----------------------|-------------------|--------|
| III: "React is the current assumption… confirm React or Vue" | RD-02: **Vue 3 + Inertia** | Resolved; amend III |
| V: document "include… tax and totals" | RD-04: documents carry **no tax lines** (internal-only VAT) | Resolved; amend V |
| IV: "eligible seat count and cost timing must be clarified" | RD-17: non-cancelled seats; departure-month recognition | Resolved; amend IV |
| IV: "snapshot the applied seat price, VAT enabled state/rate and calculated amounts" | RD-04: snapshots retained but VAT metadata is internal-only | Consistent; no amendment needed |

### Risk Register

| Risk | Why it matters | Required control |
|------|----------------|-----------------|
| Supabase Auth ↔ Laravel/Inertia custom adapter | Highest custom integration surface (sessions, expiry, invitations) | **P0-01 go/no-go gate** before dependent account flows; fallback options prepared for the owner |
| Pxxl 0.5 GB RAM **/ 0.5 vCPU** | PDF rendering, exports, PHP-FPM under ~60 daily-active users | Measure early (P0-03/P0-06); chunk jobs; manual/synchronous fallbacks; upgrade plan ready |
| Supabase connection pooling vs transactions | Seat locking + allocations need real transaction semantics | P0-04 proof + DB constraints as final net |
| Free-tier availability/backups | Pausing/limits affect a production ERP | Verify terms; rehearse backups outside platform guarantees; budget upgrade path |
| Financial ambiguity | Money/tax math — resolved (RD-04…RD-19) | Key Decisions is the single source of truth; conflicts resolve in its favor |
| Invoice legal status | PDFs must not claim to be statutory ETA tax invoices | No tax lines; internal-document wording; qualified tax advice before regulatory claims |
| Concurrent seat booking | Two users taking one seat | Transaction + conditional update + unique constraint + concurrency tests |
| Authorization gaps | Permissions + tenant boundaries are security-critical | Central policies, RLS behind Laravel, tenant tests |
| Email failure | Provider outage must not block business ops | Commit first; outbox/retry |
| Arabic PDF quality | RTL shaping/fonts under low memory | Early PDF spike with real Arabic samples at target memory |
| Financial report mismatch | Ledger double-counting | Reconcile every report to immutable transactions + approved formulas (covering rules) |

### Spec Extraction Map

Proposed focused specifications for later extraction (via Spec-Kit):

- **DB Spec A** — Identity, tenancy, access schema · **DB Spec B** — Trips/seats/booking schema · **DB Spec C** — Financial ledger schema (incl. close/reopen) · **DB Spec D** — Documents/settings/period schema · **DB Spec E** — Audit/CRM/support schema.
- **BE Spec A** — Auth (adapter, invitations) + RBAC · **BE Spec B** — Companies/users/quotas · **BE Spec C** — Trips/recurrence/pricing/costs · **BE Spec D** — Booking/concurrency/lifecycle/requests · **BE Spec E** — Payments/allocation/refunds/credits/adjustments · **BE Spec F** — Document lifecycle (invoices/receipts) · **BE Spec G** — Reports/profit/close · **BE Spec H** — CRM/audit/operational reports/notifications.
- **FE Spec A** — Shell/design/auth/localization · **FE Spec B** — Admin access/companies/users/quotas · **FE Spec C** — Admin trips/recurrence/pricing/requests · **FE Spec D** — Company portal/booking · **FE Spec E** — Finance ops · **FE Spec F** — Documents (incl. receipts, monthly generator) · **FE Spec G** — Reports/audit/close · **FE Spec H** — CRM + notification UX.

Each spec: one bounded purpose, roles/permissions, flows and state transitions, data ownership, contract, invariants, security/tenant/audit/localization, acceptance criteria, dependencies + Build Order IDs. No spec in isolation where shared financial/booking/permission invariants apply — cross-reference instead of duplicating.

### Cost & Hosting Validation Notes

- **Verified Pxxl free tier:** 5 project slots (2 web-service), 1 custom domain, 0.5 GB RAM **and 0.5 vCPU** per project, 50 GiB monthly egress, 300 build minutes, 24 h artifact retention, no free database slot (Supabase covers persistence).
- **Assumed Supabase free plan:** ~500 MB database, 1 GB storage, 5 GB egress, project pausing on inactivity, no automatic backups — reconfirm terms before launch.
- Demonstrate at planning scale: 14 companies × 60 users with daily booking, concurrent seat claims, monthly document batches (manual), bilingual PDFs within RAM, exports; rehearsed backup/restore; upgrade path documented.
- Email delivery via an SMTP provider chosen before production; monitor egress/storage monthly against upgrade thresholds.

### Governance and Status

- This plan captures the agreed direction; extracted specs must remain consistent with it unless the owner approves a change (recorded in [Key Decisions](#key-decisions) first, then propagated).
- Owners approve business-rule changes; agents recommend but never silently decide. Conflicts resolve in favor of the business rule + security invariant. Every task cites its Build Order ID.
- **Delivery milestones** — M1 foundation proven (Phase 0/1) → M2 operational foundation (Phase 2–4) → M3 booking (Phase 5) → M4 financial core (Phase 6) → M5 documents (Phase 7) → M6 reporting/close (Phase 8) → M7 MVP release candidate (Phase 9–10).
- **Plan status:** all decisions resolved (2026-11-09); remaining pre-launch items are data entry/validation: issuer legal details + logo, production credentials, hosting/plan revalidation, business acceptance run. **Next action:** execute Phase 0 (P0-01 first as the go/no-go), then spec extraction per the map above.

---

*End of Implementation Plan*
