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
    <div v-if="meta.total" class="flex flex-wrap items-center justify-between gap-3 border-t border-linha-fraca p-3 text-sm tabular-nums">
        <div class="text-suave">
            <template v-if="temPaginas">
                {{ meta.from }}–{{ meta.to }} de <strong class="text-tinta">{{ meta.total }}</strong> {{ etiqueta }}
            </template>
            <template v-else>
                <strong class="text-tinta">{{ meta.total }}</strong> {{ etiqueta }}
            </template>
        </div>

        <div v-if="temPaginas" class="flex flex-wrap gap-1">
            <template v-for="(link, indice) in links" :key="indice">
                <Link
                    v-if="link.url"
                    :href="link.url"
                    preserve-scroll
                    preserve-state
                    class="inline-flex min-h-11 min-w-11 items-center justify-center rounded-[10px] border px-3 font-bold transition"
                    :class="link.active
                        ? 'border-verde bg-verde text-white'
                        : 'border-linha-forte bg-white text-tinta hover:bg-fundo'"
                >
                    {{ texto(link.label) }}
                </Link>
                <span
                    v-else
                    class="inline-flex min-h-11 min-w-11 items-center justify-center rounded-[10px] border border-linha px-3 font-bold text-linha-forte"
                >
                    {{ texto(link.label) }}
                </span>
            </template>
        </div>
    </div>
</template>
