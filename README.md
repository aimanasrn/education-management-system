# Education Management System

Laravel API + Next.js frontend + PostgreSQL, with Redis, JWT API access and private Garage object storage. Includes student profiles, admissions/enrolments, multiple roles, campus management and academic foundations. See [PROGRESS.md](PROGRESS.md) for the phased implementation checklist and remaining modules.

## Current features

- Eight-hour Laravel Sanctum tokens stored in HttpOnly, SameSite cookies by Next.js Server Actions. Browser JavaScript never receives the token.
- Eight supported roles with multiple roles per account; Laravel enforces permissions, assigned-campus scope and teacher class scope.
- Login validation, throttling, accessible errors, pending states, and responsive pages.
- Official shadcn/ui components across the frontend, including forms, cards, navigation, feedback, loading states, and empty states.
- Interactive administrator creation with no default credentials.
- Student profiles with unique student numbers, contact details, and enrollment dates; searchable/paginated lists; active/archived filters; profile editing; and reversible archive/restore actions.
- Backend/frontend Dockerfiles, persistent PostgreSQL/Redis/Garage storage, a queue worker, and service health checks.
- Redis-backed cache, login throttling, sessions and queued jobs; optional short-lived JWT API authentication alongside the existing browser login.
- Campus contacts, operating hours, buildings, rooms/capacity/facilities and holiday/closure entries.
- Academic years, terms, programmes, subjects and classes with assigned teachers.
- Protected user creation, role/campus assignments, account disabling, session revocation and administrator safeguards.
- Role-aware dashboards with real scoped counts, grouped navigation, record details and field-name-only audit logs.

Timetables, attendance, progress, finance, HR/payroll, inventory, reporting exports and scholarships remain future deliveries. Facilities booking, campus events and planned maintenance are now available. Admissions, guardian/self links and historical enrolments are now available. No unfinished module is presented as operational.

## Campus and academic foundations

Use **Management**, **Academics** and **Users** in the sidebar. Start with a campus, buildings/rooms, an academic year and terms, then programmes/subjects. In **Staff & User Management**, create staff accounts and assign their roles and campuses before assigning teachers to classes. Account passwords require at least 12 characters and at most 72 bytes. Optional local demo seeding creates accounts with generated passwords, described below.

System and centre administrators manage the global catalogues and student profiles. Only system administrators can create or change administrator access. Campus managers manage their assigned campuses, facilities, calendars and classes; they read global academic reference data. Teachers read assigned-campus facilities and only their assigned classes. HR/finance roles grant explicit module permissions and assigned-campus access; their private workflows are not implemented yet. Student and guardian accounts open Learning Records for their linked profiles and enrolment history. Multiple roles combine permissions; campus scope still applies unless the account is a global administrator.

Lists support search, status filters, sorting and pagination; campus-linked lists also filter by campus. Dropdown filters apply immediately, and search updates after a 350 ms typing pause; no Apply button is needed. Filter changes reset pagination to page 1, preserve other selections, and support Clear and browser Back/Forward navigation. **View** shows full details, including operating hours and facilities. **Edit** updates records; **Inactive** preserves history and requires confirmation. There are no permanent deletion endpoints. Parent relationships are fixed after creation; create a replacement record instead of silently moving historical data to a different campus/year/programme.

Laravel validates matching campus/building/room, year/term and programme/subject relationships, term boundaries, room/class capacity, and teacher role/campus assignments. A room cannot shrink below an existing class capacity. Reassign classes before removing a teacher's role or campus. Hours represent one interval per day in Asia/Kuala_Lumpur; closed days can remain unconfigured. This phase stores calendar entries; scheduling/conflict enforcement comes with timetables.

Account access changes end affected sessions. Self-disable/self-demotion is blocked, at least one active system administrator is required, and centre administrators cannot grant themselves higher access. Audit logs record actor, time, record reference, action and changed field names without passwords, tokens or field values. They cover the new foundation/access writes; legacy student change auditing is still pending.

Foundation and access writes share a transaction lock in PostgreSQL, so capacity checks, teacher assignments, unique codes and role/campus changes cannot race each other through these APIs. This intentionally serializes low-volume setup changes; revisit lock granularity if write volume grows. List pages are paginated. Reference selectors currently support up to 2,000 available records and fail explicitly above that limit; searchable remote selectors are a future scaling improvement.

Dashboard counts are point-in-time active records within the user's scope. Staff counts mean enabled accounts with at least one staff role, counted once. Student counts mean non-archived profiles, separate from login accounts. Attendance and financial totals are omitted until their source workflows exist. Timestamps are stored in UTC and displayed in Kuala Lumpur time. Navigation and dashboard labels support English/Bahasa Melayu; full workflow/form localization remains a later phase.

## Dashboard and workspace

[Dashboard preview](docs/dashboard-preview.png).

The MatDash-inspired workspace uses the existing official shadcn/ui system, Inter, a soft slate backdrop, white cards and pastel metric badges. The grouped sidebar contains collapsible academic and room menus. Header controls provide Ctrl+K quick search, campus selection, EN/BM labels, persistent light/dark preferences, recent audit activity and account information/logout.

The dashboard displays API-backed active students, staff, classes, rooms and campuses according to permissions. It includes campus comparison bars, the active academic year/programme count where available, active classes with assigned teachers, and administrator audit activity. Campus Live Monitor provides a paginated campus comparison. Dashboard data refreshes every 30 seconds while visible, and on returning to the tab; refresh pauses while typing or an overlay is open. Refresh also runs on demand. This uses polling of the current Laravel endpoints.

Campus selection applies to dashboard room/class counts and supported building, room, closure and class lists. Student and staff totals remain account-wide. Global catalogues and student profiles retain their existing scope. Selecting a campus from a page without a campus filter opens its dashboard. Teacher class scope and all role permissions remain enforced by Laravel. Unavailable responses show an error or unavailable value rather than a fabricated zero.

Quick search debounces record queries by 300 ms and searches only permitted modules; ordinary list searches retain their 350 ms delay. Facilities and Teacher Assignments are focused views of existing room/class records. The bell shows existing audit activity, with a per-account seen marker stored in this browser. It does not represent a notification-delivery service. Account Settings displays current account access and browser display preferences; account editing remains in user management. Language preferences apply to navigation and dashboard labels, not all existing forms.

Timetables are clearly marked Coming soon. Guardian/student links are available to authorized administrators. No attendance, financial or notification data is invented. Inter is self-hosted under its bundled [SIL Open Font License](frontend/public/fonts/INTER-LICENSE.txt). The dashboard redesign does not change the Laravel API or require migrations.

## Campus bookings, events and planned maintenance

[Booking portal preview](docs/bookings-preview.png).

Use **Management → Facilities & Bookings** to request lecture halls, laboratories, sports spaces, auditoriums or other rooms already registered in the room catalogue. The portal uses the existing rooms/facilities; it does not allocate separate inventory assets yet. Filter by period, status, type, facility and campus; controls update immediately without an Apply button.

Staff request facilities within assigned campuses. Linked students/guardians can request rooms in their current enrolment campuses. Requests remain requested until a campus manager or global administrator approves them. A manager-created reservation is confirmed immediately. Approval checks availability again. Non-managers can see other confirmed time blocks as Reserved; private titles, purposes, requesters, decisions and history remain restricted to the owner and managers. Searching by title searches only the requester's own records unless the actor is a booking manager.

Confirmed room intervals cannot overlap. An event ending at 11:00 and another beginning at 11:00 are allowed. The booking writes, approvals, reschedules and campus catalogue writes share the existing transaction lock. Attendance must fit room capacity. Ordinary bookings/events use active rooms and campuses, fit one Kuala Lumpur day, last no more than twelve hours, and respect operating hours and closures. Planned maintenance can block an inactive room, run outside operating hours/closures and last up to seven days, while its campus remains active. Start dates must be future and within the next year.

Choose 1–12 weekly occurrences when creating a request/reservation. The series saves atomically: one invalid/conflicting occurrence rejects the whole creation. Review, edit and cancellation act on one occurrence. Every decision/change needs a reason. A requested booking can be edited by its owner; managers can edit future requested/confirmed reservations. Confirmed reservations can be cancelled by owners or managers; records and old room/time/status history are retained. Managers mark completed only after the end time. Declined, cancelled and completed records are final. Catalogue changes cannot shrink/deactivate booked rooms, close booked campuses, or add conflicting closures/hours without first resolving confirmed reservations.

This is facility reservation and maintenance scheduling. Automated class timetables, teacher/student timetable conflict checks, live change notices, maintenance work-order assignment/costs and inventory-asset allocation remain subsequent work. No payment, email, SMS, LMS or biometric provider is configured.

Optional additive examples after the base demo seeder:

```bash
docker compose exec -e APP_ENV=local backend php artisan db:seed --class=CampusBookingSeeder
```

It adds a requested facility reservation, confirmed event and confirmed planned-maintenance block for the next Monday. Reserved demo references prevent duplicates and preserve decisions on rerun. The seeder only runs in local/testing environments.

## Admissions, enrolments and linked access

Use **Users → Admissions** to create an application draft on behalf of an applicant, or let an existing student/guardian account create its own draft. There is no public signup endpoint in this delivery; staff create login accounts through user management. Drafts can be edited and receive up to ten private PDF/JPEG/PNG documents, each at most 5 MB. Submit locks editing. A campus-scoped reviewer starts review, verifies every attached document, then approves or rejects with a reason. No mandatory document policy is assumed: applications without attachments can be reviewed. Withdraw is available before review. Approval/rejection are final. Approval creates one student profile and generated STU- student number exactly once; it does not automatically grant account access or enrol the student.

Use **Enrolments** to place an active student profile into an active class with dates contained in its term. The class defines campus, programme, year and term. Overlapping placements in the same programme are rejected. Different programmes may overlap. Capacity uses the highest simultaneous occupancy, not the sum of placements in different date ranges. Dates include both endpoints. Transfers shorten the original placement through the day before the new placement, retain its reason/status, and link the new record to it. A full/invalid destination rolls the whole transfer back. Completion/withdrawal includes the selected effective date. Ended records cannot be edited again. An active status means not manually closed; dates determine whether the placement is current. Class capacity and term edits cannot invalidate existing enrolments. End current/future enrolments before archiving a profile.

Use **Guardian Links** to bind verified existing login accounts to profiles. Multiple active guardian/child links are supported, with relationship and emergency-contact flag. This flag does not replace a full emergency-contact/health record. A student account has one active self link, and a profile has one active student account. Save the same pair as inactive to revoke access before correcting a self identity; revoked self links retain history. Account disabling, role removal and link revocation take effect on subsequent requests. Only global administrators grant these identity links.

**Learning Records** shows full contacts only to authorized managers, the linked student or an active linked guardian. Teachers see only currently enrolled students in their assigned classes, without personal contact data or private withdrawal reasons. Campus managers see current enrolments within assigned campuses and their approved, not-yet-placed admissions; future placements become current on their start date. Historical enrolments follow the same class/campus or family/self scope. Existing administrator profile editing stays separate. Applicants access only their own applications unless they have campus reviewer permissions. Private evidence downloads require application access, use attachment responses and do not expose storage paths.

The Docker backend accepts 5 MB file uploads, and Next.js permits 6 MB action bodies including multipart overhead. Native PHP installations must set upload_max_filesize=5M and post_max_size=6M to match. New private admission files use Garage in Docker; existing local documents retain their recorded storage location. Private student photos/health data, richer lifecycle statuses and public applicant onboarding remain follow-up work.

Optional additional demo data, after the base DemoSeeder and migrations, uses the existing demo accounts and preserves their passwords/records:

```bash
docker compose exec -e APP_ENV=local backend php artisan db:seed --class=LearningDemoSeeder
```

This adds four reserved demo applications, four class enrolments and guardian/self links. Reruns preserve changed decisions and revoked links. It never seeds document files or runs in production/staging. Never run database-reset commands to install these modules.

## Student management

Sign in as an administrator and choose **Student Profiles** in the sidebar. Add a student, search by name/student number/email, open their profile, edit their details, or archive/restore the record. Lists default to active profiles and show 15 records per page. Status filters also support archived records and all records.

Student number, full name, and enrollment date are required; email and phone are optional. Student numbers are normalized to uppercase and remain reserved when a record is archived. Archived records can be viewed and restored; restore a profile before editing it.

Student profiles are separate from login accounts. Adding or archiving a profile does not create, disable, or change a login account. The dashboard's student-profile count excludes archived profiles. Student-profile editing remains restricted to global administrators. The separate Learning Records directory grants scoped read access through current enrolments, student self links and active guardian links.

## Docker quick start

Install Docker Engine/Desktop with Docker Compose. On Windows, run these commands from this repository in your **WSL Ubuntu terminal**, with Docker Desktop's WSL integration enabled.

1. Generate the local environment file and secrets:

   ```bash
   php docker/setup-infrastructure.php
   ```

   If host PHP is unavailable, use Docker instead:

   ```bash
   docker run --rm --user "$(id -u):$(id -g)" -v "$PWD":/workspace -w /workspace php:8.5-cli php docker/setup-infrastructure.php
   ```

   Setup creates root `.env` from `.env.example` if needed, fills missing secrets and preserves existing nonempty values, including `APP_KEY` and `DB_PASSWORD`. The file is private and credentials are never printed. Keep `.env` out of Git. Rerunning setup does not rotate existing keys.

2. Build and start infrastructure, then migrate:

   ```bash
   docker compose build
   docker compose up -d --wait database redis garage
   docker compose run --rm --no-deps backend php artisan migrate --force --no-interaction
   ```

3. For a new installation, create an administrator:

   ```bash
   docker compose run --rm --no-deps backend php artisan app:create-admin admin@example.com --name="School Administrator"
   ```

   The command prompts for a password and confirmation. Use at least 12 characters, up to 72 bytes for bcrypt. Existing emails are rejected, never overwritten or promoted. Existing installations keep their accounts and skip this step.

4. Start the app and verify integrations:

   ```bash
   docker compose up -d --wait
   docker compose exec backend php artisan ems:check-infrastructure --queue
   ```

   The diagnostic writes and removes unique temporary cache keys and objects, verifies anonymous downloads are denied, and dispatches a short-lived worker job. It does not alter business records or flush Redis.

Open [http://127.0.0.1:3000](http://127.0.0.1:3000). API health: [http://127.0.0.1:8000/api/health](http://127.0.0.1:8000/api/health). Keep the hostname consistent while signing in and out.

### Updating an existing installation

Run setup first to add missing infrastructure secrets while preserving current keys/passwords. Then build, start infrastructure, migrate and restart as shown above. Do not run database-reset commands. The storage migration is additive: old document rows default to `local`; subsequent uploads use the configured disk. Downloads follow each record's disk, so no file move or reseeding is needed. Before changing queue drivers, inspect the old database queue and process any known pending jobs through the previous worker.

Useful commands:

```bash
docker compose ps
docker compose logs -f backend worker redis garage
docker compose exec backend php artisan ems:check-infrastructure --queue
docker compose down
```

`docker compose down` preserves volumes; adding `--volumes` erases them. Changing `DB_PASSWORD` in `.env` alone does not rotate an initialized PostgreSQL role's password. Redis/Garage credentials also need coordinated service/key changes; replacing secrets on existing volumes is not a reset procedure.

### Redis and background jobs

Compose uses Redis 8.2 with Predis for cache, login rate limits, sessions and queued jobs. Redis is authenticated and internal to Docker; it does not bind host port 6379. AOF persistence uses every-second fsync, a 256 MB memory limit and `noeviction`, so cache pressure cannot silently discard queued jobs. Monitor memory and increase capacity as needed. Laravel retains its separate cache/default Redis databases and prefixes.

The `worker` processes Redis's default queue with three attempts, a 90-second timeout, a 120-second retry interval and hourly recycling. PHP `pcntl` enforces job timeouts. Restart workers after code changes (`docker compose up -d --build --wait backend worker`). Failed jobs use the database failed-job store; inspect with `docker compose exec backend php artisan queue:failed`. Business workflows remain synchronous unless explicitly dispatching jobs. No email/SMS delivery provider is configured.

### Private Garage storage

Garage 2.5 supplies the private S3-compatible `ems-private` bucket. Its S3 API is **http://127.0.0.1:13900**, not a web dashboard. Port 13900 avoids another local project's port 3900. Garage RPC/admin interfaces and PostgreSQL remain internal. The backend uses `http://garage:3900`, path-style requests and region `garage`.

New admission evidence uses `DOCUMENT_STORAGE_DISK=garage` in Compose. Downloads pass through Laravel authorization and return private attachments; paths/keys stay out of API responses. Files marked `local` keep working. Setting `DOCUMENT_STORAGE_DISK=local` affects future uploads only. Demo credentials and other existing private files remain on the backend volume. Garage metadata, objects and metadata snapshots have separate persistent volumes. This single-node local deployment is not highly available; metadata snapshots alone do not back up objects. Back up PostgreSQL, backend storage and all Garage volumes together.

Compose uses production builds for **local HTTP testing**. Published ports bind to `127.0.0.1`. Change `FRONTEND_PORT`, `BACKEND_PORT` or `GARAGE_PORT` in root `.env` if needed. Before public hosting, configure HTTPS, `COOKIE_SECURE=true`, deployed URLs, secret management, backups and migrations.

Implementation references: [Laravel Redis](https://laravel.com/docs/13.x/redis), [Laravel filesystems](https://laravel.com/docs/13.x/filesystem), [JWT introduction](https://jwt.io/introduction), and [Garage quick start](https://garagehq.deuxfleurs.fr/documentation/quick-start/).

## Native development

Requirements: PHP 8.5 (PostgreSQL, mbstring, XML, and SQLite extensions), Composer 2, Node.js 24, npm, and PostgreSQL. SQLite is used only by tests. Dependencies are locked in `composer.lock` and `package-lock.json`.

### Backend

```bash
cd backend
composer install
cp .env.example .env
php artisan key:generate
```

Configure `backend/.env` for an existing PostgreSQL database and user:

```dotenv
DB_CONNECTION=pgsql
DB_HOST=127.0.0.1
DB_PORT=5432
DB_DATABASE=education_management
DB_USERNAME=ems
DB_PASSWORD=your-local-database-password
```

Then:

```bash
php artisan migrate
php artisan app:create-admin admin@example.com --name="School Administrator"
php artisan serve --host=127.0.0.1 --port=8000
```

The foundation migration preserves existing users and backfills their current role into role assignments. It adds tables and an enabled flag without resetting existing data. No existing account is promoted automatically. The default seeder calls the local/testing-only demo seeder. Never run database-reset commands against an existing installation.

### Frontend

In another terminal:

```bash
cd frontend
npm ci
cp .env.example .env.local
npm run dev -- --hostname 127.0.0.1
```

Open [http://127.0.0.1:3000](http://127.0.0.1:3000). Keep the browser hostname consistent while signing in and out.

`LARAVEL_API_URL` is server-only and includes `/api`: `http://127.0.0.1:8000/api` natively, `http://backend/api` in Docker. Never prefix it with `NEXT_PUBLIC_`. Use `COOKIE_SECURE=false` only for local HTTP; production defaults to secure cookies if this variable is omitted.

The root `.env` configures Compose. Native development uses `backend/.env` and `frontend/.env.local` separately.

## Demo data

The linked `DemoSeeder` fills all currently implemented modules with synthetic data:

| Records | Demo count |
| --- | ---: |
| Campuses / buildings / rooms | 3 / 6 / 18 |
| Academic years / terms | 2 / 6 |
| Programmes / subjects / classes | 3 / 9 / 18 |
| Holidays and closures | 9 |
| Accounts covering all eight roles, including multiple-role and disabled examples | 25 |
| Student profiles, including 6 archived | 60 |

Records use reserved `DEMO-` codes and `@demo.ems.test` addresses. Years are example 2026/2027 data; calendar entries are illustrative, not an official holiday calendar. Classes have valid rooms, capacities, subjects, terms and teacher/campus assignments. Student and guardian accounts are still separate from profiles; enrolment and child links belong to the next phase. No data is invented for unimplemented attendance, finance, payroll or inventory modules.

For the existing **local Docker stack**, build and seed without resetting the database:

```bash
docker compose build backend
docker compose run --rm -T -e APP_ENV=local backend php artisan db:seed --class=DemoSeeder --no-interaction
docker compose up -d --wait
```

The one-off `APP_ENV=local` override is needed because the supplied local Compose image normally uses production mode. The seeder rejects production/staging environments even with `--force`. Do not use this command against a remote production database.

For native development with `APP_ENV=local`, run `php artisan db:seed` from `backend/`. The default `DatabaseSeeder` calls the same demo seeder.

Sign in with `admin@demo.ems.test` to explore all screens. Each newly created demo account gets its own random password. View the private initial-credentials file locally:

```bash
docker compose exec backend cat storage/app/private/demo-credentials.json
```

The file lives in the persistent backend storage volume and is not logged, committed or publicly downloadable. It lists initial passwords only; later password changes are preserved. If an email already exists, that account is not reset, promoted or added to campuses, and no new credentials are issued for it.

Reruns add missing records without duplicating the dataset or changing existing records, passwords, assignments or archived status. Seeding uses a transaction and the foundation access lock. Audit events describe only records actually created, with field names rather than personal values. If private credentials cannot be written, new database records roll back. No Faker/development dependency is needed in the runtime image.

## Verification

```bash
cd backend
php artisan test --compact
vendor/bin/pint --dirty --format agent
```

By default, tests use in-memory SQLite, separate from PostgreSQL data. Two live Redis/Garage tests are skipped unless explicitly enabled against an isolated QA PostgreSQL database whose name contains `_qa_`. Coverage includes authentication, student management, additive role backfill, multi-role permissions, campus and teacher scope, access safeguards, audit privacy, relational validation and capacity/date rules. PostgreSQL concurrency and desktop/mobile browser workflows are also verified separately with synthetic data; see PROGRESS.md.

```bash
cd frontend
npm run lint
npm run build
```

Runtime Docker images omit development dependencies. Run PHP tests from a development checkout with Composer development dependencies installed.

## API

Send `Accept: application/json`. Protected endpoints require `Authorization: Bearer <token>`.

| Method | Path | Access |
| --- | --- | --- |
| GET | `/api/health` | Public |
| POST | `/api/login` | Public; email/password; throttled |
| POST | `/api/jwt/login` | Public; email/password; shared login throttling |
| POST | `/api/jwt/refresh` | Current unexpired JWT only; rotates/revokes old token |
| GET | `/api/user` | Authenticated |
| POST | `/api/logout` | Authenticated; revokes current token |
| GET | `/api/admin/dashboard` | Admin only |
| GET | `/api/dashboard` | Authenticated; scoped active-record metrics |
| GET | `/api/foundation/{resource}` | Resource view permission; scoped/search/filter/sort/paginate |
| GET | `/api/foundation/{resource}/options` | Resource view permission; scoped references |
| GET | `/api/foundation/{resource}/{id}` | Resource view permission and record scope |
| POST | `/api/foundation/{resource}` | Resource manage permission; campus creation is global-admin only |
| PUT | `/api/foundation/{resource}/{id}` | Resource manage permission and record scope |
| GET/POST | `/api/system/users` | Global administrators; list/create users |
| GET | `/api/system/users/options` | Global administrators; grantable roles and campuses |
| PUT | `/api/system/users/{id}` | Global administrators, with administrator safeguards |
| GET | `/api/system/audit` | Global administrators; paginated audit metadata |
| GET | `/api/students` | Admin only; search/filter/paginate |
| POST | `/api/students` | Admin only; create profile |
| GET | `/api/students/{id}` | Admin only; active or archived profile |
| PUT | `/api/students/{id}` | Admin only; update active profile |
| POST | `/api/students/{id}/archive` | Admin only; reversible archive |
| POST | `/api/students/{id}/restore` | Admin only; restore profile |

Student list query parameters: `q` (up to 100 characters), `status` (`active`, `archived`, or `all`), `page`, and `per_page` (1–100; default 15). Responses include `data` and pagination `meta`. Search is case insensitive and treats wildcard characters literally. Create/update fields: `student_number`, `full_name`, optional `email`/`phone`, and `enrollment_date` (`YYYY-MM-DD`). Editing an archived profile returns `409`. Archive and restore are idempotent; there is no permanent deletion endpoint.

Login returns `{ user, token, expires_at }`. User/dashboard responses wrap their payload in `data`. Missing/expired authentication returns `401`, insufficient roles `403`, invalid credentials `422`, and throttling `429`.

Next.js calls Laravel on behalf of the browser. Server Actions apply origin checks; Laravel revalidates authorization. Account data is not cached across users. Logout revokes only the current token, keeping other devices signed in. If the backend is unavailable, logout reports an error so it can be retried.

Login permits five attempts per account and sixty per backend-visible IP each minute. Because Next.js is the API caller, its users share the IP limit; account limits are separate. Review this policy when scaling. Expired token records can be cleaned up periodically with `php artisan sanctum:prune-expired`.

### JWT API clients

Next.js keeps its eight-hour opaque Sanctum token in an HttpOnly cookie. JWT adds API-client authentication; protected routes accept either format with the same live account, role and campus checks.

`POST /api/jwt/login` accepts email/password JSON and returns `{ user, token, token_type, expires_in, expires_at }`. Send the JWT as `Authorization: Bearer <token>`. Access defaults to 15 minutes (`JWT_TTL_MINUTES`, clamped to 1–60). Before expiry, `POST /api/jwt/refresh` returns a replacement and immediately revokes the previous token. Refresh preserves an absolute eight-hour limit from password authentication, after which login is required. Expired tokens cannot refresh. Refresh permits 20 requests per user and 60 per backend-visible IP per minute.

Fixed HS256 signing uses a separate generated `JWT_SECRET`, with validated issuer/audience, timing claims and a database session ID. Claims contain no names, email addresses or roles. Signed payloads are readable, not encrypted. Database sessions allow immediate logout, disabling and access-change revocation; authenticated requests intentionally query the database. `POST /api/logout` revokes the current token only. Store client tokens securely; never paste secrets/tokens into public JWT debuggers.

Native Laravel development retains database cache/session/queue and local-document defaults. To use these integrations outside Compose, configure `backend/.env` Redis host/password, JWT settings and Garage keys/bucket/region/endpoint explicitly. Setup prepares root Compose `.env`; it does not copy secrets into `backend/.env`.

## Structure

Foundation resource keys are `campuses`, `buildings`, `rooms`, `closures`, `years`, `terms`, `programmes`, `subjects` and `classes`. List queries accept `q` (100 characters), `status` (`all`, `active`, `inactive`), `sort` (`name`, `-name`, `newest`), `page`, `per_page` (1–100), and `campus_id` for campus-linked records. Responses include pagination `meta` and `access.create`/`access.edit` capabilities. Invalid relationships and capacity/date violations return field-specific `422` responses; records outside the caller's scope return `404`.

User creation fields are `name`, `email`, `password`, `roles`, `campus_ids` and `is_active`. Updates accept the same fields except `password`, which is prohibited; password reset is not yet implemented. Role codes are `admin`, `centre_admin`, `campus_manager`, `teacher`, `hr_officer`, `finance_officer`, `student` and `guardian`. Role assignments are authoritative; the old `users.role` column remains a compatibility field. New code must use the permission/role helpers rather than checking that column directly.

```text
backend/       Laravel API, migrations, commands, and tests
frontend/      Next.js pages, forms, and server-side auth helpers
compose.yaml   Local PostgreSQL, Redis, Garage, backend, worker and frontend services
docker/        Infrastructure configuration, setup and service startup helpers
```
