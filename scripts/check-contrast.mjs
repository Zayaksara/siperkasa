// Verifies the design-token pairs meet WCAG AA (4.5:1). Run: node scripts/check-contrast.mjs
const pairs = [
    // [label, foreground, background]
    ['text on page', '#07110b', '#ffffff'],
    ['muted text on page', '#565c59', '#ffffff'],
    ['muted text on muted', '#565c59', '#f5f7f6'],
    ['primary button', '#ffffff', '#0a5e3d'],
    ['accent-strong text', '#8a5e3c', '#ffffff'],
    ['destructive button', '#ffffff', '#b42318'],
    ['sidebar text', '#e7efec', '#0f443e'],
    ['sidebar primary', '#07110b', '#bb8f32'],
    ['status pending', '#565c59', '#eceeed'],
    ['status assigned', '#1d4e89', '#e3edf8'],
    ['status in-progress', '#8a5e3c', '#f6efe0'],
    ['status on-hold', '#9a4a00', '#fdebd7'],
    ['status closed', '#0a5e3d', '#dff1e8'],
    ['status rejected', '#b42318', '#fde7e5'],
    ['status cancelled', '#3f4542', '#e2e5e3'],
    // dark mode
    ['dark text', '#e7eae9', '#0b1410'],
    ['dark muted text', '#a3adaa', '#0b1410'],
    ['dark primary button', '#04150e', '#3fb98a'],
    ['dark destructive', '#1a0705', '#f0786d'],
    ['dark sidebar text', '#d7e3df', '#0a1f1b'],
    ['dark status pending', '#c4cbc8', '#252c29'],
    ['dark status assigned', '#8fb8ea', '#14263d'],
    ['dark status in-progress', '#e0b878', '#3a2d17'],
    ['dark status on-hold', '#f0a867', '#3a2410'],
    ['dark status closed', '#6fd3a7', '#0f3326'],
    ['dark status rejected', '#f59a92', '#3d1511'],
    ['dark status cancelled', '#aab1ae', '#26302c'],
];

const lum = (hex) => {
    const [r, g, b] = [1, 3, 5].map((i) => {
        const c = parseInt(hex.slice(i, i + 2), 16) / 255;
        return c <= 0.03928 ? c / 12.92 : ((c + 0.055) / 1.055) ** 2.4;
    });
    return 0.2126 * r + 0.7152 * g + 0.0722 * b;
};

let failed = 0;
for (const [label, fg, bg] of pairs) {
    const [a, b] = [lum(fg), lum(bg)].sort((x, y) => y - x);
    const ratio = (a + 0.05) / (b + 0.05);
    const ok = ratio >= 4.5;
    if (!ok) failed++;
    console.log(
        `${ok ? 'PASS' : 'FAIL'}  ${ratio.toFixed(2)}  ${label}  ${fg} on ${bg}`,
    );
}
process.exit(failed ? 1 : 0);
