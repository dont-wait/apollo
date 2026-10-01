<!doctype html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('title', 'Authentication · NeuralLog')</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Geist:wght@400;500;600&family=Hanken+Grotesk:wght@600;700&family=JetBrains+Mono:wght@400;500&display=swap" rel="stylesheet">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="relative flex min-h-screen items-center justify-center overflow-x-hidden bg-background px-4 py-8 font-sans text-on-surface sm:px-6">
    <div class="pointer-events-none absolute -left-32 -top-32 size-80 rounded-full bg-primary/10 blur-3xl" aria-hidden="true"></div>
    <div class="pointer-events-none absolute -bottom-32 -right-32 size-80 rounded-full bg-tertiary/10 blur-3xl" aria-hidden="true"></div>

    <main class="relative z-10 w-full max-w-md">
        @yield('content')
    </main>
</body>
</html>
