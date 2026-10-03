import { usePage } from '@inertiajs/vue3';
import {
    Boxes,
    Building2,
    ClipboardList,
    FilePlus,
    Home,
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
import { index as usersIndex } from '@/routes/admin/users';
import { dashboard as picDashboard } from '@/routes/pic';
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
];

const adminNav: NavItem[] = [
    { title: 'Beranda', href: adminDashboard(), icon: Home },
    { title: 'Aset', href: assetsIndex(), icon: Boxes },
    { title: 'Impor Aset', href: assetImportsIndex(), icon: Upload },
    { title: 'Kategori', href: categoriesIndex(), icon: Tags },
    { title: 'Gedung & Ruang', href: buildingsIndex(), icon: Building2 },
    { title: 'Pengguna', href: usersIndex(), icon: Users },
];

const picNav: NavItem[] = [
    { title: 'Beranda', href: picDashboard(), icon: Home },
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
