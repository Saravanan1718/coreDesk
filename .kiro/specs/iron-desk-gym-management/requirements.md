# Requirements Document

## Introduction

IronDesk is a SaaS-ready Gym Management System designed for gym owners and receptionists. It replaces paper notebooks and fragmented spreadsheets with a centralized, multi-tenant dashboard. Each gym business operates as an isolated tenant within the platform. The system manages members, attendance, membership plans, billing, staff, notifications, and reports — all accessible from a web-based interface. IronDesk is not a member-facing application; it is an internal operations tool for gym staff.

---

## Glossary

- **IronDesk**: The SaaS gym management platform described in this document.
- **Tenant**: A single gym business operating within IronDesk. Each tenant's data is fully isolated from other tenants.
- **Gym_Owner**: The primary administrator of a tenant. Has full access to all features and settings within their tenant.
- **Receptionist**: A staff member with limited operational access — can manage members, record attendance, and process payments, but cannot modify plans, staff, or system settings.
- **Member**: A person enrolled in a gym. Not a system user; managed as a data record by gym staff.
- **Membership_Plan**: A subscription package offered by a gym, defining duration, price, and included services.
- **Membership**: An active association between a Member and a Membership_Plan, with defined start and end dates.
- **Attendance_Record**: A timestamped log entry marking a member's check-in or check-out at the gym.
- **Invoice**: A billing record generated when a Membership is created or renewed.
- **Payment**: A record of a financial transaction against an Invoice.
- **Dashboard**: The main landing screen for authenticated users, showing operational summaries and key metrics.
- **Notification**: An in-app or external alert triggered by system events such as membership expiry or missed payments.
- **Super_Admin**: A platform-level administrator (IronDesk operator) who manages tenants and platform health. Not affiliated with any single gym.
- **Audit_Log**: An immutable record of user actions within a tenant, used for accountability and debugging.
- **QR_Code**: A machine-readable code assigned to each Member for rapid attendance check-in.
- **Report**: An aggregated data export or visualization covering membership, attendance, revenue, or staff activity.
- **Grace_Period**: A configurable number of days after a Membership expires during which the Member is still treated as active.

---

## Requirements

---

### Requirement 1: Product Vision and Platform Goals

**User Story:** As a gym owner, I want a single platform to manage all gym operations, so that I can replace notebooks and spreadsheets with a reliable, modern system.

#### Acceptance Criteria

1. THE IronDesk SHALL operate as a multi-tenant SaaS platform where each Tenant's data is logically isolated from all other Tenants, such that no query, report, or API response returns data belonging to more than one Tenant.
2. THE IronDesk SHALL provide a web-based interface accessible from modern desktop browsers (Chrome, Firefox, Edge, Safari — latest two major versions each) without requiring any browser plugin or extension.
3. THE IronDesk SHALL enforce role-based access so that a Gym_Owner has full access to all modules within their Tenant, and a Receptionist has access only to member management, attendance recording, and payment collection.
4. WHEN a new Tenant is registered, THE IronDesk SHALL create an isolated data partition for that Tenant and confirm completion within 30 seconds before granting access to any user.
5. THE IronDesk SHALL be operable entirely by Gym_Owner and Receptionist roles without requiring any member-facing interface or member login capability.
6. IF the creation of a new Tenant's data partition fails, THEN THE IronDesk SHALL not grant access to any user for that Tenant, shall display an error to the Super_Admin, and shall not leave a partially initialized partition in the system.

---

### Requirement 2: User Roles and Access Control

**User Story:** As a gym owner, I want distinct permission levels for myself and my receptionists, so that staff cannot access sensitive business settings or financial summaries.

#### Acceptance Criteria

1. THE IronDesk SHALL support exactly three user roles: Super_Admin, Gym_Owner, and Receptionist; each user account SHALL be assigned exactly one role at account creation time.
2. THE Gym_Owner SHALL have full access to all modules within their Tenant, including member management, billing, staff management, reports, and system settings, and SHALL NOT have access to other Tenants' data or platform-level settings.
3. THE Receptionist SHALL have access to member management, attendance recording, and payment collection, but SHALL NOT have access to staff management, financial reports, or Tenant settings.
4. THE Super_Admin SHALL have access to tenant provisioning, platform-level monitoring, and tenant suspension, but SHALL NOT have access to any individual Tenant's member or billing data.
5. WHEN a user attempts to access a resource outside their permitted role, THE IronDesk SHALL return an authorization error containing the user identifier, target resource, timestamp, and the user's role, and SHALL log the attempt.
6. THE Gym_Owner SHALL be able to create a Receptionist account with a username between 3 and 50 characters, a valid email address, a password of at least 8 characters, and SHALL associate the account with the Gym_Owner's Tenant before the account is active.
7. WHEN a Receptionist account is deactivated, THE IronDesk SHALL immediately change the account status to inactive, preventing new logins.
8. WHEN a Receptionist account is deactivated, THE IronDesk SHALL invalidate all active sessions for that account within 5 seconds of deactivation; WHEN the account is reactivated, the Receptionist SHALL be required to authenticate again before gaining access.
9. THE Gym_Owner SHALL NOT be able to create, deactivate, or reactivate accounts belonging to a different Tenant; IF such an attempt is made, THE IronDesk SHALL return an authorization error and log the attempt.

---

### Requirement 3: Member Management

**User Story:** As a receptionist, I want to register and manage gym members, so that I can maintain accurate records without using paper forms.

#### Acceptance Criteria

1. WHEN a staff member submits a member registration form with all required fields present and valid, THE IronDesk SHALL create a Member record with a unique identifier within the Tenant.
2. IF a staff member submits a member registration form with one or more required fields missing or invalid, THEN THE IronDesk SHALL reject the submission and indicate which specific fields are incomplete or invalid without creating a Member record.
3. THE Member record SHALL store: full name, date of birth, gender, contact phone number (maximum 15 digits), emergency contact name and phone (maximum 15 digits each), profile photo (optional), and registration date.
4. WHEN a staff member updates a Member record, THE IronDesk SHALL record all modified fields with their previous values in the Audit_Log before applying the changes.
5. WHEN a Member is registered, THE IronDesk SHALL assign a unique QR_Code to that Member, and the QR_Code SHALL be scannable and associated with that Member's identifier.
6. WHEN a staff member searches for a Member by name or phone number using a query between 1 and 100 characters, THE IronDesk SHALL return matching active Member records within 1 second for datasets up to 10,000 active members per Tenant; inactive members SHALL NOT appear in search results.
7. WHEN a staff member requests deletion of a Member record, THE IronDesk SHALL mark the Member as inactive rather than permanently removing data.
8. WHEN a Member is marked inactive, THE IronDesk SHALL exclude that Member from search results, active member counts, and new Membership assignments.
9. IF a staff member attempts to register a Member with a phone number already registered within the same Tenant, THEN THE IronDesk SHALL display a duplicate warning with a confirmation prompt and require explicit confirmation before creating the new record.
10. WHEN a staff member uploads a profile photo, THE IronDesk SHALL accept only JPEG or PNG files up to 5 MB in size; IF the file type or size is invalid, THE IronDesk SHALL display an error message and reject the upload without creating or modifying the Member record.
11. WHEN the Gym_Owner requests a member list export, THE IronDesk SHALL generate a CSV file containing all stored fields for both active and inactive members within the Tenant.

---

### Requirement 4: Membership Plans

**User Story:** As a gym owner, I want to define and manage membership plans, so that I can offer structured pricing tiers to my members.

#### Acceptance Criteria

1. THE Gym_Owner SHALL be able to create a Membership_Plan with the following required attributes: plan name (1–100 characters), duration in days (1–3650), price (0.01–999999.99), and currency (ISO 4217 code).
2. THE Gym_Owner SHALL be able to add an optional description (maximum 500 characters) and a list of up to 20 included services (each up to 100 characters) to a Membership_Plan.
3. THE Gym_Owner SHALL be able to deactivate a Membership_Plan, after which no new Memberships SHALL be assigned to that plan.
4. WHEN a Membership_Plan is deactivated, THE IronDesk SHALL NOT modify any existing active Memberships associated with that plan.
5. THE Gym_Owner SHALL be able to update the price of a Membership_Plan, and the update SHALL apply only to Memberships created after the date and time of the update.
6. THE IronDesk SHALL display all active Membership_Plans to Receptionists when assigning a Membership to a Member.
7. IF a Gym_Owner attempts to create a Membership_Plan with a name already used by an existing plan within the same Tenant, THEN THE IronDesk SHALL reject the creation and display a duplicate name error.
8. IF a Gym_Owner submits a Membership_Plan with a price or duration outside the permitted ranges, THEN THE IronDesk SHALL reject the submission and identify the invalid field in the error response.

---

### Requirement 5: Membership Assignment and Renewal

**User Story:** As a receptionist, I want to assign and renew memberships for members, so that I can track who is currently subscribed and when their membership expires.

#### Acceptance Criteria

1. WHEN a staff member assigns a Membership_Plan to a Member, THE IronDesk SHALL create a Membership record containing a start date, calculated end date, a snapshot of the Membership_Plan's attributes at the time of assignment, and a status of "active".
2. THE IronDesk SHALL calculate the Membership end date as the start date plus the plan duration in days minus one, such that a 30-day plan starting on day 1 ends on day 30.
3. WHEN a staff member renews a Membership that is currently active or within the Grace_Period, THE IronDesk SHALL set the new Membership start date to the day after the current Membership end date.
4. WHEN a staff member renews a Membership that has expired and is outside the Grace_Period, THE IronDesk SHALL set the new Membership start date to the renewal action date.
5. THE IronDesk SHALL maintain a full history of all Memberships for each Member within a Tenant, and all historical records SHALL be retrievable by staff from the Member's profile.
6. WHEN a Membership end date passes and no Grace_Period is configured, THE IronDesk SHALL update the Membership status to "expired" no later than 24 hours after the end date during the daily reconciliation cycle.
7. WHERE a Grace_Period is configured by the Gym_Owner and WHEN the Grace_Period days after the Membership end date have also passed, THE IronDesk SHALL update the Membership status to "expired" during the daily reconciliation cycle no later than 24 hours after the Grace_Period end.
8. WHEN a Member's Membership status changes to "expired" during the reconciliation cycle, THE IronDesk SHALL trigger a Notification to all staff channels configured for expiry alerts within the Tenant.

---

### Requirement 6: Attendance Tracking

**User Story:** As a receptionist, I want to record member check-ins quickly, so that I can maintain an accurate daily attendance log without slowing down the queue at the front desk.

#### Acceptance Criteria

1. WHEN a staff member scans a Member's QR_Code, THE IronDesk SHALL create an Attendance_Record with the member identifier, timestamp, and recording staff identifier within 2 seconds.
2. WHEN a staff member scans a QR_Code that is not recognized within the Tenant, THE IronDesk SHALL display an error message indicating the code is invalid and SHALL NOT create an Attendance_Record.
3. WHEN a staff member performs a manual attendance search by name or phone number, THE IronDesk SHALL display matching active members within 2 seconds; IF no matches are found, THE IronDesk SHALL display a no-results message.
4. IF a staff member attempts to check in a Member who already has an open check-in session (no check-out recorded) on the same day, THEN THE IronDesk SHALL display a duplicate check-in warning and require explicit confirmation before creating a new Attendance_Record.
5. WHEN an attendance check-in is recorded for a Member whose Membership status is "expired" and outside the Grace_Period, THE IronDesk SHALL display an expiry warning occupying at least the upper third of the active screen area and require explicit staff confirmation before saving the Attendance_Record.
6. THE IronDesk SHALL record both check-in and check-out timestamps for each attendance session.
7. IF a check-out is not recorded within 12 hours of a check-in, THEN THE IronDesk SHALL automatically mark the session as "check-out not recorded" without deleting the check-in data.
8. WHEN a staff member applies filters (date range, member, or staff member) on the attendance records view, THE IronDesk SHALL return the filtered and paginated results within 3 seconds.
9. THE IronDesk SHALL display the total number of members currently checked in within the gym on the Dashboard, updated with a maximum staleness of 30 seconds.

---

### Requirement 7: Billing and Payments

**User Story:** As a gym owner, I want to track payments for memberships, so that I can reconcile revenue and identify overdue accounts.

#### Acceptance Criteria

1. WHEN a Membership is created or renewed, THE IronDesk SHALL automatically generate an Invoice linked to that Membership, containing the member name, plan name, amount due, and a due date defaulting to the Membership start date with a configurable offset of up to 30 days per Tenant.
2. THE IronDesk SHALL support the following payment methods as configurable options per Tenant: cash, bank transfer, and card (recorded manually — no payment gateway integration in the initial version).
3. WHEN a staff member records a Payment against an Invoice, THE IronDesk SHALL update the Invoice status to "paid" and record the payment method, amount, date, and recording staff identifier.
4. IF a Payment amount is less than the outstanding Invoice balance, THEN THE IronDesk SHALL mark the Invoice as "partially paid", calculate the outstanding balance as the Invoice amount minus all recorded payments, and SHALL NOT change the status to "paid" until the balance reaches zero.
5. IF a staff member attempts to record a Payment amount exceeding the outstanding Invoice balance, THEN THE IronDesk SHALL reject the payment and display an error indicating the maximum acceptable amount.
6. THE IronDesk SHALL display all unpaid and partially paid Invoices on a dedicated billing screen, paginated at up to 100 records per page, sortable by due date in ascending or descending order.
7. WHEN an Invoice's due date passes and the Invoice status is not "paid", THE IronDesk SHALL update the Invoice status to "overdue".
8. THE Gym_Owner SHALL be able to generate a revenue summary Report for a selected date range of up to 366 days, showing total collected, total outstanding, and a breakdown by Membership_Plan.
9. THE IronDesk SHALL NOT store raw card numbers or any sensitive payment instrument data.

---

### Requirement 8: Staff Management

**User Story:** As a gym owner, I want to manage my receptionist accounts, so that I can control who has access to the system and audit their actions.

#### Acceptance Criteria

1. THE Gym_Owner SHALL be able to create a Receptionist account by providing: full name (1–100 characters), a valid and unique-within-Tenant email address, and a temporary password (8–72 characters).
2. WHEN a Receptionist account is created, THE IronDesk SHALL send a welcome email to the provided address containing a one-time password reset link valid for exactly 24 hours.
3. IF the welcome email cannot be delivered within 60 seconds, THE IronDesk SHALL display an error to the Gym_Owner, retain the account in a pending state, and provide a resend option.
4. THE Gym_Owner SHALL be able to view a list of all staff accounts within their Tenant, including their name, role, status (active or deactivated), and last login timestamp displayed in UTC (or "Never" if the account has never been used).
5. WHEN the Gym_Owner deactivates a Receptionist account, THE IronDesk SHALL change the account status to deactivated and invalidate all active sessions for that account within 5 seconds.
6. WHEN the Gym_Owner views the Audit_Log, THE IronDesk SHALL return paginated results of up to 1,000 entries per page, filterable by staff member, action type, date range, or any combination of those filters.
7. THE Audit_Log SHALL record for each entry: user identifier, action type, affected resource identifier, timestamp in UTC, and the IP address of the request.
8. THE IronDesk SHALL retain Audit_Log entries for a minimum of 12 months and a maximum of 84 months (7 years) per Tenant.

---

### Requirement 9: Dashboard and Reports

**User Story:** As a gym owner, I want a real-time operational dashboard, so that I can monitor the health of my business at a glance without generating manual reports.

#### Acceptance Criteria

1. THE Dashboard SHALL display the following metrics on load: total active members, members with expiring memberships in the next 7 days, members currently checked in, total revenue collected for the current calendar month, and count of unpaid invoices.
2. WHEN the Dashboard is loaded, THE IronDesk SHALL render all summary metrics within 3 seconds on a standard broadband connection (25 Mbps or above).
3. WHEN the Gym_Owner requests an Attendance Report for a selected date range, THE IronDesk SHALL generate a report showing daily check-in counts and unique member counts for each day in the range.
4. WHEN the Gym_Owner requests a Membership Report for a selected date range, THE IronDesk SHALL generate a report showing the count of active, expired, and newly enrolled members for that range.
5. WHEN the Gym_Owner exports a generated Report, THE IronDesk SHALL produce a downloadable CSV file containing all data rows displayed in the report.
6. THE Receptionist SHALL have access to a simplified Dashboard view showing only: the count of members currently checked in and the list of members with memberships expiring today.
7. WHEN the Dashboard data is older than 30 seconds for currently-checked-in count, THE IronDesk SHALL refresh that metric automatically without requiring a full page reload.

---

### Requirement 10: Notifications

**User Story:** As a gym owner, I want the system to send automated alerts for important events, so that I don't have to manually monitor expiry dates or overdue payments.

#### Acceptance Criteria

1. WHEN a Member's Membership is 7 days from expiry, THE IronDesk SHALL generate an in-app Notification visible to all staff of the relevant Tenant within 1 hour of the condition becoming true.
2. WHEN a Member's Membership is 1 day from expiry, THE IronDesk SHALL generate an in-app Notification visible to all staff of the relevant Tenant within 1 hour of the condition becoming true.
3. WHEN a Member's Membership expires, THE IronDesk SHALL generate an in-app Notification visible to all staff of the relevant Tenant within 1 hour of the condition becoming true.
4. WHEN an Invoice remains unpaid 3 days after its due date, THE IronDesk SHALL generate an in-app Notification visible to the Gym_Owner within 1 hour of the condition becoming true.
5. THE IronDesk SHALL NOT generate more than one Notification per Member per expiry event type (7-day warning, 1-day warning, expiry) or per Invoice per overdue event within a single check cycle.
6. THE Gym_Owner SHALL be able to configure up to 5 designated email addresses per Tenant to receive notifications for expiry and overdue invoice events; each address SHALL be validated to be a properly formatted email (maximum 254 characters).
7. THE IronDesk SHALL display all unread Notifications in a notification panel accessible from all pages of the application; each panel entry SHALL show the notification message, the name of the triggering entity (member or invoice), and the timestamp.
8. WHEN a staff member marks a Notification as read, THE IronDesk SHALL update the read status within 2 seconds and decrement the unread notification count accordingly.
9. THE IronDesk SHALL retain Notification history for a minimum of 90 days per Tenant; Notifications older than 90 days MAY be permanently deleted.

---

### Requirement 11: Multi-Tenancy and SaaS Infrastructure

**User Story:** As the IronDesk platform operator, I want each gym to operate in full data isolation, so that no gym can access another gym's data under any circumstance.

#### Acceptance Criteria

1. THE IronDesk SHALL enforce Tenant-level data isolation such that all database queries are scoped to a single Tenant identifier and no query returns data belonging to more than one Tenant.
2. WHEN the Super_Admin submits a valid provisioning request with a gym name (1–100 characters), owner name (1–100 characters), owner email, and subscription tier, THE IronDesk SHALL create a new Tenant and a Gym_Owner account.
3. IF a provisioning request includes an owner email already registered to an existing Tenant, THEN THE IronDesk SHALL reject the request and return a duplicate email error.
4. WHEN a new Tenant is provisioned, THE IronDesk SHALL send a welcome email to the Gym_Owner containing login credentials and a one-time password reset link valid for 24 hours.
5. WHEN the Super_Admin suspends a Tenant, THE IronDesk SHALL prevent all users of that Tenant from logging in within 30 seconds of the suspension action, without deleting any Tenant data.
6. WHEN a suspended Tenant is reactivated by the Super_Admin, THE IronDesk SHALL restore login access for all users of that Tenant who were not individually deactivated prior to suspension.
7. IF a request references a resource belonging to a different Tenant, THEN THE IronDesk SHALL return an HTTP 403 authorization error and SHALL NOT expose any data from the referenced resource.
8. WHEN the Super_Admin views the platform dashboard, THE IronDesk SHALL display: total tenant count, count of non-suspended active tenants, and platform-wide count of members with non-expired Memberships — without exposing any member PII.

---

### Requirement 12: Non-Functional Requirements — Performance

**User Story:** As a receptionist, I want the system to respond quickly during busy morning check-in hours, so that the front desk queue doesn't back up.

#### Acceptance Criteria

1. THE IronDesk SHALL handle up to 100 concurrent users per Tenant with an average API response time not exceeding 2 seconds under that load.
2. WHEN a QR_Code is scanned for attendance under a load of up to 100 concurrent users per Tenant, THE IronDesk SHALL complete the check-in and display confirmation within 2 seconds.
3. WHEN a staff member loads a list view (members, invoices, attendance), THE IronDesk SHALL return the paginated result within 2 seconds for datasets up to 10,000 records per Tenant.
4. THE IronDesk SHALL support pagination on all list views, with a default page size of 50 records and a maximum page size of 200 records.
5. THE IronDesk SHALL support a platform-wide load of up to 500 concurrent users across all Tenants with an average API response time not exceeding 2 seconds.

---

### Requirement 13: Non-Functional Requirements — Security

**User Story:** As a gym owner, I want my business and member data to be protected, so that I am not exposed to data breaches or unauthorized access.

#### Acceptance Criteria

1. THE IronDesk SHALL enforce HTTPS for all client-server communication.
2. THE IronDesk SHALL store all user passwords using a memory-hard hashing algorithm (bcrypt with a minimum cost factor of 12, or Argon2id).
3. WHEN a user fails authentication 5 consecutive times within a rolling 10-minute window, THE IronDesk SHALL lock that account for 15 minutes and notify the Gym_Owner of the associated Tenant via email.
4. THE IronDesk SHALL issue session tokens with a maximum lifetime of 8 hours, requiring re-authentication after expiry.
5. IF a request is made using an expired session token, THEN THE IronDesk SHALL reject the request, return an authentication error, and NOT process the requested operation.
6. THE IronDesk SHALL validate and sanitize all user-supplied input at the API layer to prevent SQL injection, cross-site scripting, and cross-site request forgery attacks.
7. THE IronDesk SHALL NOT log personally identifiable information (names, phone numbers, emails) in application log files.
8. THE IronDesk SHALL comply with GDPR principles for data handling, including the ability for a Gym_Owner to request deletion of all Tenant data upon account termination.

---

### Requirement 14: Non-Functional Requirements — Availability and Reliability

**User Story:** As a gym owner, I want the system to be reliably available during gym operating hours, so that attendance and payments are never blocked by downtime.

#### Acceptance Criteria

1. THE IronDesk SHALL maintain a minimum uptime of 99.5% measured per calendar month, excluding scheduled maintenance windows of up to 4 hours per month.
2. THE IronDesk SHALL perform automated database backups at a minimum frequency of once every 24 hours, with backups retained for a minimum of 30 days and restorable within 4 hours.
3. WHEN a scheduled maintenance window is planned, THE IronDesk SHALL notify all active Tenants at least 24 hours in advance via email, including the scheduled start time, expected duration, and affected functionality.
4. THE IronDesk SHALL implement graceful error handling such that a failure in the notification subsystem does not affect the availability of attendance recording or billing functions.
5. IF the notification subsystem fails, THEN THE IronDesk SHALL continue processing attendance and billing operations normally, and SHALL queue failed notifications for retry within 15 minutes of the failure.

---

### Requirement 15: Non-Functional Requirements — Scalability and Maintainability

**User Story:** As the IronDesk platform operator, I want the system to scale as the number of gym tenants grows, so that onboarding new customers does not require infrastructure re-architecture.

#### Acceptance Criteria

1. THE IronDesk SHALL use a stateless API architecture so that additional application server instances can be added horizontally without configuration changes.
2. THE IronDesk SHALL use a relational database that supports row-level tenant isolation via a tenant identifier column on all primary data tables.
3. THE IronDesk SHALL separate background processing (notification dispatch, daily membership reconciliation, report generation) from the request-response path using an asynchronous task queue.
4. THE IronDesk SHALL expose application health and readiness endpoints that respond within 1 second and are suitable for use with standard container orchestration health checks.

---

### Requirement 16: Future Scope

**User Story:** As a gym owner, I want the platform to grow with my business, so that I don't have to migrate to a different system as my needs expand.

#### Acceptance Criteria

1. THE IronDesk SHALL be designed so that a member-facing mobile application can be added as a future module without requiring changes to the core tenant data model.
2. THE IronDesk SHALL be designed so that direct payment gateway integration (Stripe, Razorpay, or equivalent) can be added as a future billing enhancement without restructuring the Invoice or Payment data models.
3. THE IronDesk SHALL be designed so that biometric check-in (fingerprint reader or face recognition) can be integrated as an alternative to QR_Code scanning at the attendance recording layer.
4. THE IronDesk SHALL be designed so that SMS-based member notifications can be added as a future notification channel without modifying existing notification logic.
5. THE IronDesk SHALL be designed so that a personal trainer scheduling and session tracking module can be added as a future extension to the staff management module.
6. THE IronDesk SHALL be designed so that a subscription and billing management portal (plan upgrades, invoicing for IronDesk itself) can be layered on top of the existing multi-tenancy infrastructure.
