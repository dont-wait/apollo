<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>@yield('title', 'Apollo Admin')</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
</head>
<body class="bg-[#0b0f19] text-slate-200 flex min-h-screen font-sans antialiased">

    <aside class="w-64 bg-[#070a12] border-r border-slate-800/80 flex flex-col justify-between p-4 shrink-0 min-h-screen">
        <div>
            <div class="flex items-center gap-3 px-3 py-3 mb-6">
                <div class="w-8 h-8 rounded-lg bg-sky-500/20 text-sky-400 border border-sky-500/30 flex items-center justify-center font-bold">
                    <i class="fa-solid fa-bolt text-sm"></i>
                </div>
                <span class="text-base font-bold text-white tracking-wide">Apollo Admin</span>
            </div>

            <nav class="space-y-1 text-sm">
                <a href="{{ route('admin.dashboard') }}" class="flex items-center gap-3 px-3 py-2.5 rounded-lg bg-sky-600/20 text-sky-400 font-medium border border-sky-500/30">
                    <i class="fa-solid fa-chart-pie w-5 text-center"></i>
                    <span>Dashboard</span>
                </a>
                <a href="#" class="flex items-center gap-3 px-3 py-2.5 rounded-lg text-slate-400 hover:bg-slate-800/60 hover:text-slate-200 transition">
                    <i class="fa-regular fa-newspaper w-5 text-center"></i>
                    <span>Articles</span>
                </a>
                <a href="#" class="flex items-center gap-3 px-3 py-2.5 rounded-lg text-slate-400 hover:bg-slate-800/60 hover:text-slate-200 transition">
                    <i class="fa-solid fa-folder-tree w-5 text-center"></i>
                    <span>Categories</span>
                </a>
                <a href="#" class="flex items-center gap-3 px-3 py-2.5 rounded-lg text-slate-400 hover:bg-slate-800/60 hover:text-slate-200 transition">
                    <i class="fa-solid fa-users w-5 text-center"></i>
                    <span>Users</span>
                </a>
                <a href="#" class="flex items-center gap-3 px-3 py-2.5 rounded-lg text-slate-400 hover:bg-slate-800/60 hover:text-slate-200 transition">
                    <i class="fa-solid fa-sliders w-5 text-center"></i>
                    <span>Settings</span>
                </a>
            </nav>
        </div>

        <div class="border-t border-slate-800/80 pt-4 flex items-center gap-3 px-2">
            <div class="w-9 h-9 rounded-full bg-slate-800 border border-slate-700 flex items-center justify-center font-bold text-xs text-sky-400">
                AD
            </div>
            <div class="text-xs min-w-0">
                <p class="font-medium text-slate-200 truncate">{{ auth()->user()->name ?? 'Administrator' }}</p>
                <p class="text-slate-500">Admin</p>
            </div>
        </div>
    </aside>

    <main class="flex-1 flex flex-col min-w-0 overflow-hidden">
        <!-- Header dùng chung -->
        <header class="h-16 border-b border-slate-800/80 px-8 flex items-center justify-between bg-[#070a12]/40 backdrop-blur shrink-0">
            <h1 class="text-sm font-semibold text-white">@yield('page-title', 'Dashboard Overview')</h1>
            <div class="flex items-center gap-4">
                <a href="/" target="_blank" class="text-xs text-slate-400 hover:text-white flex items-center gap-1.5 transition">
                    <i class="fa-solid fa-arrow-up-right-from-square"></i> Xem Website
                </a>
            </div>
        </header>

        <div class="flex-1 overflow-y-auto p-8 space-y-6">
            @yield('content')
        </div>
    </main>

</body>
</html>