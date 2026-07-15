<script setup lang="ts">
import { router, usePage } from '@inertiajs/vue3';

const page = usePage();

const locales = ['tr', 'en'] as const;

function setLocale(locale: string): void {
    if (locale === page.props.locale) {
        return;
    }

    router.post(
        '/locale',
        { locale },
        { preserveScroll: true, preserveState: false },
    );
}
</script>

<template>
    <div
        class="inline-flex items-center rounded-lg border border-neutral-200 p-0.5 text-xs dark:border-white/10"
    >
        <button
            v-for="l in locales"
            :key="l"
            type="button"
            class="rounded-md px-2 py-1 font-medium uppercase transition"
            :class="
                page.props.locale === l
                    ? 'bg-emerald-500 text-white'
                    : 'text-neutral-500 hover:text-neutral-800 dark:text-neutral-400 dark:hover:text-white'
            "
            @click="setLocale(l)"
        >
            {{ l }}
        </button>
    </div>
</template>
