# B2B Transportation ERP: Simple Architecture

## Database Schema (PostgreSQL)

Seven tables matching the entities above. Foreign keys enforce the relationships, and check constraints protect the basic rules.

&#91;embedded content: entity relationship diagram · 7 tables\]

Admin and company tables meet only through trips, bookings, and seat requests.

```sql
-- Admin side
CREATE TABLE admin_users (
  id            BIGSERIAL PRIMARY KEY,
  name          VARCHAR(120) NOT NULL,
  email         VARCHAR(255) NOT NULL UNIQUE,
  password_hash VARCHAR(255) NOT NULL,
  role          VARCHAR(20)  NOT NULL CHECK (role IN ('SUPER_ADMIN', 'ADMIN')),
  is_active     BOOLEAN      NOT NULL DEFAULT TRUE,
  created_at    TIMESTAMPTZ  NOT NULL DEFAULT NOW()
);

CREATE TABLE admin_permissions (
  id            BIGSERIAL PRIMARY KEY,
  admin_user_id BIGINT      NOT NULL REFERENCES admin_users(id) ON DELETE CASCADE,
  feature       VARCHAR(50) NOT NULL,
  granted_by    BIGINT      NOT NULL REFERENCES admin_users(id),
  granted_at    TIMESTAMPTZ NOT NULL DEFAULT NOW(),
  UNIQUE (admin_user_id, feature)
);

-- Company side
CREATE TABLE companies (
  id         BIGSERIAL PRIMARY KEY,
  name       VARCHAR(160) NOT NULL UNIQUE,
  contact    VARCHAR(255),
  seat_limit INT          NOT NULL DEFAULT 0 CHECK (seat_limit >= 0),
  is_active  BOOLEAN      NOT NULL DEFAULT TRUE,
  created_at TIMESTAMPTZ  NOT NULL DEFAULT NOW()
);

CREATE TABLE company_users (
  id            BIGSERIAL PRIMARY KEY,
  company_id    BIGINT       NOT NULL REFERENCES companies(id) ON DELETE CASCADE,
  name          VARCHAR(120) NOT NULL,
  email         VARCHAR(255) NOT NULL UNIQUE,
  password_hash VARCHAR(255) NOT NULL,
  role          VARCHAR(20)  NOT NULL CHECK (role IN ('MANAGER', 'STAFF')),
  is_active     BOOLEAN      NOT NULL DEFAULT TRUE
);

-- Trips and bookings
CREATE TABLE trips (
  id                 BIGSERIAL PRIMARY KEY,
  route_name         VARCHAR(160) NOT NULL,
  origin             VARCHAR(160) NOT NULL,
  destination        VARCHAR(160) NOT NULL,
  departure_datetime TIMESTAMPTZ  NOT NULL,
  total_seats        INT          NOT NULL CHECK (total_seats > 0),
  status             VARCHAR(20)  NOT NULL DEFAULT 'SCHEDULED'
                     CHECK (status IN ('SCHEDULED', 'CANCELLED', 'COMPLETED')),
  created_by         BIGINT       NOT NULL REFERENCES admin_users(id)
);

CREATE TABLE bookings (
  id          BIGSERIAL PRIMARY KEY,
  company_id  BIGINT      NOT NULL REFERENCES companies(id),
  trip_id     BIGINT      NOT NULL REFERENCES trips(id),
  booked_by   BIGINT      NOT NULL REFERENCES company_users(id),
  seats_count INT         NOT NULL CHECK (seats_count > 0),
  status      VARCHAR(20) NOT NULL DEFAULT 'CONFIRMED'
              CHECK (status IN ('CONFIRMED', 'CANCELLED')),
  created_at  TIMESTAMPTZ NOT NULL DEFAULT NOW()
);

-- Formal requests for a higher seat limit
CREATE TABLE seat_requests (
  id              BIGSERIAL PRIMARY KEY,
  company_id      BIGINT      NOT NULL REFERENCES companies(id),
  requested_by    BIGINT      NOT NULL REFERENCES company_users(id),
  requested_seats INT         NOT NULL CHECK (requested_seats > 0),
  reason          TEXT,
  status          VARCHAR(20) NOT NULL DEFAULT 'PENDING'
                  CHECK (status IN ('PENDING', 'APPROVED', 'REJECTED')),
  reviewed_by     BIGINT      REFERENCES admin_users(id),
  review_note     TEXT,
  created_at      TIMESTAMPTZ NOT NULL DEFAULT NOW(),
  reviewed_at     TIMESTAMPTZ
);

-- Helpful indexes
CREATE INDEX idx_bookings_trip    ON bookings(trip_id);
CREATE INDEX idx_bookings_company ON bookings(company_id);
CREATE INDEX idx_requests_status  ON seat_requests(status);
CREATE INDEX idx_trips_departure  ON trips(departure_datetime);
```

## Rules the Database Cannot Enforce Alone

Three rules need application code or triggers, because they depend on sums across rows:

1. A company's total booked seats must not exceed its seat\_limit.
2. A trip's total booked seats must not exceed its total\_seats.
3. A company can have at most two company\_users.

## Overview

A web application where administrators manage trips and seat allocation, and client companies book seats on those trips within a seat limit. Companies can formally request more seats, which admins approve or reject.

&#91;embedded content: entity diagram · 7 entities\]

Admins create trips and review seat requests; companies make bookings and ask for more seats when they hit their limit.

## Entities

### 1. AdminUser

People who run the system.

- id
- name, email, password hash
- role: SUPER\_ADMIN or ADMIN
- is\_active
- created\_at

### 2. AdminPermission

Controls which features a regular admin can use. Granted by a Super Admin.

- id
- admin\_user\_id (FK to AdminUser)
- feature: for example MANAGE\_TRIPS, MANAGE\_COMPANIES, MANAGE\_SEAT\_LIMITS, APPROVE\_REQUESTS, VIEW\_REPORTS
- granted\_by (FK to AdminUser, must be a Super Admin)
- granted\_at

### 3. Company

The B2B clients, around 14 at the start.

- id
- name, contact info
- seat\_limit: the maximum seats the company may hold
- is\_active
- created\_at

### 4. CompanyUser

Login accounts for a company. Maximum two per company.

- id
- company\_id (FK to Company)
- name, email, password hash
- role: MANAGER or STAFF
- is\_active

### 5. Trip

A scheduled journey that seats can be booked on.

- id
- route\_name, origin, destination
- departure\_datetime
- total\_seats
- status: SCHEDULED, CANCELLED, COMPLETED
- created\_by (FK to AdminUser)

### 6. Booking

A company reserving seats on a trip.

- id
- company\_id (FK to Company)
- trip\_id (FK to Trip)
- booked\_by (FK to CompanyUser)
- seats\_count
- status: CONFIRMED, CANCELLED
- created\_at

### 7. SeatRequest

A formal request from a company for a higher seat limit.

- id
- company\_id (FK to Company)
- requested\_by (FK to CompanyUser)
- requested\_seats: the extra seats wanted
- reason (optional)
- status: PENDING, APPROVED, REJECTED
- reviewed\_by (FK to AdminUser, nullable until reviewed)
- review\_note (optional)
- created\_at, reviewed\_at

## Relationships

- One Company has many CompanyUsers, with a maximum of two.
- One Company has many Bookings.
- One Trip has many Bookings.
- One Company has many SeatRequests.
- One AdminUser creates many Trips.
- One AdminUser reviews many SeatRequests.
- One AdminUser has many AdminPermissions, which only a Super Admin can grant or remove.

## Key Business Rules

1. **Company seat limit:** the sum of seats\_count across a company's active bookings must never exceed its seat\_limit. Decide whether the limit applies to upcoming trips only, or per month; this is worth confirming.
2. **Trip capacity:** the sum of seats\_count across all confirmed bookings on a trip must never exceed the trip's total\_seats.
3. **Two accounts per company:** block creating a third CompanyUser for the same company.
4. **Seat requests:** when a company is at its limit it submits a SeatRequest. An admin with the APPROVE\_REQUESTS permission approves or rejects it. On approval, the company's seat\_limit increases by requested\_seats. The admin should only approve if enough seats are available.
5. **Admin permissions:** only a Super Admin can grant or revoke permissions. A Super Admin implicitly has every permission. Admins can also manually increase or decrease a company's seat\_limit if they hold the MANAGE\_SEAT\_LIMITS permission.
6. **Audit trail (recommended):** log every seat limit change and every request decision, with who did it and when.

## Suggested Next Steps

1. Pick the stack, for example a relational database such as PostgreSQL with a REST API backend and a web frontend.
2. Build authentication with two separate login areas: admin and company.
3. Build the admin side first: companies, seat limits, trips, permissions.
4. Build the company side: view trips, book seats, submit seat requests.
5. Add the request approval flow and the audit log.
