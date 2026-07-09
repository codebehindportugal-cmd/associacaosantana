<script setup>
import { usePermissions } from '@/Composables/usePermissions';

const props = defineProps({ permission: String, role: String });
const { can, hasRole } = usePermissions();
</script>

<template>
    <slot v-if="(!permission || can(permission)) && (!role || hasRole(role))" />
</template>
