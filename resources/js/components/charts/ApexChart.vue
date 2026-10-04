<script setup lang="ts">
import type ApexChartsType from 'apexcharts';
import { computed, onBeforeUnmount, onMounted, ref, watch } from 'vue';
import { Skeleton } from '@/components/ui/skeleton';

type Series = { name: string; data: number[] };

const props = withDefaults(
    defineProps<{
        type: 'bar' | 'line';
        categories: string[];
        series: Series[];
        horizontal?: boolean;
        height?: number;
        /** CSS variable names, one per series (or per bar when `distributed`). */
        colors?: string[];
        /** One colour per bar (single series only). */
        distributed?: boolean;
        /** Word after the value in tooltips, e.g. "laporan". */
        unit?: string;
        xTitle?: string;
        /** Stroke dash per series (line charts), a non-colour cue. 0 = solid. */
        dashArray?: number[];
        /** Marker shape per series (line charts), a non-colour cue. */
        markerShapes?: ('circle' | 'square' | 'diamond' | 'triangle')[];
    }>(),
    {
        height: 260,
        colors: () => ['--chart-1', '--accent-sky'],
        unit: 'laporan',
    },
);

const emit = defineEmits<{ select: [index: number, seriesIndex: number] }>();

const el = ref<HTMLElement | null>(null);
const loaded = ref(false);
const boxHeight = computed(() => `${props.height}px`);

let chart: ApexChartsType | undefined;
let disposed = false;
let ready = false;
let observer: MutationObserver | undefined;
let motionQuery: MediaQueryList | undefined;
let themeTimer: ReturnType<typeof setTimeout> | undefined;
let dark = false;
let builtSignature = '';

/** ApexCharts renders tooltip and legend text as HTML; labels come from user-entered names. */
const escapeHtml = (text: string) =>
    text.replace(
        /[&<>"']/g,
        (c) =>
            ({
                '&': '&amp;',
                '<': '&lt;',
                '>': '&gt;',
                '"': '&quot;',
                "'": '&#39;',
            })[c] as string,
    );

const dataSignature = () =>
    JSON.stringify([props.series, props.categories, props.height]);

/** Dense axis: daily trends have 8-31 points; fewer markers and straight segments keep them light. */
const dense = () => props.type === 'line' && props.categories.length > 12;

/** Dense category axis on a narrow chart: tilt labels instead of trimming them. */
const tilt = () => dense() && (el.value?.clientWidth ?? 640) < 520;

const token = (style: CSSStyleDeclaration, name: string) =>
    style.getPropertyValue(name.startsWith('--') ? name : `--${name}`).trim();

function buildOptions(): ApexCharts.ApexOptions {
    const root = document.documentElement;
    const style = getComputedStyle(root);
    dark = root.classList.contains('dark');
    const muted = token(style, '--muted-foreground');
    const grid = token(style, '--border');
    const fg = token(style, '--foreground');
    const font = token(style, '--font-sans') || 'DM Sans, sans-serif';
    const animate = !window.matchMedia('(prefers-reduced-motion: reduce)')
        .matches;
    const colors = props.colors.map((name) => token(style, name));

    return {
        chart: {
            type: props.type,
            height: props.height,
            fontFamily: font,
            foreColor: muted,
            background: 'transparent',
            toolbar: { show: false },
            zoom: { enabled: false },
            // Short, decelerating morph: bars/lines grow together instead of
            // one by one, and data updates reuse the same shapes.
            animations: {
                enabled: animate,
                easing: 'easeout',
                speed: 400,
                animateGradually: { enabled: false },
                dynamicAnimation: { enabled: animate, speed: 300 },
            },
            events: {
                dataPointSelection: (_event, _chart, config) =>
                    emit(
                        'select',
                        config?.dataPointIndex ?? 0,
                        config?.seriesIndex ?? 0,
                    ),
            },
        },
        series: props.series,
        colors,
        plotOptions: {
            bar: {
                horizontal: props.horizontal ?? false,
                distributed: props.distributed ?? false,
                borderRadius: 4,
                borderRadiusApplication: 'end',
                barHeight: '64%',
                columnWidth: '56%',
                dataLabels: { position: 'top' },
            },
        },
        dataLabels: {
            enabled: props.type === 'bar',
            style: { fontSize: '12px', fontWeight: 600, colors: [fg] },
            offsetX: props.horizontal ? 14 : 0,
            offsetY: props.horizontal ? 0 : -18,
        },
        stroke: {
            width: props.type === 'line' ? 3 : 0,
            curve: dense() ? 'straight' : 'smooth',
            dashArray: props.type === 'line' ? props.dashArray : undefined,
        },
        markers: {
            size: props.type === 'line' ? (dense() ? 3 : 6) : 0,
            shape: props.markerShapes,
            strokeWidth: 2,
            strokeColors: token(style, '--card'),
            hover: { size: dense() ? 6 : 7 },
        },
        xaxis: {
            categories: props.categories,
            max: props.horizontal
                ? Math.ceil(
                      Math.max(1, ...props.series.flatMap((s) => s.data)) *
                          1.15,
                  )
                : undefined,
            tickPlacement: 'on',
            // Dense axes on narrow screens: tilt the full d/m labels so none collide.
            labels: {
                style: { fontSize: '12px', colors: muted },
                trim: false,
                rotate: tilt() ? -45 : 0,
                rotateAlways: tilt(),
                minHeight: tilt() ? 44 : undefined,
                hideOverlappingLabels: true,
                formatter: (value: string | number) =>
                    typeof value === 'number' && !Number.isInteger(value)
                        ? ''
                        : String(value),
            },
            title: {
                text: props.xTitle,
                style: { fontSize: '12px', fontWeight: 500, color: muted },
            },
            axisBorder: { color: grid },
            axisTicks: { color: grid },
        },
        yaxis: {
            min: 0,
            forceNiceScale: true,
            decimalsInFloat: 0,
            labels: {
                maxWidth: 120,
                style: { fontSize: '12px', colors: muted },
            },
        },
        grid: {
            borderColor: grid,
            strokeDashArray: 3,
            padding: { right: props.horizontal ? 8 : 24 },
        },
        legend: {
            show: props.series.length > 1,
            position: 'top',
            horizontalAlign: 'left',
            fontSize: '13px',
            labels: { colors: fg },
            markers: { size: 7, shape: 'square', offsetX: -2 },
            itemMargin: { horizontal: 12, vertical: 4 },
            formatter: (name: string, opts) => {
                const total = (
                    props.series[opts?.seriesIndex ?? 0]?.data ?? []
                ).reduce((sum, value) => sum + value, 0);
                const index = opts?.seriesIndex ?? 0;
                const cue =
                    props.type === 'line' && props.dashArray
                        ? ` (${props.dashArray[index] ? 'garis putus-putus' : 'garis penuh'})`
                        : '';
                return `${escapeHtml(name)}${cue}: ${total} ${props.unit}`;
            },
        },
        tooltip: {
            theme: dark ? 'dark' : 'light',
            shared: props.type === 'line',
            intersect: false,
            x: {
                formatter: (value: number | string) =>
                    escapeHtml(String(value)),
            },
            y: {
                formatter: (value: number) => `${value} ${props.unit}`,
                title: { formatter: (name: string) => escapeHtml(name) },
            },
        },
        states: {
            hover: { filter: { type: 'lighten' } },
            active: { filter: { type: 'none' } },
        },
    };
}

async function render() {
    if (!el.value) return;
    const { default: ApexCharts } = await import('apexcharts');
    if (disposed || !el.value) return;
    builtSignature = dataSignature();
    const instance = new ApexCharts(el.value, buildOptions());
    chart = instance;
    ready = false;
    await instance.render();
    if (disposed || chart !== instance) {
        instance.destroy();
        return;
    }
    ready = true;
    loaded.value = true;
    // Props changed while the library loaded / first render ran: apply them now.
    if (dataSignature() !== builtSignature) updateData();
}

/** Full option rebuild (theme / reduced motion / style props). No animation. */
function restyle() {
    if (!chart || !ready || disposed) return;
    void chart.updateOptions(buildOptions(), false, false);
}

/** Data change: morph the existing chart in place, no destroy or redraw. */
function updateData() {
    if (!chart || !ready || disposed) return; // render() re-runs this once ready
    builtSignature = dataSignature();
    const max = props.horizontal
        ? Math.ceil(Math.max(1, ...props.series.flatMap((s) => s.data)) * 1.15)
        : undefined;
    void chart.updateOptions(
        {
            chart: { height: props.height },
            series: props.series,
            xaxis: { categories: props.categories, max },
        },
        false,
        true,
    );
}

/** Theme flips fire several class mutations; coalesce them and ignore other class changes. */
function onThemeMutation() {
    const next = document.documentElement.classList.contains('dark');
    if (next === dark) return;
    clearTimeout(themeTimer);
    themeTimer = setTimeout(restyle, 80);
}

onMounted(() => {
    void render();
    observer = new MutationObserver(onThemeMutation);
    observer.observe(document.documentElement, {
        attributes: true,
        attributeFilter: ['class'],
    });
    motionQuery = window.matchMedia('(prefers-reduced-motion: reduce)');
    motionQuery.addEventListener('change', restyle);
});

// Parents often pass fresh inline arrays on every render (e.g. while typing in
// a date input). Compare by content so only real data changes touch the chart.
watch(dataSignature, updateData);
watch(
    () => JSON.stringify([props.colors, props.dashArray, props.markerShapes]),
    restyle,
);

onBeforeUnmount(() => {
    disposed = true;
    ready = false;
    observer?.disconnect();
    motionQuery?.removeEventListener('change', restyle);
    clearTimeout(themeTimer);
    chart?.destroy();
    chart = undefined;
});
</script>

<template>
    <div
        class="relative w-full min-w-0"
        aria-hidden="true"
        :style="{ height: boxHeight }"
    >
        <Skeleton
            v-if="!loaded"
            class="absolute inset-0 motion-reduce:animate-none"
        />
        <div ref="el" class="size-full" />
    </div>
</template>
