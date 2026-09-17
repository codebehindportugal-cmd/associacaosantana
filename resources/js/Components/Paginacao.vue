<script setup>
import { Link } from '@inertiajs/vue3';
import { computed } from 'vue';

const props = defineProps({
    // O paginador do Laravel tal como vem do Inertia
    dados: { type: Object, required: true },
    etiqueta: { type: String, default: 'registos' },
});

const meta = computed(() => props.dados ?? {});
const links = computed(() => meta.value.links ?? []);
const temPaginas = computed(() => (meta.value.last_page ?? 1) > 1);

// O Laravel manda "&laquo; Previous" e "Next &raquo;"
const texto = (label) => String(label ?? '')
    .replace(/&laquo;\s*/g, '')
    .replace(/\s*&raquo;/g, '')
    .replace('Previous', 'Anterior')
    .replace('Next', 'Seguinte')
    .trim();
</script>

<template>
    <div v-if="meta.total" class="flex flex-wrap items-center justify-between gap-3 border-t border-slate-100 p-3 text-sm">
        <div class="text-slate-500">
            <template v-if="temPaginas">
                {{ meta.from }}–{{ meta.to }} de <strong class="text-slate-700">{{ meta.total }}</strong> {{ etiqueta }}
            </template>
            <template v-else>
                <strong class="text-slate-700">{{ meta.total }}</strong> {{ etiqueta }}
            </template>
        </div>

        <div v-if="temPaginas" class="flex flex-wrap gap-1">
            <template v-for="(link, indice) in links" :key="indice">
                <Link
                    v-if="link.url"
                    :href="link.url"
                    preserve-scroll
                    preserve-state
                    class="rounded-md border px-3 py-1.5 font-bold transition"
                    :class="link.active
                        ? 'border-slate-900 bg-slate-900 text-white'
                        : 'border-slate-300 bg-white text-slate-700 hover:bg-slate-50'"
                >
                    {{ texto(link.label) }}
                </Link>
                <span
                    v-else
                    class="rounded-md border border-slate-200 px-3 py-1.5 font-bold text-slate-300"
                >
                    {{ texto(link.label) }}
                </span>
            </template>
        </div>
    </div>
</template>
