<script setup lang="ts">
import { ImageOff } from '@lucide/vue';
import { computed } from 'vue';
import { Badge } from '@/components/ui/badge';
import { Spinner } from '@/components/ui/spinner';
import { formatUsd } from '@/lib/money';
import type { Generation } from '@/types';

const props = defineProps<{
    generation: Generation;
}>();

const statusVariant = computed(() => {
    switch (props.generation.status) {
        case 'succeeded':
            return 'default';
        case 'failed':
            return 'destructive';
        default:
            return 'secondary';
    }
});
</script>

<template>
    <article class="flex flex-col overflow-hidden rounded-xl border">
        <div
            class="relative flex aspect-square items-center justify-center bg-muted"
        >
            <img
                v-if="generation.image_url"
                :src="generation.image_url"
                :alt="generation.prompt"
                class="h-full w-full object-contain"
                loading="lazy"
            />
            <div
                v-else-if="!generation.is_final"
                class="flex flex-col items-center gap-2 text-sm text-muted-foreground"
            >
                <Spinner />
                <span class="capitalize">{{ generation.status }}…</span>
            </div>
            <ImageOff v-else class="size-8 text-muted-foreground" />
        </div>

        <div class="flex flex-1 flex-col gap-3 p-4 text-sm">
            <div class="flex items-center justify-between gap-2">
                <Badge :variant="statusVariant" class="capitalize">
                    {{ generation.status }}
                </Badge>
                <span class="text-xs text-muted-foreground">
                    {{ generation.size }} · {{ generation.quality }}
                </span>
            </div>

            <p class="line-clamp-3">{{ generation.prompt }}</p>

            <p v-if="generation.error" class="text-destructive">
                {{ generation.error }}
            </p>

            <dl class="mt-auto grid grid-cols-2 gap-1 tabular-nums">
                <dt class="text-muted-foreground">Estimated</dt>
                <dd
                    class="text-right"
                    :title="`Up to ${generation.estimated_price_max}`"
                >
                    ≈ {{ formatUsd(generation.estimated_price) }}
                </dd>
                <dt class="text-muted-foreground">Charged</dt>
                <dd
                    class="text-right font-medium"
                    :title="generation.price ?? undefined"
                >
                    {{
                        generation.price !== null
                            ? formatUsd(generation.price)
                            : '—'
                    }}
                </dd>
            </dl>
        </div>
    </article>
</template>
