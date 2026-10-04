<script setup lang="ts">
import type ApexChartsType from 'apexcharts';
import { computed, onBeforeUnmount, onMounted, ref, watch } from 'vue';

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
    }>(),
    {
        height: 260,
        colors: () => ['--chart-1', '--accent-sky'],
        unit: 'laporan',
    },
);

const emit = defineEmits<{ select: [index: number, seriesIndex: number] }>();

const el = ref<HTMLElement | null>(null);
const minHeight = computed(() => `${props.height}px`);

let chart: ApexChartsType | undefined;
let disposed = false;
let observer: MutationObserver | undefined;
let motionQuery: MediaQueryList | undefined;

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

const token = (style: CSSStyleDeclaration, name: string) =>
    style.getPropertyValue(name.startsWith('--') ? name : `--${name}`).trim();

function buildOptions(): ApexCharts.ApexOptions {
    const root = document.documentElement;
    const style = getComputedStyle(root);
    const dark = root.classList.contains('dark');
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
            animations: { enabled: animate },
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
        stroke: { width: props.type === 'line' ? 3 : 0, curve: 'smooth' },
        markers: {
            size: props.type === 'line' ? 5 : 0,
            strokeWidth: 2,
            strokeColors: token(style, '--card'),
            hover: { size: 7 },
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
            tickAmount: props.type === 'line' ? 6 : undefined,
            labels: {
                style: { fontSize: '12px', colors: muted },
                trim: true,
                rotate: 0,
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
                return `${escapeHtml(name)}: ${total} ${props.unit}`;
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
    chart = new ApexCharts(el.value, buildOptions());
    await chart.render();
}

function refresh() {
    if (chart) void chart.updateOptions(buildOptions(), false, true);
}

onMounted(() => {
    void render();
    observer = new MutationObserver(refresh);
    observer.observe(document.documentElement, {
        attributes: true,
        attributeFilter: ['class'],
    });
    motionQuery = window.matchMedia('(prefers-reduced-motion: reduce)');
    motionQuery.addEventListener('change', refresh);
});

watch(
    () => [props.series, props.categories, props.height, props.colors],
    refresh,
    { deep: true },
);

onBeforeUnmount(() => {
    disposed = true;
    observer?.disconnect();
    motionQuery?.removeEventListener('change', refresh);
    chart?.destroy();
    chart = undefined;
});
</script>

<template>
    <div
        ref="el"
        class="w-full min-w-0"
        aria-hidden="true"
        :style="{ minHeight }"
    />
</template>
