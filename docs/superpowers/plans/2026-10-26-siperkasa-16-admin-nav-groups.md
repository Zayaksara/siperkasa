# Admin Nav Groups Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development. Steps use checkbox (`- [x]`) syntax for tracking.

**Goal:** Kelompokkan menu sidebar admin ke 3 grup berlabel (Operasional, Data Master, Akses) dan tambah menu Notifikasi untuk admin.

**Spec (Fatih 2026-10-26):**

- Grup datar dengan `SidebarGroupLabel`, tanpa submenu lipat.
- Operasional: Beranda, Laporan, Notifikasi. Data Master: Aset, Impor Aset, Kategori, Gedung & Ruang. Akses: Pengguna.
- Admin dapat menu Notifikasi (admin memang menerima `ReportSubmitted`; route `notifications.index` tidak dibatasi role).
- Menu user dan PIC tidak berubah: tetap satu grup berlabel "Menu".

## Global Constraints

- Agents use `/caveman`. UI work: `/frontend-design` + @design agreement (`.agents/rules/DESIGN.md`, `.agents/rules/siperkasa.md`: tokens only, WCAG 2.2 AA, DM Sans, mobile-first 360 px). Tidak ada animasi baru.
- Commit tanpa trailer collaborator / Co-Authored-By. Never push. Stage explicit paths only. Jangan sentuh `LandingFooter.vue` (edit Fatih) dan 19 SVG untracked di `resources/js/assets/errors/`.
- `BottomNav.vue` (HP) memakai `items.slice(0, 4)`: urutan admin baru menghasilkan tab Beranda, Laporan, Notifikasi, Aset, Profil. Ini disengaja; `BottomNav.vue` tidak diubah.

### Task 1: Grup nav admin + Notifikasi

**Owner:** Pak Budi Haiku - grup menu admin (caveman, frontend-design)

**Files:** `resources/js/types/navigation.ts`, `resources/js/composables/useRoleNav.ts`, `resources/js/components/NavMain.vue`

- [x] `navigation.ts`: tambah field opsional ke `NavItem`, tepat setelah `isActive?: boolean;`:

```ts
    group?: string;
```

- [x] `useRoleNav.ts`: ganti seluruh `const adminNav` dengan:

```ts
const adminNav: NavItem[] = [
    {
        title: 'Beranda',
        href: adminDashboard(),
        icon: Home,
        group: 'Operasional',
    },
    {
        title: 'Laporan',
        href: adminReportsIndex(),
        icon: ClipboardList,
        group: 'Operasional',
    },
    {
        title: 'Notifikasi',
        href: notificationsIndex(),
        icon: Bell,
        group: 'Operasional',
    },
    { title: 'Aset', href: assetsIndex(), icon: Boxes, group: 'Data Master' },
    {
        title: 'Impor Aset',
        href: assetImportsIndex(),
        icon: Upload,
        group: 'Data Master',
    },
    {
        title: 'Kategori',
        href: categoriesIndex(),
        icon: Tags,
        group: 'Data Master',
    },
    {
        title: 'Gedung & Ruang',
        href: buildingsIndex(),
        icon: Building2,
        group: 'Data Master',
    },
    { title: 'Pengguna', href: usersIndex(), icon: Users, group: 'Akses' },
];
```

- [x] `NavMain.vue`: tambah computed `groups` setelah `activeItem`, dan ganti template:

```ts
// Items without a group share the default "Menu" label; first-seen order is kept.
const groups = computed(() => {
    const byLabel = new Map<string, NavItem[]>();

    for (const item of props.items) {
        const label = item.group ?? 'Menu';
        byLabel.set(label, [...(byLabel.get(label) ?? []), item]);
    }

    return [...byLabel].map(([label, items]) => ({ label, items }));
});
```

```vue
<template>
    <SidebarGroup v-for="group in groups" :key="group.label" class="px-2 py-0">
        <SidebarGroupLabel>{{ group.label }}</SidebarGroupLabel>
        <SidebarMenu>
            <SidebarMenuItem v-for="item in group.items" :key="item.title">
                <SidebarMenuButton
                    as-child
                    :is-active="item === activeItem"
                    :tooltip="item.title"
                >
                    <Link :href="item.href">
                        <component :is="item.icon" />
                        <span>{{ item.title }}</span>
                    </Link>
                </SidebarMenuButton>
            </SidebarMenuItem>
        </SidebarMenu>
    </SidebarGroup>
</template>
```

- [x] Verifikasi: `npm run check:fix` lalu `npm run check`, `npm run types:check`, `npm run build` — semua lulus.
- [x] Commit (tanpa trailer): `feat: group admin sidebar menu and add notifications`

### Task 2: Review

- [x] Pak Joko Sonnet - review spec + kualitas
- [x] Pak Hendra Opus - review akhir

### Task 3: Notifikasi lebar penuh (semua role)

**Owner:** Pak Budi Haiku - layout notifikasi (caveman, frontend-design)

**Files:** `resources/js/pages/notifications/Index.vue`

**Spec (Fatih 2026-10-26):** halaman Notifikasi tidak kecil di tengah, untuk admin, PIC, dan user.

- [x] Kontainer root: `mx-auto flex w-full max-w-2xl flex-col gap-4 p-4` → `flex flex-col gap-4 p-4` (sama dengan `admin/reports/Index.vue`).
- [x] Baris: blok teks (badge + pesan) `flex-1 min-w-0`; waktu jadi elemen terpisah — di bawah pesan pada < sm, di kanan baris (`sm:ml-auto sm:shrink-0 sm:text-right`, sejajar atas) pada sm+. Tidak ada token/warna baru.
- [x] Verifikasi: `npm run check`, `npm run types:check`, `npm run build`; tes PHP notifikasi tetap lulus (`php artisan test --filter=Notification`).
- [x] Commit (tanpa trailer): `style: let notifications fill the page width`
