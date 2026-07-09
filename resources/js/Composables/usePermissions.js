import { usePage } from '@inertiajs/vue3';

export function usePermissions() {
    const page = usePage();
    const permissions = () => page.props.auth?.permissions ?? [];
    const roles = () => page.props.auth?.roles ?? [];

    return {
        can: (permission) => permissions().includes(permission),
        hasRole: (role) => roles().includes(role),
    };
}
