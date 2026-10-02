import { usePage } from '@inertiajs/vue3';
import { Home } from '@lucide/vue';
import { computed } from 'vue';
import { dashboard as adminDashboard } from '@/routes/admin';
import { dashboard as picDashboard } from '@/routes/pic';
import { dashboard as userDashboard } from '@/routes/user';
import type { NavItem } from '@/types';

const userNav: NavItem[] = [
    { title: 'Beranda', href: userDashboard(), icon: Home },
];

const adminNav: NavItem[] = [
    { title: 'Beranda', href: adminDashboard(), icon: Home },
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
