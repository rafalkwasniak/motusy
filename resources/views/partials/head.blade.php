<meta charset="utf-8" />
<meta name="viewport" content="width=device-width, initial-scale=1.0" />

<title>
    {{ filled($title ?? null) ? $title.' - '.config('app.name', 'Laravel') : config('app.name', 'Laravel') }}
</title>

<link rel="icon" href="/favicon-32.png" type="image/png" sizes="32x32">
<link rel="apple-touch-icon" href="/apple-touch-icon.png">

{{-- Kroje są self-hostowane w public/fonts, deklaracje @font-face siedzą
     w resources/css/app.css. Nie ma tu żadnego zewnętrznego arkusza. --}}
<link rel="preload" href="/fonts/barlow-condensed-latin-ext-700-normal.woff2" as="font" type="font/woff2" crossorigin>
<link rel="preload" href="/fonts/barlow-latin-ext-400-normal.woff2" as="font" type="font/woff2" crossorigin>

@vite(['resources/css/app.css', 'resources/js/app.js'])
@fluxAppearance
