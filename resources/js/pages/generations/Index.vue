<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import GenerationCard from '@/components/generations/GenerationCard.vue';
import GenerationForm from '@/components/generations/GenerationForm.vue';
import Heading from '@/components/Heading.vue';
import { index } from '@/routes/generations';
import type { Generation } from '@/types';

defineOptions({
    layout: {
        breadcrumbs: [{ title: 'Generate', href: index() }],
    },
});

// `generations` is a live prop: the server reloads it whenever one of this
// user's generations changes (see GenerationUpdated).
defineProps<{
    generations: Generation[];
    sizes: string[];
    qualities: string[];
}>();
</script>

<template>
    <Head title="Generate" />

    <div class="grid gap-8 p-4 lg:grid-cols-[minmax(0,24rem)_1fr]">
        <section class="flex flex-col gap-6">
            <Heading
                title="Generate an image"
                description="Describe it, pick a size and quality, and see the cost before you start."
            />
            <GenerationForm :sizes="sizes" :qualities="qualities" />
        </section>

        <section class="flex flex-col gap-6">
            <Heading
                title="History"
                description="Your latest 50 generations."
            />
            <p
                v-if="generations.length === 0"
                class="text-sm text-muted-foreground"
            >
                Nothing yet. Your images will appear here.
            </p>
            <div v-else class="grid gap-4 sm:grid-cols-2 xl:grid-cols-3">
                <GenerationCard
                    v-for="generation in generations"
                    :key="generation.id"
                    :generation="generation"
                />
            </div>
        </section>
    </div>
</template>
