# SiPerkasa v1 — Implementation Plans (Index)

> **For agentic workers:** execute the plans **in order**, one task at a time, with `superpowers:subagent-driven-development` (recommended) or `superpowers:executing-plans`. Each plan ends with working, tested software and can be reviewed and merged on its own.

**Spec:** `C:\Users\Lenovo\Documents\Obsidian Vault\40 - PROJECTS\SiPerkasa\04-Spec.md`, with `00-PRD.md`, `01-Arsitektur.md`, `02-Data-Model.md` and `03-Tasks/F01…F11` next to it. Project rules: `D:\siperkasa\.agents\rules\siperkasa.md` and `db.md`.

The spec covers several independent subsystems, so it is split into five plans. Each one was written after reading the real code (Laravel 13 + Inertia 3 + Vue starter kit, Fortify, Wayfinder, Pest 5).

| # | Plan | Delivers | Spec features |
|---|---|---|---|
| 1 | [Foundation](2026-10-03-siperkasa-01-foundation.md) | Working test suite on MySQL, login by NIM/NIP, three role areas, brand tokens, mobile layout, shared UI components | F01, F11 |
| 2 | [Master data & assets](2026-10-03-siperkasa-02-master-data-assets.md) | Users, categories (+ default PIC), buildings, rooms, BMN import (CLI + UI), asset browser and search | F02, F03 |
| 3 | [Reports core](2026-10-03-siperkasa-03-reports-core.md) | Status machine, policy, ticket numbers, notification classes, create / list / edit / cancel report with compressed photos | F04, F05, F08 (backend) |
| 4 | [Admin & PIC workflow](2026-10-03-siperkasa-04-admin-pic-workflow.md) | Report queue with filters, validate / reject / reassign, PIC tasks (start / hold / resume / complete) | F06, F07 |
| 5 | [Notifications, dashboards, export](2026-10-03-siperkasa-05-notifications-dashboards-export.md) | Bell + inbox, three dashboards, Excel + PDF export, demo seeder, final acceptance run | F08 (UI), F09, F10 |

Dependency chain: **1 → 2 → 3 → 4 → 5** (each plan names what it needs from the earlier ones). Plan 3 can start after Plan 1 if Plan 2's asset search is stubbed, but the order above avoids that.

## Facts found in the codebase (they shaped the plans)

- Repo has **no commits yet** → migrations are edited in place and the dev DB is rebuilt with `migrate:fresh` (as the spec allows). Plan 1 makes the first commit.
- `php.ini` has `pdo_sqlite` **disabled**, so the starter suite (`DB_CONNECTION=sqlite`) cannot run, and `RefreshDatabase` was commented out. Plan 1 moves tests to a separate MySQL database `siperkasa_test`.
- Login already accepted email **or** NIM/NIP; the spec says NIM/NIP, so Plan 1 narrows it and adds the `is_active` check.
- `CheckRole` middleware (alias `role`) already exists; it is reused.
- `User` implemented `MustVerifyEmail` and routes used `verified`, which would lock out admin-created accounts without a verified email.
- Existing frontend: sidebar layout, shadcn-vue components (no `textarea`, `table`, `tabs`), Wayfinder-generated routes in `resources/js/routes` (git-ignored, regenerate with `php artisan wayfinder:generate`).
- Data file `data/daftar-aset-gabungan-terkategori.xlsx`: 25,995 assets, two extra columns, separator rows, `Lokasi Ruang` always "Belum berlokasi".

## Decisions that differ from the spec text — please confirm

| # | Change | Why |
|---|---|---|
| 1 | Fortify registration, **password reset, email verification** and **self-service account deletion** are removed; `User` no longer implements `MustVerifyEmail` | Accounts are admin-created and `email` is optional (D1); verification would block them, reset-by-email is impossible without email, and deleting a user would break report history. Admin resets passwords. |
| 2 | Tests use MySQL `siperkasa_test`, not SQLite | `pdo_sqlite` is disabled on this machine. |
| 3 | Excel import/export use **`openspout/openspout`** instead of `maatwebsite/excel` | Streaming reader/writer: 26 k rows × 80 columns without loading the whole sheet into memory. |
| 4 | Sidebar ↔ bottom-nav switch at `md` (768 px), not 1024 px | The starter sidebar's own breakpoint is `md`. |
| 5 | Charts are CSS bars + a screen-reader table, no chart library | Lighter, no dependency, accessible. |
| 6 | Reassigning a PIC also notifies the reporter | Rules §10 lists "Assigned / reassign → PIC baru (+ user pelapor)". |
| 7 | Report photos use the private `local` disk, served through an authorised route | Damage photos must not be reachable by guessing a URL. |
| 8 | Font DM Sans replaces Instrument Sans; the sidebar is dark green (UINSSC primary) | Brand and `DESIGN.md` font token. |

## Shared names (single source — later plans rely on these exactly)

- **Enums:** `UserRole{User,Admin,Pic}` (`label()`, `dashboardRoute()`); `ReportStatus{Pending,Assigned,InProgress='In_Progress',OnHold='On_Hold',Closed,Rejected,Cancelled}` (`label()`, `allowedNext()`, `canTransitionTo()`, `isFinal()`, `finalValues()`); `Priority{Low,Medium,High,Critical}` (`label()`, `slaDays()`); `AttachmentType{Damage,Completion}`; `AssetImportStatus`.
- **Models:** `User` (`is_active`, `hasRole()`, `defaultCategories()`), `Category` (`default_pic_id`, `isInUse()`), `Building`, `Room`, `Asset` (`bmn_data`), `AssetImport`, `Report` (`visibleTo()`, `overdue()`, `isOverdue()`), `ReportAttachment`, `ReportStatusLog`.
- **Status changes happen only in** `App\Actions\Reports\ReportTransitioner` (`transition`, `note`, `logCreated`); use cases: `SubmitReport`, `UpdateReport`, `CancelReport`, `ValidateReport`, `RejectReport`, `ReassignReport`, `StartReport`, `HoldReport`, `ResumeReport`, `CompleteReport`; photos via `AttachmentStore`.
- **Support:** `TicketNumber::next()`, `ReportPresenter::{summary,detail}`, `ReportFilter`, `DashboardStats`, `ReportExport`, `RowReader`, `BmnColumns`, `BmnValue`.
- **Route names:** `user.dashboard`, `admin.dashboard`, `pic.dashboard`, `reports.*` (reporter), `admin.reports.*`, `pic.tasks.*`, `admin.{users,categories,buildings,rooms,assets,asset-imports}.*`, `assets.search`, `attachments.show`, `notifications.*`.
- **Frontend:** `StatusBadge`, `PageHeader`, `EmptyState`, `ConfirmDialog`, `Pagination`, `Timeline`, `PhotoUploader`, `AssetCombobox`, `StatCard`, `BarList`, `WeeklyTrend`, `NativeSelect`, `Textarea`; navigation per role lives in `resources/js/composables/useRoleNav.ts`.

## Commands

| Purpose | Command |
|---|---|
| Whole test suite | `php artisan test` |
| One file / filter | `php artisan test tests/Feature/Reports/SubmitReportTest.php` · `php artisan test --filter="name"` |
| PHP style / static analysis | `composer lint:check` (fix: `composer lint`) · `composer types:check` |
| Regenerate Wayfinder routes | `php artisan wayfinder:generate` |
| Frontend types / lint+format / build | `npm run types:check` · `npm run check` · `npm run build` |
| Run the app | `composer dev` |
| Fresh seeded dev DB | `php artisan migrate:fresh --seed` |
| Import the BMN file | `php artisan assets:import data/daftar-aset-gabungan-terkategori.xlsx` |

Demo logins after seeding (local only): `admin`, `pic001`, `pic002`, `2010001`, `198501012010011001` — password `password`.

## Working rules for whoever executes these plans

- **Commits:** conventional messages, **no `Co-Authored-By` trailer and no mention of Claude** (user rule).
- **TDD:** every backend task writes the failing test first, runs it, then implements. Do not skip the "run it to see it fail" step.
- **Do not weaken a test to make it pass.** If a test looks wrong, stop and report it.
- Backend tests are HTTP-level Pest feature tests; the status machine also has a unit test. Frontend has no unit-test runner: it is verified by `vue-tsc`, `vp check`, `vite build` and the manual checklist at the end of each UI task (360 px and 1280 px, keyboard only, visible focus).
- A plan task may say "stub page" when Inertia's page-exists check needs a file before the real page is written; the later UI task in the same plan replaces it.

## Honest status of these plans

- They were written from reading the code and the spec, then corrected in a second pass. **No task has been executed in the project yet**, so the first run of a task can still surface a small mismatch (for example a Pest or Inertia testing detail). Fix it in the task and do not weaken the tests.
- Checked while writing (outside the repo, in a scratch folder):
  - `openspout/openspout` resolves to **v5** on PHP 8.5. Every reader/writer call used in Plans 2 and 5 works. Integral floats read back as ints, empty cells as `''`, and `=`-strings are written as formulas, which is why the export sanitises them.
  - The `dompdf` page-number calls used in Plan 5 work. `barryvdh/laravel-dompdf` v3.1 declares support for Laravel 13.
  - All 27 colour pairs in Plan 1's contrast script pass WCAG AA; the lowest is 4.90.
  - Wayfinder renames reserved words: the route `admin.users.import` becomes `importMethod`, and kebab names such as `notifications.read-all` become `readAll`.
  - `config/inertia.php` has `testing.ensure_pages_exist = true`, so tasks create stub page files before their tests run.
- Corrections made in the second pass:
  - Plan 1 is green after every task: the `active` guard moved into Task 4, dashboard stubs no longer depend on Task 7, the steps are renumbered, and the baseline names the one starter test that already fails.
  - Plans 2–5 create stub pages explicitly instead of conditionally.
  - Plan 3 photo compression no longer returns a larger file, and the form uses Wayfinder URLs instead of hard-coded paths.
  - The reserved-word Wayfinder import is fixed in Plan 2.
- Still open from the spec: the real building and room list (Plan 2 seeds placeholders, easy to replace through the admin CRUD), and publishing `04-Spec.md` to an issue tracker (`gh` login and `/setup-matt-pocock-skills` pending).
