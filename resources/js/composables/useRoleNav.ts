import { usePage } from '@inertiajs/vue3';
import {
    Bell,
    Boxes,
    Building2,
    ClipboardList,
    FilePlus,
    Home,
    ListChecks,
    Tags,
    Upload,
    Users,
} from '@lucide/vue';
import { computed } from 'vue';
import { dashboard as adminDashboard } from '@/routes/admin';
import { index as assetImportsIndex } from '@/routes/admin/asset-imports';
import { index as assetsIndex } from '@/routes/admin/assets';
import { index as buildingsIndex } from '@/routes/admin/buildings';
import { index as categoriesIndex } from '@/routes/admin/categories';
import { index as notificationsIndex } from '@/routes/notifications';
import { index as adminReportsIndex } from '@/routes/admin/reports';
import { index as usersIndex } from '@/routes/admin/users';
import { dashboard as picDashboard } from '@/routes/pic';
import { index as picTasksIndex } from '@/routes/pic/tasks';
import {
    create as reportsCreate,
    index as reportsIndex,
} from '@/routes/reports';
import { dashboard as userDashboard } from '@/routes/user';
import type { NavItem } from '@/types';

const userNav: NavItem[] = [
    { title: 'Beranda', href: userDashboard(), icon: Home },
    { title: 'Laporan Saya', href: reportsIndex(), icon: ClipboardList },
    { title: 'Buat Laporan', href: reportsCreate(), icon: FilePlus },
    { title: 'Notifikasi', href: notificationsIndex(), icon: Bell },
];

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

const picNav: NavItem[] = [
    { title: 'Beranda', href: picDashboard(), icon: Home },
    { title: 'Tugas', href: picTasksIndex(), icon: ListChecks },
    { title: 'Notifikasi', href: notificationsIndex(), icon: Bell },
];

export function useRoleNav() {
    const page = usePage();

    const items = computed<NavItem[]>(() => {
        switch (page.props.auth.user?.role) {
            case 'admin':
                return adminNav;
            case 'pic':
                return picNav;
            default:
                return userNav;
        }
    });

    return { items };
}
