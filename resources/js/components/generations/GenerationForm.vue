<script setup lang="ts">
import { useForm, useHttp } from '@inertiajs/vue3';
import { watchDebounced } from '@vueuse/core';
import { Plus, X } from '@lucide/vue';
import { computed, onMounted } from 'vue';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { Spinner } from '@/components/ui/spinner';
import { formatUsd } from '@/lib/money';
import { estimate as estimateRoute, store } from '@/routes/generations';
import type { Estimate } from '@/types';

const props = defineProps<{
    sizes: string[];
    qualities: string[];
}>();

const MAX_REFERENCE_IMAGES = 4;
// Bound from script: a literal "https://" attribute inside this v-for trips up vue-tsc.
const REFERENCE_PLACEHOLDER = 'https://example.com/style.png';

const form = useForm({
    prompt: '',
    size: props.sizes[0],
    quality: 'medium',
    reference_images: [] as string[],
});

// The estimate comes from the server, which prices it with the same code that
// records the final cost.
type EstimateRequest = {
    prompt: string;
    size: string;
    quality: string;
    reference_image_count: number;
};

const estimate = useHttp<EstimateRequest, Estimate>(estimateRoute(), {
    prompt: '',
    size: form.size,
    quality: form.quality,
    reference_image_count: 0,
});

function refreshEstimate(): void {
    estimate.prompt = form.prompt;
    estimate.size = form.size;
    estimate.quality = form.quality;
    estimate.reference_image_count = form.reference_images.filter(
        (url) => url.trim() !== '',
    ).length;
    estimate.cancel();
    void estimate.submit();
}

onMounted(refreshEstimate);
watchDebounced(
    () => [
        form.prompt,
        form.size,
        form.quality,
        form.reference_images.join('\n'),
    ],
    refreshEstimate,
    { debounce: 300 },
);

const canAddReference = computed(
    () => form.reference_images.length < MAX_REFERENCE_IMAGES,
);

// Errors arrive as "reference_images" or "reference_images.2".
const referenceImagesError = computed(() => {
    const errors = form.errors as Record<string, string | undefined>;
    const key = Object.keys(errors).find((name) =>
        name.startsWith('reference_images'),
    );

    return key ? errors[key] : undefined;
});

function submit(): void {
    form.transform((data) => ({
        ...data,
        reference_images: data.reference_images.filter(
            (url) => url.trim() !== '',
        ),
    })).post(store.url(), {
        preserveScroll: true,
        onSuccess: () => form.reset('prompt', 'reference_images'),
    });
}
</script>

<template>
    <form class="flex flex-col gap-5" @submit.prevent="submit">
        <div class="grid gap-2">
            <Label for="prompt">Prompt</Label>
            <textarea
                id="prompt"
                v-model="form.prompt"
                rows="4"
                maxlength="4000"
                placeholder="A lighthouse on a cliff at dawn, oil painting"
                class="min-h-24 w-full rounded-md border border-input bg-transparent px-3 py-2 text-base shadow-xs outline-none placeholder:text-muted-foreground focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50 md:text-sm dark:bg-input/30"
            ></textarea>
            <InputError :message="form.errors.prompt" />
        </div>

        <div class="grid grid-cols-2 gap-4">
            <div class="grid gap-2">
                <Label>Size</Label>
                <Select v-model="form.size">
                    <SelectTrigger class="w-full">
                        <SelectValue />
                    </SelectTrigger>
                    <SelectContent>
                        <SelectItem
                            v-for="size in sizes"
                            :key="size"
                            :value="size"
                        >
                            {{ size }}
                        </SelectItem>
                    </SelectContent>
                </Select>
                <InputError :message="form.errors.size" />
            </div>
            <div class="grid gap-2">
                <Label>Quality</Label>
                <Select v-model="form.quality">
                    <SelectTrigger class="w-full capitalize">
                        <SelectValue />
                    </SelectTrigger>
                    <SelectContent>
                        <SelectItem
                            v-for="quality in qualities"
                            :key="quality"
                            :value="quality"
                            class="capitalize"
                        >
                            {{ quality }}
                        </SelectItem>
                    </SelectContent>
                </Select>
                <InputError :message="form.errors.quality" />
            </div>
        </div>

        <div class="grid gap-2">
            <Label>Reference images (optional)</Label>
            <div
                v-for="(url, index) in form.reference_images"
                :key="index"
                class="flex gap-2"
            >
                <Input
                    v-model="form.reference_images[index]"
                    type="url"
                    :placeholder="REFERENCE_PLACEHOLDER"
                />
                <Button
                    type="button"
                    variant="ghost"
                    size="icon"
                    aria-label="Remove reference image"
                    @click="form.reference_images.splice(index, 1)"
                >
                    <X />
                </Button>
            </div>
            <Button
                v-if="canAddReference"
                type="button"
                variant="outline"
                size="sm"
                class="w-fit"
                @click="form.reference_images.push('')"
            >
                <Plus /> Add a reference image
            </Button>
            <InputError :message="referenceImagesError" />
        </div>

        <div class="rounded-lg border bg-muted/40 p-4 text-sm">
            <div class="flex items-baseline justify-between gap-4">
                <span class="text-muted-foreground">Estimated cost</span>
                <span
                    v-if="estimate.response"
                    class="text-lg font-semibold tabular-nums"
                >
                    ≈ {{ formatUsd(estimate.response.expected) }}
                </span>
                <Spinner v-else />
            </div>
            <p v-if="estimate.response" class="mt-1 text-muted-foreground">
                Between {{ formatUsd(estimate.response.min) }} and
                {{ formatUsd(estimate.response.max) }}. You pay the exact amount
                once the image is ready, never more than
                {{ formatUsd(estimate.response.max) }}. Failed generations are
                free.
            </p>
        </div>

        <Button type="submit" :disabled="form.processing">
            <Spinner v-if="form.processing" />
            Generate
        </Button>
    </form>
</template>
