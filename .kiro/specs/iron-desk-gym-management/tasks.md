# Implementation Plan: IronDesk Gym Management System

## Overview

This plan breaks the IronDesk design into incremental coding tasks aligned with all 16 requirement areas and 40 correctness properties. The Laravel 12 backend and Vue 3 + TypeScript frontend are built in parallel where possible, with shared interfaces established early. Every task produces working, integrated code — no orphaned components.

## Tasks

- [ ] 1. Project scaffolding and infrastructure
  - [ ] 1.1 Initialize Laravel 12 project with Sail, configure MySQL 8.x, Redis, and S3-compatible storage drivers in `.env.example`
    - Set up `docker-compose.yml` with `mysql`, `redis`, and `mailpit` services
    - Configure `config/session.php` for Redis driver with 8-hour lifetime (`SESSION_LIFETIME=480`)
    - Configure `config/queue.php` with Redis driver as default
    - Add health and readiness endpoints (`/up`, `/ready`) returning 200 within 1 second
    - _Requirements: 13.1, 14.1, 15.1, 15.4_

  - [ ] 1.2 Initialize Vue 3 + TypeScript + Vite frontend project
    - Set up Vue Router, Pinia store, and Axios with CSRF cookie interceptor for Sanctum SPA auth
    - Configure `vite.config.ts` with proxy to Laravel dev server
    - Create base `api.ts` Axios instance that attaches `X-XSRF-TOKEN` header and handles 401/403 globally
    - _Requirements: 1.2, 13.1_

  - [ ] 1.3 Create all database migrations for core tables in dependency order
    - Migrations: `gyms`, `users`, `members`, `qr_codes`, `membership_plans`, `memberships`, `invoices`, `payments`, `attendance_records`, `notifications`, `notification_settings`, `audit_logs`
    - Every primary table includes `gym_id BIGINT UNSIGNED NOT NULL` FK to `gyms`
    - Add all indexes defined in the design (FULLTEXT on `members`, composite expiry/overdue/attendance indexes, ULID unique index)
    - _Requirements: 11.1, 11.2, 15.2_

  - [ ]* 1.4 Write integration smoke test confirming all migrations run cleanly and health endpoint returns 200
    - _Requirements: 15.4_


- [ ] 2. Multi-tenancy foundation — Eloquent global scope and `BelongsToGym` trait
  - [ ] 2.1 Implement `GymScope` Eloquent global scope and `BelongsToGym` trait
    - `GymScope::apply()` injects `WHERE {table}.gym_id = ?` using the currently resolved gym from the request context
    - Apply the trait to: `Member`, `MembershipPlan`, `Membership`, `Invoice`, `Payment`, `AttendanceRecord`, `Notification`, `NotificationSetting`, `AuditLog`, `User`
    - Implement `currentGymId()` helper that reads the resolved gym from the request (set by `verified.gym` middleware)
    - _Requirements: 1.1, 11.1, 11.7_

  - [ ]* 2.2 Write property test for Tenant Data Isolation (P1)
    - **Property 1: Tenant Data Isolation**
    - Create two gyms with overlapping record data; assert every query scoped to G1 returns zero G2 records across all models
    - **Validates: Requirements 1.1, 11.1, 11.7**

  - [ ] 2.3 Implement `verified.gym` middleware
    - Resolve gym from the authenticated user's `gym_id`; attach gym to request; reject if gym is suspended (HTTP 403)
    - _Requirements: 11.5, 11.7_

  - [ ] 2.4 Create Eloquent model classes for all tables with relationships, casts, and fillable fields
    - `Gym`, `User`, `Member`, `QrCode`, `MembershipPlan`, `Membership`, `Invoice`, `Payment`, `AttendanceRecord`, `Notification`, `NotificationSetting`, `AuditLog`
    - _Requirements: 15.2_


- [ ] 3. Authentication — Laravel Sanctum SPA auth with account lockout
  - [ ] 3.1 Implement `AuthController` with login, logout, and password reset endpoints
    - `POST /sanctum/csrf-cookie` (native Sanctum), `POST /auth/login`, `POST /auth/logout`, `POST /auth/reset-password`
    - `LoginRequest` validates email + password; passes through `ThrottleLogins` trait logic
    - On successful login: resolve gym from user, store `{gym_id, user_id, role}` in Redis-backed session
    - On failed login: increment `failed_login_count` on `users` table; if count reaches 5 within 10 minutes, set `locked_until = now() + 15 minutes` and dispatch `AccountLockedNotificationJob`
    - Session lifetime enforced via `SESSION_LIFETIME=480` (8 hours)
    - _Requirements: 2.1, 13.2, 13.3, 13.4, 13.5_

  - [ ]* 3.2 Write property test for Password Hashing Algorithm (P30)
    - **Property 30: Password Hashing Algorithm**
    - Generate random passwords; assert stored hash is valid bcrypt (cost ≥ 12) or Argon2id; assert plaintext never appears in any response or log
    - **Validates: Requirements 13.2**

  - [ ]* 3.3 Write property test for Account Lockout (P31)
    - **Property 31: Account Lockout After Failed Attempts**
    - Simulate exactly 5 consecutive failed logins within a 10-minute window; assert account is locked; assert 6th attempt (even with correct password) is rejected for 15 minutes
    - **Validates: Requirements 13.3**

  - [ ]* 3.4 Write property test for Session Token Expiry (P32)
    - **Property 32: Session Token Expiry**
    - Issue session, advance time past 8 hours via `Carbon::setTestNow()`; assert all subsequent requests return 401 without executing the operation
    - **Validates: Requirements 13.4, 13.5**

  - [ ] 3.5 Implement password reset flow
    - One-time signed URL valid for 24 hours; `POST /auth/reset-password` validates token, hashes new password with bcrypt cost 12 minimum, invalidates token
    - _Requirements: 8.2, 11.4_


- [ ] 4. Role-based access control — Laravel Policies, Gates, and Audit logging
  - [ ] 4.1 Implement `GymPolicy`, `MemberPolicy`, `StaffPolicy`, `BillingPolicy`, and Gate definitions
    - Map each policy method to the permitted role set defined in the design (Super_Admin, Gym_Owner, Receptionist)
    - Register policies in `AuthServiceProvider`
    - All policy denials return 403 (not 404) via `AuthorizationException` handler
    - _Requirements: 1.3, 2.2, 2.3, 2.4, 2.5_

  - [ ]* 4.2 Write property test for Role-Based Access Control (P2)
    - **Property 2: Role-Based Access Control**
    - Generate random (role, endpoint) pairs; assert response is 403 if and only if the role is not in the permitted set for that endpoint
    - **Validates: Requirements 1.3, 2.2, 2.3, 2.4**

  - [ ] 4.3 Implement `AuditLogger` service and `MemberObserver`
    - `AuditLogger::log(User $user, string $action, Model $resource, array $previousValues = [])` writes to `audit_logs` inside the same `DB::transaction()`
    - `MemberObserver::updating()` captures `getDirty()` values and calls `AuditLogger::log()` before the update is applied
    - Register observer in `AppServiceProvider`
    - _Requirements: 3.4, 8.7_

  - [ ]* 4.4 Write property test for Unauthorized Access is Audited (P3)
    - **Property 3: Unauthorized Access is Audited**
    - For any request resulting in 403, assert an `AuditLog` entry exists with user_id, resource, timestamp, and IP
    - **Validates: Requirements 2.5**

  - [ ]* 4.5 Write property test for Cross-Tenant Staff Management Blocked (P4)
    - **Property 4: Cross-Tenant Staff Management is Blocked**
    - Attempt staff create/deactivate/reactivate on G2 from a G1 Gym_Owner session; assert 403 + audit log entry
    - **Validates: Requirements 2.9**

  - [ ]* 4.6 Write property test for Audit Log Entry Completeness (P24)
    - **Property 24: Audit Log Entry Completeness**
    - For any audited action, assert all five fields (user_id, action_type, resource_id, timestamp_utc, ip_address) are present in the resulting entry
    - **Validates: Requirements 8.7**

  - [ ]* 4.7 Write property test for Audit Log Filter Correctness (P23)
    - **Property 23: Audit Log Filter Correctness**
    - Generate random filter combinations (staff, action type, date range); assert all returned entries satisfy all active filters and no matching entry is omitted
    - **Validates: Requirements 8.6**


- [ ] 5. Member management — CRUD, QR code, soft delete, photo upload, CSV export
  - [ ] 5.1 Implement `StoreMemberRequest`, `UpdateMemberRequest`, and `MemberService::create()`
    - Validate all required fields per Req 3.3; reject on missing/invalid fields with 422 + field list
    - On successful creation, generate ULID for member, call `QrCodeService::generate()`, store QR image to S3
    - On duplicate phone within same gym, return 409 with confirmation prompt payload
    - _Requirements: 3.1, 3.2, 3.3, 3.5, 3.9_

  - [ ]* 5.2 Write property test for Member Creation Assigns Unique QR Code (P5)
    - **Property 5: Member Creation Assigns Unique QR Code**
    - Register N random members in same gym; assert all `qr_codes.code_payload` values are distinct across all N records
    - **Validates: Requirements 3.1, 3.5**

  - [ ]* 5.3 Write property test for Invalid Member Payload Rejection (P6)
    - **Property 6: Invalid Member Payloads are Rejected with Field Errors**
    - Generate payloads with various missing/invalid fields; assert 422 with each offending field named; assert no Member record created
    - **Validates: Requirements 3.2**

  - [ ] 5.4 Implement `MemberService::update()` with audit logging and `MemberService::deactivate()`
    - `update()` calls `AuditLogger::log()` with `getDirty()` previous values inside `DB::transaction()` before applying changes
    - `deactivate()` sets `status = inactive`; inactive members excluded from search, active counts, and membership assignment
    - _Requirements: 3.4, 3.7, 3.8_

  - [ ]* 5.5 Write property test for Member Updates are Audit Logged Before Apply (P7)
    - **Property 7: Member Updates are Audit Logged Before Apply**
    - Update random fields on a Member; assert AuditLog entry with previous values is written before the transaction commits
    - **Validates: Requirements 3.4**

  - [ ]* 5.6 Write property test for Inactive Member Exclusion (P8)
    - **Property 8: Inactive Members are Universally Excluded**
    - Mix active/inactive members; assert inactive appear in zero of: search results, active counts, membership assignment lists
    - **Validates: Requirements 3.7, 3.8**

  - [ ] 5.7 Implement profile photo upload in `MemberController`
    - Validate MIME type (JPEG/PNG) and file size (≤5 MB) in `UpdateMemberRequest`
    - On valid upload: store to S3 via Laravel Filesystem, save URL to `members.photo_url`
    - On invalid: return 422 error, leave Member record unchanged
    - _Requirements: 3.10_

  - [ ]* 5.8 Write property test for Profile Photo Upload Validation (P9)
    - **Property 9: Profile Photo Upload Validation**
    - Send random file type + size combinations; assert only JPEG/PNG ≤5 MB are accepted; assert Member record unchanged on rejection
    - **Validates: Requirements 3.10**

  - [ ] 5.9 Implement `MemberService::exportCsv()` and `GET /members/export` endpoint
    - Stream CSV containing all fields for all members (active + inactive) within the gym using Laravel's `StreamedResponse`
    - _Requirements: 3.11_

  - [ ]* 5.10 Write property test for Member CSV Export Completeness (P10)
    - **Property 10: Member CSV Export Completeness**
    - Create N members (mix of active and inactive); assert exported CSV has exactly N data rows with all stored fields present
    - **Validates: Requirements 3.11**

  - [ ] 5.11 Implement member search (`GET /members?search=`) with FULLTEXT index
    - Use `MATCH(full_name, phone) AGAINST(?)` for search queries; return only active members; paginate at 50 default / 200 max
    - _Requirements: 3.6, 12.3, 12.4_


- [ ] 6. Membership plans — create, update, deactivate
  - [ ] 6.1 Implement `StorePlanRequest`, `UpdatePlanRequest`, and `PlanService`
    - `StorePlanRequest` validates: name (1–100 chars), duration_days (1–3650), price (0.01–999999.99), currency (ISO 4217), optional description (≤500 chars), included_services (≤20 items, each ≤100 chars)
    - Reject duplicate plan name within same gym with 409
    - `PlanService::deactivate()` sets `status = inactive`; deactivated plans excluded from `GET /plans` (Receptionist list)
    - `PlanService::updatePrice()` updates price; does not modify existing memberships or their snapshots
    - _Requirements: 4.1, 4.2, 4.3, 4.5, 4.6, 4.7, 4.8_

  - [ ]* 6.2 Write property test for Deactivated Plan Cannot Be Assigned (P11)
    - **Property 11: Deactivated Plan Cannot Be Assigned**
    - Deactivate a plan; assert it is absent from `GET /plans`; assert attempt to create Membership with that plan_id returns error
    - **Validates: Requirements 4.3, 4.6**

  - [ ]* 6.3 Write property test for Plan Deactivation Does Not Affect Existing Memberships (P12)
    - **Property 12: Plan Deactivation Does Not Affect Existing Memberships**
    - Create N active memberships referencing plan P; deactivate P; assert all N memberships retain their status and plan_snapshot
    - **Validates: Requirements 4.4**

  - [ ]* 6.4 Write property test for Plan Price Update Preserves Historical Snapshots (P13)
    - **Property 13: Plan Price Update Does Not Affect Historical Snapshots**
    - Create memberships before price update and after; assert pre-update memberships have old price in snapshot, post-update memberships have new price
    - **Validates: Requirements 4.5**

  - [ ]* 6.5 Write property test for Duplicate Plan Name Rejection (P14)
    - **Property 14: Duplicate Plan Name is Rejected**
    - Submit plan creation with a name identical to existing plan (active or inactive) in same gym; assert 409 and no new plan created
    - **Validates: Requirements 4.7**

  - [ ]* 6.6 Write property test for Plan Validation Rejects Out-of-Range Values (P15)
    - **Property 15: Plan Validation Rejects Out-of-Range Values**
    - Generate price/duration outside permitted ranges; assert 422 identifying the invalid field(s) and no plan persisted
    - **Validates: Requirements 4.8**


- [ ] 7. Membership assignment and renewal — end date, grace period, reconciliation
  - [ ] 7.1 Implement `MembershipService::assign()` and `MembershipService::calculateEndDate()`
    - `assign()` creates Membership with `plan_snapshot` JSON of plan attributes at assignment time, `status = active`, computed `end_date`
    - `calculateEndDate(Date $start, int $durationDays): Date` returns `start + ($durationDays - 1)`
    - Immediately triggers invoice creation via `BillingService::createInvoice()`
    - _Requirements: 5.1, 5.2, 7.1_

  - [ ]* 7.2 Write property test for Membership End Date Calculation (P16)
    - **Property 16: Membership End Date Calculation**
    - Generate random start dates and durations (1–3650); assert `end_date = start + (duration - 1)` holds for all combinations; assert 1-day plan ends on start date, 30-day plan ends on start+29
    - **Validates: Requirements 5.1, 5.2**

  - [ ] 7.3 Implement `MembershipService::renew()` with active/grace/expired branching
    - If current membership is `active` or within grace period: new start = current end + 1 day
    - If expired and outside grace period: new start = renewal action date
    - Store full history (never delete memberships); dispatch invoice creation
    - _Requirements: 5.3, 5.4, 5.5_

  - [ ]* 7.4 Write property test for Membership Renewal Date Continuity (P17)
    - **Property 17: Membership Renewal Date Continuity**
    - Generate memberships with status active or within grace period; assert new membership start_date = previous end_date + 1 day for all cases
    - **Validates: Requirements 5.3**

  - [ ]* 7.5 Write property test for Membership History Completeness (P18)
    - **Property 18: Membership History Completeness**
    - Create K memberships for a member; assert `GET /members/{id}/memberships` returns exactly K records all belonging to that member and gym
    - **Validates: Requirements 5.5**

  - [ ] 7.6 Implement `MembershipReconcileJob` (daily scheduler, 00:05 UTC)
    - Query memberships where `status = active` and `end_date + grace_period_days < today`
    - Set status to `expired`; dispatch `NotificationDispatchJob` for each expired membership
    - Run within 24 hours of end date per requirements
    - _Requirements: 5.6, 5.7, 5.8_


- [ ] 8. Attendance tracking — QR scan, manual check-in, auto-close, filters
  - [ ] 8.1 Implement `QrCodeService` and `AttendanceService::checkIn()`
    - `QrCodeService::generate(Member $member)` creates unique ULID payload, renders QR image via `endroid/qr-code`, stores PNG to S3, saves record to `qr_codes`
    - `AttendanceService::checkIn(string $qrPayload)` resolves member by QR payload scoped to current gym; rejects unknown payloads with error (no record created)
    - Creates `AttendanceRecord` with `entry_method = qr_scan`, `status = checked_in`, `checked_in_at = now()`
    - Must complete within 2 seconds under 100 concurrent users
    - _Requirements: 6.1, 6.2, 3.5_

  - [ ]* 8.2 Write property test for Unrecognized QR Code Produces No Attendance Record (P38)
    - **Property 38: Unrecognized QR Code Produces No Attendance Record**
    - Send random QR payloads not matching any member in gym; assert error response and zero AttendanceRecord created
    - **Validates: Requirements 6.2**

  - [ ] 8.3 Implement duplicate check-in guard and expired membership check-in warning in `AttendanceService`
    - Duplicate check-in: if member has open session (no checkout, same calendar day), return `duplicate_checkin` warning; require explicit `force: true` flag to proceed
    - Expired membership: if member's active membership is expired and outside grace period, return `membership_expired` warning; require explicit `confirmed: true` flag to proceed
    - _Requirements: 6.4, 6.5_

  - [ ]* 8.4 Write property test for Duplicate Check-In Guard (P39)
    - **Property 39: Duplicate Check-In Guard**
    - Members with open check-in sessions; assert second scan returns warning without creating a new record; assert confirmation flag allows record creation
    - **Validates: Requirements 6.4**

  - [ ]* 8.5 Write property test for Expired Member Check-In Warning (P40)
    - **Property 40: Expired Member Check-In Warning**
    - Members with expired membership outside grace period; assert check-in returns expiry warning; assert record saved only after explicit confirmation
    - **Validates: Requirements 6.5**

  - [ ] 8.6 Implement manual check-in (`POST /attendance/manual`), checkout (`POST /attendance/{id}/checkout`), and auto-close job
    - Manual check-in: search by name/phone (active members only), create record with `entry_method = manual`
    - Checkout: set `checked_out_at = now()`, `status = checked_out`
    - `AttendanceAutoCloseJob` (scheduler: every 30 min): set `status = auto_closed` for all sessions where `checked_in_at < now() - 12h` and `checked_out_at IS NULL`
    - _Requirements: 6.3, 6.6, 6.7_

  - [ ] 8.7 Implement attendance list with filters (`GET /attendance`)
    - Filter by date range, member_id, recorded_by; paginate at 50 default / 200 max; return within 3 seconds for datasets up to 10,000 records
    - _Requirements: 6.8, 12.3, 12.4_


- [ ] 9. Billing and payments — invoice auto-generation, partial payments, overdue job
  - [ ] 9.1 Implement `BillingService::createInvoice()` triggered by membership create/renew
    - Invoice contains: member name, plan name (from snapshot), `amount = plan_snapshot.price`, `paid_amount = 0`, `status = unpaid`
    - `due_date = membership.start_date + notification_settings.invoice_due_offset_days` (0–30, default 0)
    - Exactly one invoice per membership creation/renewal event (enforce via unique constraint on `membership_id`)
    - _Requirements: 7.1_

  - [ ]* 9.2 Write property test for Invoice Auto-Generation (P19)
    - **Property 19: Invoice Auto-Generation on Membership Creation and Renewal**
    - Create/renew memberships N times; assert exactly one Invoice is generated per event with correct member name, plan name, amount, and due date
    - **Validates: Requirements 7.1**

  - [ ] 9.3 Implement `BillingService::recordPayment()` with partial payment and overpayment logic
    - On payment recording: add to `invoices.paid_amount`; recalculate status: `paid` if `paid_amount >= amount`, `partially_paid` if `0 < paid_amount < amount`
    - Reject payment if `payment_amount > (amount - paid_amount)` with 422
    - Never store raw card numbers; payment_method is enum: `cash | bank_transfer | card`
    - _Requirements: 7.3, 7.4, 7.5, 7.9_

  - [ ]* 9.4 Write property test for Partial Payment Leaves Correct Balance (P20)
    - **Property 20: Partial Payment Leaves Correct Balance**
    - Generate invoices with random total amounts; record partial payments; assert balance = amount − sum(payments) and status = partially_paid
    - **Validates: Requirements 7.4**

  - [ ]* 9.5 Write property test for Overpayment Rejection (P21)
    - **Property 21: Overpayment is Rejected**
    - Attempt payment > outstanding balance; assert 422 returned; assert Invoice balance and status remain unchanged
    - **Validates: Requirements 7.5**

  - [ ] 9.6 Implement `InvoiceOverdueJob` (scheduler: daily 00:10 UTC)
    - Update `status = overdue` for all invoices where `due_date < today` and `status != paid`
    - _Requirements: 7.7_

  - [ ] 9.7 Implement invoice list endpoint with pagination and sorting
    - `GET /invoices?status=unpaid&sort=due_date&order=asc` — paginated at 100 default / 200 max, sortable by `due_date` ASC/DESC
    - _Requirements: 7.6, 12.3, 12.4_


- [ ] 10. Staff management — receptionist accounts, welcome email, session invalidation
  - [ ] 10.1 Implement `StaffService::createReceptionist()` and `StoreStaffRequest`
    - Validate: full_name (1–100 chars), unique-within-gym email, temporary password (8–72 chars)
    - Create `User` record with `role = receptionist`, `status = active`, `gym_id` from requesting Gym_Owner
    - Dispatch `SendWelcomeEmailJob` to queue; email contains one-time password reset link (signed URL, 24h TTL)
    - If email delivery fails within 60 seconds, set account `status = pending`, return error to Gym_Owner with resend option
    - _Requirements: 8.1, 8.2, 8.3_

  - [ ] 10.2 Implement `StaffService::deactivate()`, `StaffService::reactivate()`, and `InvalidateSessionJob`
    - `deactivate()`: set `status = deactivated`, dispatch `InvalidateSessionJob` synchronously (delete session key from Redis within 5 seconds)
    - `reactivate()`: set `status = active`; user must re-authenticate
    - Gym_Owner cannot deactivate/reactivate accounts in a different gym (enforced by `StaffPolicy`)
    - _Requirements: 2.7, 2.8, 8.5_

  - [ ] 10.3 Implement staff list endpoint and resend-invite endpoint
    - `GET /staff`: list all users in gym with name, role, status, last_login_at (UTC or "Never")
    - `POST /staff/{id}/resend-invite`: re-dispatch `SendWelcomeEmailJob` with new signed URL
    - _Requirements: 8.4_

  - [ ] 10.4 Implement audit log endpoint (`GET /audit-logs`) with filters and pagination
    - Filters: staff member (user_id), action_type, date range; paginate at 1,000 entries per page
    - Retention: reject queries older than 84 months; ensure records are retained minimum 12 months
    - _Requirements: 8.6, 8.7, 8.8_


- [ ] 11. Dashboard and reports — cached metrics, CSV export, role-scoped views
  - [ ] 11.1 Implement `DashboardService` with Redis-cached metrics
    - Metrics computed per gym: total active members, expiring in 7 days, currently checked in, revenue this calendar month, count of unpaid invoices
    - Cache with `Cache::remember("dashboard:{$gymId}", 30, fn() => ...)` (30-second TTL)
    - If Redis unavailable: fall back to direct MySQL query and include `X-Metric-Source: database` response header
    - Role-scoped: Receptionist sees only `checked_in_count` and `expiring_today_members` (via Laravel API Resource)
    - _Requirements: 9.1, 9.2, 9.6, 9.7_

  - [ ] 11.2 Implement `ReportService::attendanceReport()` and `ReportService::membershipReport()`
    - Attendance report: daily check-in count and unique member count for each day in date range
    - Membership report: active, expired, and newly enrolled counts for the date range
    - _Requirements: 9.3, 9.4_

  - [ ]* 11.3 Write property test for Attendance Report Accuracy (P25)
    - **Property 25: Attendance Report Accuracy**
    - Generate random attendance records across a date range; assert daily counts and unique member counts match actual records exactly
    - **Validates: Requirements 9.3**

  - [ ]* 11.4 Write property test for Membership Report Accuracy (P26)
    - **Property 26: Membership Report Accuracy**
    - Generate memberships with known states; assert report counts for active, expired, newly enrolled match database state at end of range
    - **Validates: Requirements 9.4**

  - [ ] 11.3 Implement revenue summary report endpoint (`GET /invoices/report`)
    - Date range up to 366 days; totals: collected, outstanding, breakdown by plan
    - _Requirements: 7.8_

  - [ ]* 11.5 Write property test for Revenue Report Accuracy (P22)
    - **Property 22: Revenue Report Accuracy**
    - Generate payment datasets within/outside a date range; assert total collected = sum of payments within range; assert per-plan breakdown sums to total
    - **Validates: Requirements 7.8**

  - [ ] 11.6 Implement CSV export for all reports (`GET /invoices/report?export=csv`, attendance, membership)
    - Stream CSV with all displayed data rows using `StreamedResponse`
    - _Requirements: 9.5_

  - [ ]* 11.7 Write property test for Report CSV Completeness (P27)
    - **Property 27: Report CSV Completeness**
    - Generate reports with R rows; assert exported CSV contains exactly R data rows with all displayed fields present
    - **Validates: Requirements 9.5**


- [ ] 12. Notifications — in-app, email dispatch, idempotency, configurable addresses
  - [ ] 12.1 Implement `NotificationDispatchJob` with idempotency keys
    - Runs every 30 minutes via scheduler
    - Check 7-day expiry, 1-day expiry, membership expiry, overdue invoice (3 days past due)
    - Idempotency: use unique key `"{gym_id}:{event_type}:{entity_id}:{cycle_date}"` stored in Redis (TTL 48h); skip if key exists
    - Create `Notification` record in MySQL for each new trigger
    - Dispatch `SendMailJob` for each configured email address in `notification_settings.email_addresses`
    - _Requirements: 10.1, 10.2, 10.3, 10.4, 10.5_

  - [ ]* 12.2 Write property test for Notification Idempotency (P28)
    - **Property 28: Notification Idempotency**
    - Run `NotificationDispatchJob` multiple times within same check cycle for same trigger conditions; assert at most one Notification record per entity per event type
    - **Validates: Requirements 10.5**

  - [ ] 12.3 Implement notification settings endpoints and `NotificationSettingsService`
    - `GET /notifications/settings` and `PATCH /notifications/settings`: validate up to 5 email addresses (RFC 5321, ≤254 chars each), `grace_period_days` (≥0), `invoice_due_offset_days` (0–30)
    - _Requirements: 10.6_

  - [ ] 12.4 Implement notification list and mark-as-read endpoints
    - `GET /notifications`: unread first, include message, entity name, timestamp; paginate at 50 default
    - `POST /notifications/{id}/read`: update `is_read = 1`, set `read_at = now()`; return updated unread count within 2 seconds
    - Retain notifications for minimum 90 days (soft delete / TTL policy via scheduler)
    - _Requirements: 10.7, 10.8, 10.9_

  - [ ]* 12.5 Write property test for Notification Panel Entry Completeness (P29)
    - **Property 29: Notification Panel Entry Completeness**
    - Generate random Notification records; assert each panel entry contains message, triggering entity name, and creation timestamp
    - **Validates: Requirements 10.7**


- [ ] 13. Multi-tenancy infrastructure — gym provisioning, suspension, reactivation
  - [ ] 13.1 Implement `GymService::provision()` in a database transaction
    - Create `gyms` row, `users` row (Gym_Owner), `notification_settings` row (defaults) within `DB::transaction()`
    - On any failure: full rollback, return 500 to Super_Admin, no partial state left
    - Dispatch `SendWelcomeEmailJob` for the new Gym_Owner (login credentials + 24h reset link)
    - Complete within 30 seconds
    - _Requirements: 1.4, 1.6, 11.2, 11.4_

  - [ ]* 13.2 Write property test for Tenant Provisioning Duplicate Email Rejection (P36)
    - **Property 36: Tenant Provisioning Duplicate Email Rejection**
    - Submit provisioning requests using emails already registered in existing gyms; assert 409 and no new gym or user created
    - **Validates: Requirements 11.3**

  - [ ] 13.3 Implement `GymService::suspend()` and `GymService::reactivate()`
    - `suspend()`: set `gyms.status = suspended`; `verified.gym` middleware immediately rejects all logins for that gym with 403; no data deleted
    - `reactivate()`: set `gyms.status = active`; only accounts that were active before suspension regain access (accounts with `status = deactivated` remain blocked)
    - _Requirements: 11.5, 11.6_

  - [ ]* 13.4 Write property test for Suspended Tenant Reactivation Preserves Deactivations (P37)
    - **Property 37: Suspended Tenant Reactivation Preserves Individually Deactivated Accounts**
    - Set up gym with mix of active and individually deactivated users; suspend then reactivate gym; assert pre-deactivated accounts remain deactivated, others regain access
    - **Validates: Requirements 11.6**

  - [ ] 13.5 Implement Super_Admin platform dashboard endpoint (`GET /admin/platform`)
    - Returns: total tenant count, non-suspended active tenant count, platform-wide non-expired membership count
    - Response must not contain any member PII (validated by API Resource)
    - _Requirements: 11.8_

  - [ ]* 13.6 Write property test for Platform Dashboard PII Exclusion (P35)
    - **Property 35: Platform Dashboard PII Exclusion**
    - Generate gyms with member data including names, phones, emails; assert Super_Admin platform response JSON contains none of those PII fields
    - **Validates: Requirements 11.8**


- [ ] 14. Security hardening — input sanitization, PII log exclusion, HTTPS enforcement
  - [ ] 14.1 Configure HTTPS enforcement, CSRF protection, and security headers
    - Force HTTPS in `TrustProxies` middleware and `APP_URL`; set HSTS header via `Secure-Headers` middleware
    - Sanctum CSRF cookie is `SameSite=Strict; Secure; HttpOnly`
    - _Requirements: 13.1_

  - [ ] 14.2 Implement input sanitization layer across all Form Requests
    - All `StoreMemberRequest`, `UpdateMemberRequest`, `StorePlanRequest`, and equivalent requests use Laravel's `strip_tags` / `htmlspecialchars` helpers for string fields
    - Eloquent parameterized queries prevent SQL injection by default; add regression tests for injection payloads
    - Add CSRF protection via Sanctum's built-in XSRF-TOKEN cookie verification for all state-changing endpoints
    - _Requirements: 13.6_

  - [ ]* 14.3 Write property test for Input Sanitization (P33)
    - **Property 33: Input Sanitization**
    - Send payloads containing SQL injection patterns, XSS strings, and CSRF vectors; assert system processes sanitized version and executes no unintended query or script
    - **Validates: Requirements 13.6**

  - [ ] 14.4 Configure Monolog to exclude PII from application logs
    - Add a custom Monolog processor that redacts fields matching `email`, `phone`, `full_name`, `name` before log write
    - _Requirements: 13.7_

  - [ ]* 14.5 Write property test for PII Exclusion from Application Logs (P34)
    - **Property 34: PII Exclusion from Application Logs**
    - Execute operations involving Member and User data; capture log output; assert no names, phone numbers, or email addresses appear in plaintext
    - **Validates: Requirements 13.7**


- [ ] 15. Checkpoint — core backend complete
  - Ensure all Pest PHP tests pass (`./vendor/bin/pest --parallel`)
  - Verify all migrations run cleanly on a fresh database (`php artisan migrate:fresh`)
  - Verify health endpoint responds within 1 second
  - Ask the user if any requirements need revision before proceeding to the Vue frontend

- [ ] 16. Vue 3 frontend — authentication, layout, and routing
  - [ ] 16.1 Implement Vue Router with role-based route guards and global 401/403 handlers
    - Routes: `/login`, `/dashboard`, `/members`, `/members/:id`, `/plans`, `/memberships`, `/attendance`, `/billing`, `/staff`, `/audit-logs`, `/notifications`, `/admin/*`
    - Navigation guard reads Pinia auth store for role; redirects to 403 page on unauthorized routes
    - Axios interceptor: on 401 → clear store and redirect to `/login`; on 403 → show toast
    - _Requirements: 1.2, 1.3, 2.2, 2.3_

  - [ ] 16.2 Implement Pinia `authStore` with login, logout, and session state
    - `login()` calls `GET /sanctum/csrf-cookie` then `POST /auth/login`; stores `{user, role, gymId}` in store
    - `logout()` calls `POST /auth/logout`, clears store
    - Session expiry (8h) handled via Axios 401 interceptor
    - _Requirements: 13.4_

  - [ ] 16.3 Implement shared layout components: `AppShell`, `NavSidebar`, `NotificationBell`
    - `NotificationBell` polls `GET /notifications?unread=true` every 30 seconds and shows unread count badge
    - Sidebar menu items rendered conditionally based on user role from auth store
    - _Requirements: 1.3, 9.7, 10.7_


- [ ] 17. Vue 3 frontend — Member management views
  - [ ] 17.1 Implement `MemberListView` with search, pagination, and inactive exclusion
    - Debounced search input (300ms) calls `GET /members?search=` with name/phone query
    - Paginated table: 50 per page, next/prev controls; no inactive members in results
    - Buttons: Add Member, Export CSV (triggers file download from `GET /members/export`)
    - _Requirements: 3.6, 3.8, 3.11_

  - [ ] 17.2 Implement `MemberFormView` (create and edit) with photo upload
    - All required fields with inline validation messages; file input restricted to JPEG/PNG ≤5 MB (client-side pre-validation before upload)
    - Duplicate phone: display confirmation dialog with existing member name before submitting
    - _Requirements: 3.1, 3.2, 3.3, 3.9, 3.10_

  - [ ] 17.3 Implement `MemberProfileView` with QR code display and membership history
    - Show QR image fetched from `GET /members/{id}/qr` (signed S3 URL)
    - Membership history table from `GET /members/{id}/memberships` (all records including historical)
    - Assign/renew membership actions scoped by role (Receptionist and Gym_Owner both allowed)
    - _Requirements: 3.5, 5.1, 5.5_


- [ ] 18. Vue 3 frontend — Attendance, Plans, Billing, Staff, and Dashboard views
  - [ ] 18.1 Implement `AttendanceView` with QR scan input and manual check-in
    - QR input field: auto-focuses on mount; submits on Enter; displays success/warning/error toasts
    - Duplicate check-in warning: modal dialog with "Confirm" / "Cancel" actions
    - Expired membership warning: full-screen banner covering upper third of viewport, requiring explicit staff confirmation
    - Manual check-in: type-ahead search for active members by name/phone
    - _Requirements: 6.1, 6.3, 6.4, 6.5_

  - [ ] 18.2 Implement `PlanListView` and `PlanFormView` (Gym_Owner only)
    - List all active plans with name, price, duration; deactivate action with confirmation
    - Create/edit form with all plan fields; inline validation for ranges and duplicate name
    - _Requirements: 4.1, 4.2, 4.3, 4.7, 4.8_

  - [ ] 18.3 Implement `BillingView` with invoice list, payment recording, and revenue report
    - Paginated invoice list filtered by status (unpaid/partially_paid/overdue/paid), sortable by due_date
    - Payment dialog: amount input with outstanding balance shown, method selector
    - Revenue report form: date range picker (max 366 days), display summary table, CSV export button
    - _Requirements: 7.2, 7.3, 7.6, 7.8_

  - [ ] 18.4 Implement `StaffView` (Gym_Owner only) with receptionist list, create form, and deactivate/reactivate actions
    - Table shows name, role, status, last login (UTC)
    - Create form: name, email, temporary password fields
    - Deactivate/reactivate with confirmation; resend invite button for pending accounts
    - _Requirements: 8.1, 8.4, 8.5_

  - [ ] 18.5 Implement `DashboardView` with role-scoped metrics and auto-refresh
    - Gym_Owner dashboard: 5 metric cards (active members, expiring in 7 days, checked in, revenue, unpaid invoices)
    - Receptionist dashboard: checked-in count + expiring today list
    - Auto-refresh `checked_in_count` metric every 30 seconds without full page reload (poll `GET /dashboard` or use a targeted endpoint)
    - Display "Metric source: database" notice if Redis cache is unavailable
    - _Requirements: 9.1, 9.2, 9.6, 9.7_

  - [ ] 18.6 Implement `AuditLogView` (Gym_Owner only) with filters and pagination
    - Filters: staff member select, action type select, date range picker; paginate at 1,000 per page
    - _Requirements: 8.6_

  - [ ] 18.7 Implement `NotificationPanel` slide-over with mark-as-read
    - Lists all notifications (unread first) with message, entity name, timestamp
    - Click to mark as read; unread badge count decrements immediately (optimistic update)
    - _Requirements: 10.7, 10.8_

  - [ ] 18.8 Implement Super_Admin `GymListView` and platform dashboard
    - List all gyms with status, tenant count, suspend/reactivate actions
    - Platform dashboard card: total tenants, active tenants, platform-wide active memberships (no PII shown)
    - Provision new gym form: gym name, owner name, owner email, subscription tier
    - _Requirements: 11.2, 11.5, 11.6, 11.8_


- [ ] 19. Integration wiring — background scheduler, queue workers, and error isolation
  - [ ] 19.1 Register all background jobs in Laravel Scheduler (`app/Console/Kernel.php`)
    - `MembershipReconcileJob` → daily at 00:05 UTC
    - `InvoiceOverdueJob` → daily at 00:10 UTC
    - `NotificationDispatchJob` → every 30 minutes
    - `AttendanceAutoCloseJob` → every 30 minutes
    - Notification 90-day cleanup job → daily at 01:00 UTC
    - _Requirements: 5.6, 5.7, 6.7, 7.7, 10.9, 15.3_

  - [ ] 19.2 Configure Laravel Queue with retry, backoff, and `failed_jobs` handling
    - `SendMailJob`: `$tries = 5`, exponential backoff `[10, 30, 60, 120, 300]` seconds
    - All other jobs: `$tries = 3`, fixed 60-second backoff
    - Ensure notification subsystem failures do not propagate to attendance or billing paths (separate queues: `notifications` vs `default`)
    - _Requirements: 14.4, 14.5_

  - [ ] 19.3 Wire all module services end-to-end and run full integration test suite
    - Test: complete gym provisioning flow (provision → login → create member → assign plan → generate invoice → record payment → check-in)
    - Test: membership reconciliation job (set end date to yesterday → run job → assert expired + notification created)
    - Test: session invalidation within 5 seconds of account deactivation
    - _Requirements: 1.4, 5.6, 8.5_

  - [ ]* 19.4 Write integration tests for all 16 requirement areas (one scenario each minimum)
    - Cover all modules: auth, members, plans, memberships, attendance, billing, staff, notifications, dashboard, tenant management
    - _Requirements: 1.1–16.6_


- [ ] 20. Final checkpoint — full system verification
  - Run full Pest PHP test suite: `./vendor/bin/pest --parallel` — all tests must pass
  - Run `php artisan migrate:fresh --seed` on a clean database and verify all seeders complete
  - Verify health endpoint (`/up`) responds within 1 second
  - Confirm no PII appears in any log file after exercising all user flows
  - Ask the user if any final adjustments are needed before considering implementation complete

---

## Notes

- Tasks marked with `*` are optional and can be skipped for a faster MVP build
- Each task references specific requirements and correctness properties for full traceability
- All 40 correctness properties from the design document are covered by property test sub-tasks
- The multi-tenancy global scope (Task 2) is a hard dependency for every module — it must be implemented first
- Background jobs run in a separate queue from the request-response path; email delivery failure does not block attendance or billing
- All property tests use Pest PHP with Faker-generated datasets (minimum 100 iterations per property)
- Integration tests require Docker Compose / Laravel Sail with MySQL and Redis running


## Task Dependency Graph

```json
{
  "waves": [
    { "id": 0, "tasks": ["1.1", "1.2"] },
    { "id": 1, "tasks": ["1.3", "1.4"] },
    { "id": 2, "tasks": ["2.1", "2.3", "2.4"] },
    { "id": 3, "tasks": ["2.2", "3.1", "4.1"] },
    { "id": 4, "tasks": ["3.2", "3.3", "3.4", "3.5", "4.2", "4.3"] },
    { "id": 5, "tasks": ["4.4", "4.5", "4.6", "4.7", "5.1"] },
    { "id": 6, "tasks": ["5.2", "5.3", "5.4", "6.1", "7.1", "8.1", "9.1", "10.1", "10.2", "13.1"] },
    { "id": 7, "tasks": ["5.5", "5.6", "5.7", "5.8", "5.9", "5.11", "6.2", "6.3", "6.4", "6.5", "6.6", "7.2", "7.3", "7.6", "8.2", "8.3", "8.6", "8.7", "9.2", "9.3", "9.6", "9.7", "10.3", "10.4", "11.1", "11.2", "11.3", "11.6", "12.1", "12.3", "12.4", "13.2", "13.3", "13.5", "14.1", "14.2", "14.4"] },
    { "id": 8, "tasks": ["5.10", "7.4", "7.5", "8.4", "8.5", "9.4", "9.5", "11.4", "11.5", "11.7", "12.2", "12.5", "13.4", "13.6", "14.3", "14.5"] },
    { "id": 9, "tasks": ["16.1", "16.2", "16.3", "19.1", "19.2"] },
    { "id": 10, "tasks": ["17.1", "17.2", "17.3", "18.1", "18.2", "18.3", "18.4", "18.5", "18.6", "18.7", "18.8"] },
    { "id": 11, "tasks": ["19.3", "19.4"] }
  ]
}
```
