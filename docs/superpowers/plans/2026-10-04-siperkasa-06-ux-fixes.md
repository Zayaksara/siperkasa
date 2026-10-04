# SiPerkasa Plan 6 · Task U — UX fixes from owner feedback

**Spec:** Obsidian `40 - PROJECTS/SiPerkasa/04-Spec.md` + owner decisions recorded in `06-Progress.md` (2026-10-04, "Keputusan Fatih (Task U)" and grilling Q1–Q14).
**Branch:** `feat/siperkasa-v1`. Runs after Plan 5 Task 2, before Plan 5 Task 3.

## Global Constraints

- Stack: Laravel 13 + Inertia + Vue 3 + Tailwind 4 + shadcn-vue. Pest tests. PHPStan must stay at 0 errors. Run `vendor/bin/pint --dirty`.
- **Never run `migrate:fresh`.** Seeders must be idempotent (`firstOrCreate`).
- All user-facing text in Indonesian.
- UI tasks: invoke skill `frontend-design` (+ `shadcn` for components). Follow `.agents/rules/DESIGN.md` (mobile-first, 360 px first, touch targets h-11, focus ring visible). Do **not** change page layout/structure — only the elements named in the task.
- Animation (if any): GSAP only, inside `onMounted` with `gsap.context()` + cleanup, respect `prefers-reduced-motion`.
- Colors only through CSS tokens in `resources/css/app.css`; every new token gets a light value **and** a dark value.
- Report status flow and `guard()` / `AttachmentStore::within()` stay the only paths (Plan 4 rulings).
- Commits: conventional (`feat:`/`fix:`), no Claude attribution lines. Never push.

## Task 1 · U1 report submission limits (backend)

Owner decision: throttle **10 report submissions per hour per user** + **max 5 reports in status Pending per user**.

- Add a named rate limiter `reports` (per authenticated user id, 10 per hour) and apply `throttle:reports` to route `reports.store` only.
- When throttled the user sees an Indonesian message (flash/toast or validation error on the form) — not a raw 429 page: "Terlalu banyak laporan dalam satu jam. Coba lagi nanti."
- In `StoreReportRequest`, add an after-validation check: if the user already has ≥ 5 reports with status `Pending`, fail with error on key `title` (or a form-level key the form already displays): "Kamu masih punya 5 laporan menunggu validasi. Tunggu sebagian divalidasi atau batalkan salah satunya."
- Cancelled/Rejected/other statuses do not count toward the 5.
- Also in this task: `title` rule becomes `min:10` (keep `max:120`) in StoreReportRequest and UpdateReportRequest; message "Judul minimal 10 karakter. Sebut barang, kerusakan, dan lokasi singkat."
- Tests (Pest feature): 5 pending blocks 6th; 4 pending + 1 cancelled allows; 11th submit within an hour is throttled with Indonesian message; title < 10 rejected; existing factories/tests that use short titles updated.

## Task 2 · U2 + U3 + U5 small UI polish

- **U2 Rupiah input:** PIC "Selesai" form field `repair_cost` (`resources/js/pages/pic/tasks/Show.vue`). Show thousand separators with dot while typing (`1.500.000`), decimal with comma (max 2 digits, `1.500.000,50`). Submit raw number string (`1500000.50`) — backend validation unchanged. Build a reusable `RupiahInput.vue` (v-model = raw string) with `inputmode="decimal"`, "Rp" prefix, caret stays sane when dots are inserted. Unit-free behaviour tested by existing feature tests + manual check.
- **U3 admin table row click:** `resources/js/pages/admin/reports/Index.vue` desktop table (≥1024 px): whole row navigates to detail (not only ticket number). Keep the ticket `<Link>` for keyboard/semantics; row gets `cursor-pointer`, hover background, and click handler that ignores clicks on interactive children (checkbox, buttons, links) and text selection. Same pattern on any other desktop table where only the code is a link (check `pic/tasks/Index.vue`, admin master-data lists are out of scope).
- **U5 notification badges:** notification inbox items + bell dropdown items show a colored badge per notification. Use `data.status` already stored in each notification (`ReportSubmitted`, `ReportAssigned`, `ReportStatusChanged`) and render with the existing `StatusBadge` component/tokens so colors match status everywhere. If a notification type needs a label that is not a status (e.g. "Laporan baru", "Tugas baru"), add a small type chip using existing tokens.

## Task 3 · U4 backend — category search, new category, admin edit at validation

Owner decisions (grilling): keep 16 BMN categories; category picker becomes searchable combobox fed by asset names in DB + a small manual keyword list for building items; new category **"Bangunan & Sarana"**; admin may edit **title and category** during validation; changes logged in timeline; PIC suggestion follows the (new) category's `default_pic_id`; user not notified.

- `CategorySeeder::NAMES` gets `'Bangunan & Sarana'` (firstOrCreate, idempotent). Do not run migrate:fresh; owner seeds production with `db:seed --class=CategorySeeder`.
- Manual keyword map in `config/siperkasa.php` (create or extend): `'category_keywords' => ['Bangunan & Sarana' => ['pintu','jendela','plafon','atap','dinding','lantai','toilet','wc','keran','wastafel','pipa','saluran','stop kontak','saklar','listrik','lampu ruangan','kunci pintu','tangga','cat']]`.
- New endpoint `GET /categories/search?q=` (auth, any role that can create reports; name `categories.search`): q ≥ 2 chars; returns up to 8 categories ranked by match count, each `{id, name, examples: [max 3 distinct asset names or keywords that matched]}`. Matching: category name LIKE, keyword map, and DB `assets.name LIKE %q%` grouped by `category_id` with `COUNT` + distinct names (one aggregate query, escape LIKE wildcards like `AssetSearchController`). Empty q or no match → return `[{Lain-lain, examples: []}]` as fallback. Target < 500 ms on 30k assets (index check; add index on `assets.name` only if EXPLAIN shows full scan cost is a problem — document decision in report).
- Admin validation (`ApproveReportRequest` + `ValidateReport`): optional `title` (string, min:10, max:120) and `category_id` (exists). If changed, update the report inside the existing `guard()` lock and append to the validation timeline note: `Judul diubah: "A" → "B"` / `Kategori diubah: X → Y`.
- `Admin\ReportController@show`: also pass `categories` (id, name, default_pic_id) so the frontend can recompute suggested PIC when admin picks another category (only active PICs count, same rule as current `suggested_pic_id`).
- Tests: search endpoint (asset name match returns category + examples, keyword match "plafon" → Bangunan & Sarana, wildcard escaping, fallback Lain-lain, auth required); approve with changed title/category updates report + timeline note; approve without them unchanged; invalid category rejected.

## Task 4 · U4 frontend — report form + admin validation form

- New `CategoryCombobox.vue` (shadcn Combobox/Command pattern like `AssetCombobox.vue`, debounce 300 ms) calling `categories.search`. Shows full list of categories when empty (from props), search results as "Kategori · contoh1, contoh2, contoh3". Replaces the category `NativeSelect` in `ReportForm.vue`; v-model stays `category_id`.
- In `ReportForm.vue`: when an asset is picked and it has a category, fill `category_id` automatically (still editable). Asset search results already include category name; add `category_id` to `AssetSearchController` payload (backend 1-line change allowed in this task, with test update).
- Title field: placeholder `Contoh: AC ruang 201 bocor`, helper text under input `Sebut barang + kerusakan + lokasi singkat.`, `minlength=10`.
- Admin detail validation form (`admin/reports/Show.vue`): editable title input + CategoryCombobox prefilled with current values; when category changes, suggested PIC updates from `categories[].default_pic_id` (only if that PIC is in active `pics`), without overwriting a PIC the admin already chose manually.
- frontend-design + shadcn skills mandatory; 360 px and 1280 px checked; keyboard reachable.

## Task 5 · U6 accent color variety (light + dark)

Owner: UI too monotone; add colors for light and dark — **stop if it would change existing structure**.

- Add accent token set in `resources/css/app.css` (light + dark), registered in Tailwind theme: e.g. `--accent-teal`, `--accent-amber`, `--accent-sky`, `--accent-rose`, `--accent-violet` each with `-bg` soft variant. Derive from existing chart palette for harmony; check WCAG AA contrast for text-on-bg pairs in both themes.
- Apply only to decorative/identity spots without changing layout: icon tiles of dashboard/stat cards, sidebar/nav active indicator icons, EmptyState icon circle, PageHeader icon (if any), category chips. No new components, no layout changes.
- Document the token list + where used in the report so Plan 5 Task 4 (dashboard ApexCharts) reuses them.
