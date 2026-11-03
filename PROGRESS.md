# EMS implementation progress

Updated: 2026-11-03 (Asia/Kuala_Lumpur).

## Working baseline

- Next.js 16.4.3 / React 19.3 / TypeScript / Tailwind v4 / official shadcn Base UI Nova components.
- Laravel 13.39 / PHP 8.5 / Sanctum 4.3.3 / PostgreSQL; Docker Compose for local use.
- Existing login/logout, protected dashboard, administrator creation, student profiles, search/filter/pagination, edit, archive and restore.
- Baseline verification: 88 backend tests; previous desktop/mobile browser workflows verified.
- Working tree already contains uncommitted implementation work. Preserve it.
- No DigitalOcean, Nginx or HTTPS deployment configuration found in the repository. Production deployment is outside this local task.

## Constraints

- Keep Sanctum and the Next.js server-side HttpOnly-cookie integration.
- Laravel owns validation, workflows, permissions, scoped database access and audit writes.
- Use official shadcn components for UI. Preserve the delivered MatDash-style pastel workspace.
- Additive migrations only. Do not reset databases or run destructive migrations. Do not deploy to or change production data.
- UTC storage; Asia/Kuala_Lumpur display. No credentials, tokens, payroll or medical values in audit logs.
- Existing student profiles remain separate from login accounts and future enrolments.

## Dependency checklist

### Phase 1 — audit, permissions, campus and academic foundations (complete)

- [x] Inspect repository instructions, READMEs, package versions, API, auth, student module and Docker configuration.
- [x] Add multiple roles while preserving existing users and role compatibility.
- [x] Add permission mapping, campus assignments, protected user-access management and last-administrator safeguards.
- [x] Add campuses, buildings, rooms, capacity/facilities, operating hours and holidays/closures.
- [x] Add academic years, terms, programmes, subjects and classes with teacher/campus assignments.
- [x] Enforce campus scope and teacher class scope on backend reads and writes.
- [x] Add audit records for foundation/access changes without sensitive values.
- [x] Integrate real frontend CRUD, grouped navigation, scoped dashboard counts, validation and empty/error states.
- [x] Verify database/API permissions, relational constraints, regression tests, frontend checks and desktop/mobile flows.

### Phase 2 — student registration, guardians, enrolments and classes

Depends on Phase 1 campus, academic and access foundations.

- [x] Application drafts, submission, review, verified documents, approval/rejection/withdrawal, generated student numbers and guardian/student account links.
- [ ] Rich student lifecycle history, separate emergency-contact profiles and restricted health information.
- [x] Private application documents with validated uploads, scoped downloads and reviewer verification.
- [ ] Student photographs and a separate ongoing student-document archive.
- [x] Separate historical programme/class/term/campus enrolments; transfers and class changes.
- [x] Teacher-assigned-student, parent-linked-child and student-self authorization; associated frontend views.

### Phase 3 — timetables and attendance

Depends on classes, rooms, staff and enrolments.

- [ ] Recurrence and exceptions; concurrency-safe teacher/room/class conflict and capacity checks; holiday/closure checks.
- [ ] Substitutions, reschedules, cancellations, daily/weekly/print views.
- [ ] Student/staff attendance, uniqueness, correction reasons/audit, appropriate QR attendance and scoped views.

### Phase 4 — RPH/RPA, assessments and progress

- [ ] Configurable lesson/activity templates, review workflows, comments and revisions.
- [ ] Grading/rubrics/developmental milestones, private evidence, teacher notes and explicit parent publication.

### Phase 5 — finance and scholarships

- [ ] Exact money arithmetic, fees, invoices, adjustments, allocations, manual payment-proof review, receipts, reversals/refunds and statements.
- [ ] Concurrency/idempotency controls and reconciliation tests.
- [ ] Scholarships, periods/limits/eligible charges, review/renewal and non-duplicated invoice allocations.

### Phase 6 — remaining HR and payroll

- [ ] Employment/private documents, qualifications/training/expiry, shifts, leave, claims and approval workflows.
- [ ] Versioned payroll rules and immutable approved calculation snapshots; explicit salary permissions. Statutory compliance is not claimed.

### Phase 7 — inventory

- [ ] Consumables/assets, locations/suppliers, recorded stock movements, transfers/assignments/maintenance and concurrency-safe quantities.

### Phase 8 — integrated dashboards, reports, notifications and final verification

- [ ] Role dashboards for implemented modules only; defined reporting periods and reconciled metrics.
- [ ] Permission-controlled reports, CSV/print/PDF where useful, long exports via supported jobs.
- [ ] Institution/numbering/settings, English/Bahasa Melayu localization, notification preferences/templates.
- [ ] Full end-to-end workflows and deployment documentation, without production deployment.

## Resume notes

Phase 1 is complete. Phase 2 admissions, private application documents, account/guardian links and historical enrolments are implemented. Private student photos, restricted health records and richer lifecycle data remain pending. The delivered functionality, checks and limitations below are the resume baseline. Later modules remain unchecked until their full workflows are implemented and verified.


## Phase 1 delivery and verification

Implemented locally on 2026-11-02:

- Eight role codes with multiple assignments per user. Existing admin/teacher/student role values are retained in the legacy compatibility column; authorization uses role assignments and permission helpers.
- Campus staff assignments, scoped campus queries, teacher-assigned-class queries and scoped reference options. Teacher options exclude campus assignments outside the manager's scope.
- Account creation/editing, enabled status, grantable-role controls, session revocation, self-lockout and last-administrator safeguards. Centre administrators cannot grant/change administrator access.
- Campus contacts, addresses, weekly hours, buildings, rooms, facilities, capacity and holiday/closure entries.
- Academic years, contained terms, programmes, subjects and classes with room/teacher assignments.
- Server-side relationship checks, reserved unique codes, capacity/date validation and fixed parent relationships. Foundation/access writes share a PostgreSQL transaction lock.
- Atomic audit metadata for new foundation/access changes; no password/token/field values are logged. No permanent deletion APIs.
- Official shadcn forms, full detail dialogs, confirmation dialogs, filters/search/sort, pagination, responsive grouped navigation, breadcrumbs and scoped real dashboard counts.
- Existing Sanctum/cookie authentication and student-profile workflows preserved.

Verification:

- Backend: 125 tests / 512 assertions passed; Pint passed. Includes additive migration backfill and all eight login roles.
- Frontend: ESLint, TypeScript and production/Docker builds passed. Git diff whitespace check passed; environment files remain ignored.
- Isolated PostgreSQL QA: concurrent duplicate programme creation and duplicate user creation each produced one success and one field-validation rejection; concurrent room-capacity reduction/class creation preserved valid capacity.
- Playwright desktop 1440x900 and mobile 390x844: campus hours -> building/room -> year/term -> programme/subject -> multi-role staff/campus assignment -> teacher-assigned class -> calendar -> audit verified.
- Validation preserved entered values; full details and confirmation/cancel behavior verified. Final checks covered sequential facilities typing, saving, immediate details and reopening the editor with fresh server values; fixed the stale-detail refresh timing found during QA. Teacher mobile scope, campus-manager isolation and denied user administration verified.
- Existing student creation, profile, archive and restore verified. Pagination across 17 matching records, search empty state and page reset verified.
- QA backend outage retained unsaved form values and showed a retryable error. Relevant browser checks passed: page identity, meaningful content, no framework overlay, console health, screenshots and interactions.
- Browser plugin not available; used the already installed Playwright/Chromium without adding project dependencies.
- Evidence/scripts are outside source control at C:/Users/ASUS/.codex/tmp/ems-foundations-qa.

Local runtime and migration:

- Applied 2026_11_02_041126_create_campus_and_academic_foundations.php to the existing local Compose PostgreSQL database.
- Verified existing fields for the one user and one student profile were unchanged; the existing user's role was backfilled. No database resets or destructive migrations were run.
- Local app remains at http://127.0.0.1:3000; API at http://127.0.0.1:8000/api. Main Compose project: education-management-system.
- Synthetic browser fixtures live only in the separate ems-foundations-qa Compose database/volumes. QA services are stopped after checks; their volumes are retained. Do not reset or delete them without authorization.
- No production deployment or remote production data changes. No new environment variables or backend dependencies.

Remaining boundaries:

- Phase 2 and later modules above are not implemented. Class setup is complete; student enrolments, self/guardian links and scoped student views are next.
- Student profile administration remains global-administrator-only until enrolments establish campus/class scope.
- Student/guardian dashboards intentionally have no learning metrics yet. No attendance, finance, payroll, inventory or progress figures are fabricated.
- English/Bahasa Melayu localization, reports and notifications remain Phase 8 work.
- Reference selectors fail explicitly above 2,000 available records; add remote search before scaling beyond this.
- Hours support one same-day interval per weekday. Calendar entries are stored, but scheduling/holiday conflict enforcement belongs to Phase 3.
- Parent relationships cannot be moved after creation. Use replacements/deactivation to retain history.
- Account password reset and legacy student-write auditing are not yet implemented.

Next session: begin Phase 2 with additive registration/lifecycle, guardian/child and student-self links, private documents/health access, and historical enrolments. Preserve the existing profile records, use the new role/campus helpers, and add Laravel authorization/workflow tests before wiring the UI. Do not treat users.role as authoritative for new code.


## Demo seeding delivery — 2026-11-02

- Added Database\\Seeders\\DemoSeeder and wired DatabaseSeeder to it for explicit local/testing seeding. Production/staging execution is rejected; no database-reset commands are used.
- Populates the implemented modules with 3 campuses, 6 buildings, 18 rooms, 2 academic years, 6 terms, 3 programmes, 9 subjects, 18 teacher-assigned classes, 9 calendar entries, 25 accounts covering all eight roles, and 60 student profiles (54 active / 6 archived). Includes multi-role and disabled account examples.
- Uses reserved DEMO- identities and @demo.ems.test addresses. No fixtures for unimplemented enrolment/guardian links, attendance, finance, payroll or inventory workflows.
- All new demo accounts receive individual generated passwords in the private persistent storage/app/private/demo-credentials.json file. No passwords are printed by the seeder, stored in source, or added to environment files.
- Uses additive first-or-create operations, preserves existing accounts/passwords/roles/assignments and archived profiles, and serializes writes with the foundation lock. Reruns do not add duplicate audit events. Existing-email collisions never promote/reset the account.
- Records actual demo creation as demo_seeded audit events with a Demo seeder actor label and field names only. Credentials-storage failure rolls back new database records.
- Runs without Faker or Composer development dependencies in Docker. No migrations or frontend dependencies required.
- Applied to the existing local Compose database using a one-off APP_ENV=local override. Verified all existing rows were unchanged: 25 users and 60 profiles were added, yielding 26 total accounts and 61 total profiles at delivery. Foundation totals match the demo counts above; 159 seeding audit events were created.
- Verified a second PostgreSQL run via the default DatabaseSeeder changed no rows, relationships, tokens, audit events or credential-file contents.
- Backend: 130 tests / 802 assertions passed; Pint and git diff whitespace checks passed. Tests cover linked data/capacity/teacher scope, credential hashing, rerun preservation, existing-account collisions, production rejection and storage-failure rollback. Updated the earlier empty-default-seeder test to enforce generated credentials for the newly requested demo behavior.
- Browser: generated demo administrator login, populated dashboard/classes/students/audit, non-public credentials download and logout passed with no console/page errors. Screenshot/results: C:/Users/ASUS/.codex/tmp/ems-demo-seeder.
- Main local Docker services are healthy at http://127.0.0.1:3000. Existing login remains valid. No remote production changes.
- Usage and private credential retrieval are documented under README.md → Demo data. Demo administrator: admin@demo.ems.test; retrieve its generated initial password from private storage.
- Phase 2 remains next; the demo seeding task does not implement later modules.


## Automatic list filters — 2026-11-02

- Replaced both manual filter forms with shared frontend/src/components/list-filters.tsx. Students, all nine campus/academic foundation lists, and users now have no Apply filters button.
- Dropdown status/campus/sort changes navigate immediately; text search updates after a 350 ms typing pause. Enter applies immediately. Search input stays focused across result updates and supports composition/IME input.
- Changes preserve other filter values and unrelated query parameters, reset pagination to page 1, and retain scroll position. Clear resets the controls. Browser Back/Forward synchronizes controls and results.
- Pending searches are canceled on Clear, history navigation, pagination/navigation links and unmount. Newer typed values survive an earlier response arriving. Accessible status text/spinner indicates pending updates.
- Uses the installed official shadcn Input, Select, Field, Button and Spinner. Filter layouts adapt to the number of available controls.
- Frontend ESLint, TypeScript and production Docker build passed. All local Docker services are healthy; the latest frontend is running at http://127.0.0.1:3000.
- Playwright verified all 11 searchable routes: automatic search, absence of Apply buttons, focus, Clear, status/campus combinations, sorting, pagination reset, history, pending-search cancellation, IME and Enter. Desktop 1440x900 and mobile 390x844 had no horizontal page overflow, relevant console/page errors, blank screens or framework overlays.
- Browser plugin not available; used the existing Playwright/Chromium installation. Evidence/results: C:/Users/ASUS/.codex/tmp/ems-live-filters.
- README updated. No backend API, migration or dependency changes were needed. Phase 2 remains the next feature phase.

## MatDash-style workspace redesign — 2026-11-02

- Rebuilt the shared shell with white surfaces, soft slate backdrop, indigo active states, pastel metric cards, self-hosted Inter and coherent dark tokens. Uses official shadcn/Base UI components only; added Accordion, Avatar, Command, Dropdown Menu, Popover, Progress and Kbd through the official registry. No alternate UI library or backend endpoint was added.
- Grouped permission-aware navigation under Dashboards, Management, Academics and Users, with collapsible nested menus, active indicator and actual student-profile count. Facilities and Teacher Assignments use focused existing room/class views. Guardian Links and Timetables remain visibly unavailable.
- Header includes Ctrl+K navigation/record search via existing permitted APIs, URL/context campus switching, persisted EN/BM navigation/dashboard labels, light/dark mode, real audit activity with browser seen marker, account details/display settings and existing logout. Full form localization, editable profile/preferences on the server and notification delivery remain future work.
- Reconstructed the dashboard with role-aware metrics, welcome/year/programme summary, campus class/room comparison, active-class table and recent audit activity. Added paginated Campus Live Monitor. Existing API counts drive the UI; 30-second visible-tab polling and manual refresh replace any suggestion of a websocket feed. Typing/open overlays pause polling. Student/staff counts retain account-wide scope; campus selection filters room/class counts and supported lists. Selected-campus audit requests query that campus directly. Error/null states never substitute fabricated zeros.
- Preserved instant list filters and 350 ms list search. Facilities/teacher view parameters survive search, campus selection, Clear and pagination. Ctrl+K record search uses 300 ms debounce, stale-response guards and authenticated permission checks.
- Source checks: ESLint, TypeScript, native production build and final Docker frontend build passed. git diff --check passed. Only the local frontend container was recreated; backend and database remain healthy. No migration, database reset, seeding or remote deployment was performed.
- Browser method: existing Playwright Chromium was used because the Browser plugin was unavailable. Native viewports checked: 1448x1086, 1600x1200 and 390x844. Final Docker app at http://127.0.0.1:3000 verified with one main landmark, no horizontal page overflow, full desktop table fit and mobile table scrolling. Fixed the initial mobile grid overflow, desktop status-column clipping, nested main landmark and cramped welcome mini-cards. No page errors/framework overlays were observed.
- Verified administrator login/dashboard against direct Laravel metrics (55 active profiles, 15 staff, 6 classes, 10 rooms, 2 campuses), campus selection (PJ: 3 classes/5 rooms/1 campus), API-backed record search through to a student profile, Facilities/Teacher Assignments, automatic search/clear/campus filters, Campus Live Monitor, timed dashboard refresh, saved dark/EN-BM preferences, account settings, audit seen marker, mobile sidebar and profile logout. Separate teacher, finance and guardian sessions showed the expected restricted metrics/navigation and honest guardian empty state. Only test-created tokens were revoked.
- Visual review: used view_image on the generated concept and latest production browser screenshot. Compared palette/surfaces, Inter hierarchy, grouped sidebar/active marker, header controls, five-card metric row, welcome/campus split and classes/activity grid. Implemented the requested visual language faithfully; no material functional or responsive mismatches remain. The title/subtitle match the concept. Intentional copy/layout differences: actual API names, counts, capacities, timestamps and role label; more detailed class codes; explicit refresh/scope explanation; solid requested badge colors; initials in place of nonexistent avatar photos; no invented row actions, flags or ratios. API content can wrap, truncate with full title text, or extend page height. This is a MatDash-inspired implementation, not imported template code.
- Design concept: C:/Users/ASUS/.codex/generated_images/01a199fa-403b-70c1-8e88-18f26d4e97a4/exec-a89a65f0-c5f5-431e-a89c-16088ac030e0.png. Durable final browser preview: docs/dashboard-preview.png. Removed temporary QA screenshots/reference files after review; no QA scripts were added to the repository.
- npm audit --omit=dev reports 6 low/moderate advisories in the shadcn CLI/registry toolchain and its dependencies; no high/critical advisories were reported. No automatic force upgrades were applied.
- Updated root/frontend READMEs. The existing Dockerfiles continue to build the application; no new environment variables are required. Phase 2 enrolments/guardian workflows remain next, independently of this frontend redesign.

## Expanded eleven-area scope and delivery order — 2026-11-02

The user selected student admissions/enrolments as the first delivery. No payment, SMS/email, LMS or biometric providers have been selected. Existing modules remain usable; the following is the actual scope/status, not a claim that all modules are complete.

| Requested area | Available now | Remaining delivery |
| --- | --- | --- |
| Campus Management | Campus directory, buildings, rooms/facilities, academic foundations, booking requests/approvals, event reservations and planned-maintenance blocks | Inventory asset allocation and maintenance work-order management |
| Time Table Management | Class/teacher/room reference data | Conflict-safe scheduling/generator, recurring calendars, exceptions, cancellations and change notices |
| System Management | Multiple roles, scoped access, user management, session revocation, audit | Institution settings and third-party integration configuration |
| Reports | Audit event directory | Academic/operational/financial reporting, PDF/Excel exports and compliance evidence workflows |
| Scholarship Management | Not implemented | Applications, eligibility documents/scoring, approval and disbursement tracking; depends on finance |
| Dashboard | Scoped live counts, campus comparison, classes/activity, linked-record access | Attendance, enrolment and finance analytics, personalized schedules/notices/tasks as workflows ship |
| Finance Management | Explicit finance role and campus scope | Billing/invoices/allocations/receipts, payments, budgets, expenses and payroll integration |
| Inventory Management | Room facilities catalogue | Asset movements/checkouts/maintenance, stock, suppliers, requisitions, purchasing and delivery |
| Progress Management | Class/programme/subject and student enrolment foundations | Gradebook, assessments, published report cards, lesson plans and learning analytics |
| Student Management | Profiles, admissions/review/private evidence, guardian/self links, historical enrolments/transfers | Rich lifecycle, health/emergency records, photographs, attendance and public applicant account onboarding |
| HR | Explicit HR access, staff accounts/campuses | Staff attendance, leave/balances, appraisals, professional development, claims and versioned payroll |

Dependency order: admissions/enrolments and linked records → bookings/timetables/attendance → gradebook/progress → finance/scholarships → HR/payroll and inventory/procurement → consolidated reports, dashboards and provider integrations. Providers stay unconfigured until selected; no live charges, messages, LMS sync or biometric connections are assumed.

## Admissions/enrolments delivery — completed 2026-11-03

- Added application drafts, submission, scoped review, document verification, final approve/reject and pre-review withdrawal. Approval creates one generated student number/profile atomically; enrolment and identity grants remain separate.
- Private PDF/JPEG/PNG evidence accepts at most ten 5 MB files, downloads through authorized attachment responses, and keeps storage paths out of API payloads. Docker PHP and Next action-body limits now support the advertised size.
- Added historical class/programme/term/campus enrolments, inclusive date boundaries, programme-overlap checks, peak capacity, completion/withdrawal and atomic transfers. Class-capacity/term edits and profile archival cannot invalidate existing placements.
- Added guardian relationships/emergency flags, unique active student self links, reversible access revocation and retained self-link history. Teachers see current assigned learners without private contacts/reasons; families see only linked profiles. Existing global profile editing remains separate.
- Added Admissions, Enrolments, Learning Records and Guardian Links pages using existing official shadcn components. Preserved automatic list filters, added scoped quick search and existing audit activity links. No new application dependencies, external providers or public signup flow.
- Verification: 148 backend tests / 934 assertions; new learning coverage also passed on isolated PostgreSQL (18 tests / 132 assertions). Pint, ESLint, TypeScript and Docker builds passed. Browser checks covered application → upload → verify → approve → enrol → campus transfer, private download, multiple guardian children, self/teacher scopes, self-link revocation, automatic status filters, conditional completion fields, desktop 1440x1000 and mobile 390x844. No page errors or horizontal page overflow observed. Browser plugin unavailable; used existing Playwright Chromium.
- Applied the two additive migrations to the main local Compose database, with no resets. Fingerprints of all existing users, profiles and foundation tables were unchanged. Added 4 demo applications, 4 enrolments, 4 guardian links and 4 self links using existing demo accounts; their credentials and records were preserved. The seeder is local/testing-only and idempotent; reruns preserve decisions/revocations. No fake private files were seeded.
- Main app smoke checks at http://127.0.0.1:3000 passed for populated admissions/enrolments and admissions quick search. All three Docker services are healthy. Preview: docs/enrolments-preview.png. QA writes were isolated from working records. No remote production changes.
- Remaining student work: richer lifecycle history, separate emergency/health records, photographs/ongoing student-document archive and public applicant onboarding. The eleven-area roadmap above remains authoritative for later modules.

## Campus booking portal delivery — 2026-11-03

- Added Facilities & Bookings using existing rooms and campuses for facility reservations, campus events and planned-maintenance blocks. Supports requests, manager approval/decline, confirmation, per-occurrence rescheduling/cancellation, completion and retained room/time/status history with reasons. Up to twelve weekly occurrences are created atomically.
- Confirmed intervals use half-open overlap checks, so back-to-back events are allowed. All booking writes share the existing PostgreSQL transaction lock with access/foundation/enrolment writes. Rechecks cover campus scope, active catalogues, room capacity, operating hours, closures and future dates. Room/campus/hour/closure edits cannot invalidate confirmed reservations. Planned maintenance may reserve inactive rooms outside opening hours/closures for up to seven days, within an active campus.
- Staff request within their campus assignments. Linked students/guardians request within current enrolment campuses. Non-manager users see other confirmed slots as Reserved, with names/purposes/requesters/reasons/history hidden; title searches include their own records only. Managers review within their assigned campuses; global administrators retain global scope. Requests do not reserve the room before approval.
- Added permission-checked APIs, official shadcn portal/detail/forms, immediate period/status/type/facility/campus filters, scoped sidebar links and existing audit activity integration. Extended the shared form for datetime-local inputs without adding another component system or runtime dependency.
- Tests: 158 backend tests / 1,001 assertions passed. Booking coverage passed separately on isolated PostgreSQL: 10 tests / 67 assertions. Covers scope, private-field/search masking, approval conflicts, adjacent intervals, capacity/hours/closures, maintenance exceptions, weekly rollback, rescheduling/history, family scope, catalogue safeguards and idempotent/production-rejected demo seeding. Pint, ESLint, TypeScript, Docker builds and git diff whitespace checks passed. Parallel HTTP load tests were not performed; the database transaction lock serializes application writes.
- Browser verification used existing Playwright Chromium because the Browser plugin was unavailable. Checked teacher request → admin approval → reschedule → cancellation reflected for the owner, weekly request creation, retained history, automatic maintenance filters, no Apply button, desktop 1440x1000 and mobile 390x844. No page errors or horizontal page overflow. Visually inspected detail and installed portal screenshots; preserved the existing palette, typography, sidebar and card/table components. Preview: docs/bookings-preview.png.
- Applied the additive booking/history migration to the main local database and added three reserved demo examples (request, event, maintenance). Fingerprints of existing user/profile/foundation/admissions/enrolment/link records were unchanged. Existing credentials were preserved; no remote production changes or database resets. Main app at http://127.0.0.1:3000 is healthy, including the installed booking portal and logout.
- README documents workflow, time/capacity/recurrence limits, permissions and demo usage. Temporary native test servers were stopped; QA writes were isolated. The isolated PostgreSQL database ems_learning_qa_20261102 and private SQLite QA data remain separate from the working database.
- Next: class timetable sessions/weekly calendars and automatic conflict-free proposals that also consider teachers and enrolled students. The current portal reserves facilities only; timetable generation, live change notifications, inventory asset allocation and maintenance work-order assignment/costing are still pending. The remaining eleven-area roadmap is not marked complete.

## Redis, JWT and Garage integration — 2026-11-03

- Added Redis 8.2, Garage 2.5 and a persistent Redis worker to Compose, with authenticated internal Redis, private S3 storage, health checks and persistent volumes. Redis uses AOF/every-second fsync, a 256 MB limit and noeviction. Backend Docker now includes pcntl for the worker's 90-second timeout (retry interval 120 seconds). Debian package sources use HTTPS after the environment blocked HTTP package retrieval.
- Redis is now the running backend's cache/rate-limit, session and queue driver. Existing business endpoints remain synchronous unless explicitly dispatching a job. The worker integration is verified with a short-lived queued probe, not a claimed email/SMS integration.
- Added fixed-HS256 JWT API login/refresh alongside existing Sanctum browser authentication. Default access lifetime is 15 minutes, with revocable database sessions, strict signature/issuer/audience/time validation, no personal/role claims, shared login throttling and separate refresh throttling. Refresh rotates/revokes the prior token and retains an absolute eight-hour session cap. Current database account/role/campus rules still apply. Existing account/session revocations also revoke JWT access.
- New admission evidence defaults to private Garage storage in Compose. An additive disk column records each document's location; existing rows default to local. Downloads retain authorization, attachment/no-store/nosniff responses and hidden storage metadata. Default filesystem/demo credentials stay local. No existing files were moved and no evidence files were seeded.
- Added idempotent secret setup via php docker/setup-infrastructure.php (and a host-side Artisan wrapper), with private atomic .env writes, no secret output and preservation of nonempty existing keys/passwords. Generated only missing local infrastructure secrets; existing application/database/demo credentials were preserved. Updated root/backend READMEs and environment examples, including Docker-only setup with host UID ownership.
- Another project's Garage already used host port 3900, so EMS binds only 127.0.0.1:13900. Its existing Redis/Garage services were left untouched. EMS Redis has no host port; Garage RPC/admin interfaces remain internal. This is a single-node local integration, not a highly available or remote production deployment.
- Verification: 169 backend tests / 1,111 assertions passed; two real-service tests skip by default. All 13 infrastructure/JWT tests passed on isolated PostgreSQL ems_learning_qa_20261102 with real Redis/Garage/worker (118 assertions), including actual admission upload, byte-exact authorized download, anonymous S3 denial and cross-account denial. Temporary objects/cache keys were removed. Pint, Composer validation/audit (no advisories), Docker build and Compose configuration checks passed.
- Applied the additive document migration to the working local database without reset/reseeding. Fingerprints of all 19 checked business tables remained identical (document comparison excludes only the newly added disk metadata). The previous database queue was empty before driver cutover. All six Compose services are healthy; the installed diagnostic reports Redis cache, private Garage storage and worker PASS.
- Real HTTP JWT checks passed: login/user 200, refresh 200, old token 401, replacement 200, logout 204, revoked replacement 401. Browser smoke checks on the installed app verified the same pre-upgrade Sanctum cookie still worked after both backend restarts, dashboard still showed 55 active students, admissions/enrolments/bookings retained their existing rows, and logout worked. No page errors or horizontal overflow were observed. Existing Playwright Chromium was used because the Browser plugin was unavailable. No frontend source changes required a frontend rebuild in this delivery.
- The eleven-area functional roadmap remains unchanged. Timetable generation, attendance, finance, scholarships, inventory and HR workflows are not marked complete by this infrastructure work.
