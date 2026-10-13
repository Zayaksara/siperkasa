<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>503 · Sedang pemeliharaan</title>
    <style>
        /* Static fallback for `php artisan down`: no Vite, so token values are inlined. */
        * { box-sizing: border-box; }
        body {
            margin: 0;
            position: relative;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 72px 24px 40px;
            background: #e1ffe0;
            color: #07110b;
            font-family: 'DM Sans', ui-sans-serif, system-ui, sans-serif;
            font-size: 16px;
            line-height: 24px;
            text-align: center;
        }
        header { position: absolute; top: 0; left: 0; padding: 20px 24px; }
        .brand {
            display: inline-flex;
            align-items: center;
            min-height: 44px;
            font-size: 18px;
            font-weight: 700;
            color: #216320;
            text-decoration: none;
        }
        main {
            width: 100%;
            max-width: 64rem;
            display: grid;
            gap: 32px;
            align-items: center;
            justify-items: center;
        }
        .art {
            width: 100%;
            max-width: 15rem;
            aspect-ratio: 1;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 96px;
        }
        .text { width: 100%; max-width: 28rem; }
        @media (min-width: 1024px) {
            header { padding: 20px 64px; }
            main { grid-template-columns: 1fr 1fr; gap: 64px; }
            .art { max-width: 32rem; font-size: 160px; }
        }
        .code {
            margin: 0;
            font-size: 56px;
            line-height: 1;
            font-weight: 700;
            color: #216320;
            font-variant-numeric: tabular-nums;
        }
        h1 { margin: 12px 0 0; font-size: 32px; line-height: 40px; font-weight: 600; }
        p { margin: 12px 0 0; color: #3b4a3c; font-size: 18px; line-height: 28px; }
        a.btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 100%;
            min-height: 48px;
            margin-top: 32px;
            padding: 0 28px;
            border-radius: 999px;
            background: #8a5e3c;
            color: #ffffff;
            font-weight: 600;
            text-decoration: none;
        }
        @media (min-width: 640px) { a.btn { width: auto; } }
        a.btn:hover { background: #74502f; }
        a.btn:focus-visible, .brand:focus-visible { outline: 3px solid #216320; outline-offset: 3px; }
        .text .help { margin-top: 24px; font-size: 14px; line-height: 20px; }
    </style>
</head>
<body>
    <header><a class="brand" href="/">SiPerkasa</a></header>
    <main>
        <div class="art" aria-hidden="true"><svg viewBox="0 0 120 120" width="100%" height="100%" fill="none" stroke="#07110b" stroke-width="3" stroke-linejoin="round" stroke-linecap="round"><path d="M50 22h20l18 80H32z" fill="#85fc83"/><path d="M42 58h36M37 80h46M24 102h72"/></svg></div>
        <div class="text">
            <p class="code">503</p>
            <h1>Sedang pemeliharaan</h1>
            <p>SiPerkasa sedang diperbarui dan akan segera kembali.</p>
            <a class="btn" href="">Muat ulang</a>
            <p class="help">Butuh bantuan? Hubungi PUSTIKOM UIN Siber Syekh Nurjati Cirebon.</p>
        </div>
    </main>
</body>
</html>
