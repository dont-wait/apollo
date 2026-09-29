<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('title', config('app.name', 'Laravel'))</title>
    <style>
        :root {
            color-scheme: light;
            font-family: Inter, ui-sans-serif, system-ui, sans-serif;
            color: #172033;
            background: #f4f7fb;
        }

        * {
            box-sizing: border-box;
        }

        body {
            margin: 0;
        }

        a {
            color: inherit;
        }

        .container {
            width: min(100% - 2rem, 72rem);
            margin: 0 auto;
        }

        .site-header {
            padding: 1.25rem 0;
            background: #172033;
            color: #fff;
        }

        .site-header .container,
        .page-heading {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 1rem;
        }

        .brand {
            font-weight: 700;
            text-decoration: none;
        }

        main {
            padding: 3rem 0;
        }

        .page-heading {
            margin-bottom: 1.5rem;
        }

        h1,
        p {
            margin-top: 0;
        }

        h1 {
            margin-bottom: .5rem;
        }

        .muted {
            color: #64748b;
        }

        .card {
            overflow-x: auto;
            background: #fff;
            border: 1px solid #dbe3ef;
            border-radius: .75rem;
            box-shadow: 0 0.5rem 1.5rem rgb(23 32 51 / 6%);
        }

        table {
            width: 100%;
            border-collapse: collapse;
            text-align: left;
        }

        th,
        td {
            padding: 1rem 1.25rem;
            border-bottom: 1px solid #e8edf4;
        }

        th {
            color: #64748b;
            font-size: .8rem;
            letter-spacing: .04em;
            text-transform: uppercase;
        }

        tr:last-child td {
            border-bottom: 0;
        }

        .empty-state {
            padding: 3rem 1.5rem;
            text-align: center;
        }

        .pagination {
            margin-top: 1.5rem;
        }
    </style>
</head>
<body>
    <header class="site-header">
        <div class="container">
            <a class="brand" href="{{ route('home') }}">{{ config('app.name', 'Laravel') }}</a>
            <span>Server-rendered MVC</span>
        </div>
    </header>

    <main>
        <div class="container">
            @yield('content')
        </div>
    </main>
</body>
</html>
