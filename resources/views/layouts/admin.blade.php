<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>@yield('title', 'Apollo Admin')</title>
    @vite(['resources/css/admin.css', 'resources/js/app.js'])
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Geist:wght@400;500;600&amp;family=Hanken+Grotesk:wght@600;700&amp;family=JetBrains+Mono:wght@400;500&amp;display=swap" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:opsz,wght,FILL,GRAD@20..48,100..700,0..1,-50..200" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
</head>
<body class="bg-background font-body-md text-on-surface min-h-screen selection:bg-primary-container selection:text-on-primary">
    <aside class="admin-sidebar fixed left-0 top-0 h-full w-64 bg-surface-container-lowest border-r border-outline-variant/30 z-50 flex flex-col">
        <div class="h-16 px-space-md flex items-center border-b border-outline-variant/20 gap-space-sm">
            <div class="h-8 w-8 rounded-lg bg-sky-500/20 text-sky-400 border border-sky-500/30 flex items-center justify-center shrink-0">
    <svg class="w-4 h-4 fill-current" viewBox="0 0 24 24">
        <path d="M12 2L2 7l10 5 10-5-10-5zM2 17l10 5 10-5M2 12l10 5 10-5"/>
    </svg>
</div>
            <div class="flex flex-col">
                <span class="font-headline-sm text-headline-sm text-on-surface leading-none">ApolloBlog</span>
                <span class="font-label-sm text-label-sm text-primary tracking-widest uppercase mt-0.5">Control Core</span>
            </div>
        </div>

        <div class="flex-1 overflow-y-auto px-3 py-4 space-y-6">
            <nav class="space-y-1" data-active-classes="bg-surface-container-high text-primary border-l-2 border-primary font-semibold">
                <div class="px-3 pb-1 font-label-sm text-label-sm text-outline tracking-wider uppercase">Content</div>
                <a @class([
                        'flex items-center gap-3 px-3 py-2 rounded transition-all font-body-sm text-body-sm',
                        'bg-surface-container-high text-primary border-l-2 border-primary font-semibold' => request()->routeIs('admin.dashboard'),
                        'text-on-surface-variant hover:bg-surface-container hover:text-on-surface' => ! request()->routeIs('admin.dashboard'),
                    ])
                    @if (request()->routeIs('admin.dashboard')) aria-current="page" @endif
                    data-path="admin-dashboard"
                    href="{{ route('admin.dashboard') }}">
                    <span class="material-symbols-outlined text-[18px]">dashboard</span>
                    <span>Dashboard</span>
                </a>
                <a @class([
                    'flex items-center gap-3 px-3 py-2 rounded transition-all font-body-sm text-body-sm',
                    'bg-surface-container-high text-primary border-l-2 border-primary font-semibold' => request()->routeIs('admin.articles.*'),
                    'text-on-surface-variant hover:bg-surface-container hover:text-on-surface' => ! request()->routeIs('admin.articles.*'),
                ]) @if (request()->routeIs('admin.articles.*')) aria-current="page" @endif data-path="admin-articles" href="{{ route('admin.articles.index') }}">
                    <span class="material-symbols-outlined text-[18px]">article</span>
                    <span>Articles</span>
                </a>
                <a @class([
                    'flex items-center gap-3 px-3 py-2 rounded transition-all font-body-sm text-body-sm',
                    'bg-surface-container-high text-primary border-l-2 border-primary font-semibold' => request()->routeIs('admin.categories.*'),
                    'text-on-surface-variant hover:bg-surface-container hover:text-on-surface' => ! request()->routeIs('admin.categories.*'),
                ]) @if (request()->routeIs('admin.categories.*')) aria-current="page" @endif data-path="admin-categories" href="{{ route('admin.categories.index') }}">
                    <span class="material-symbols-outlined text-[18px]">label</span>
                    <span>Categories &amp; Tags</span>
                </a>
                <a @class([
                    'flex items-center gap-3 px-3 py-2 rounded transition-all font-body-sm text-body-sm',
                    'bg-surface-container-high text-primary border-l-2 border-primary font-semibold' => request()->routeIs('admin.media.*'),
                    'text-on-surface-variant hover:bg-surface-container hover:text-on-surface' => ! request()->routeIs('admin.media.*'),
                ])
                @if (request()->routeIs('admin.media.*')) aria-current="page" @endif
                data-path="media-library"
                href="{{ route('admin.media.index') }}">
                    <span class="material-symbols-outlined text-[18px]">perm_media</span>
                    <span>Media Library</span>
                </a>
            </nav>
            <nav class="space-y-1" data-active-classes="bg-surface-container-high text-primary border-l-2 border-primary font-semibold">
                <div class="px-3 pb-1 font-label-sm text-label-sm text-outline tracking-wider uppercase">AI Automation</div>
                <a class="flex items-center gap-3 px-3 py-2 rounded text-on-surface-variant hover:bg-surface-container hover:text-on-surface transition-all font-body-sm text-body-sm" data-path="ai-weekly-review" href="{{ route('admin.weekly-reviews.index') }}">
                    <span class="material-symbols-outlined text-[18px]">auto_awesome</span>
                    <span>AI Weekly Review</span>
                </a>
                <a class="flex items-center gap-3 px-3 py-2 rounded text-on-surface-variant hover:bg-surface-container hover:text-on-surface transition-all font-body-sm text-body-sm" data-path="pipeline-sources" href="{{ route('admin.ai-sources.index') }}">
                    <span class="material-symbols-outlined text-[18px]">hub</span>
                    <span>Pipeline Sources</span>
                </a>
                <a class="flex items-center gap-3 px-3 py-2 rounded text-on-surface-variant hover:bg-surface-container hover:text-on-surface transition-all font-body-sm text-body-sm" data-path="job-status" href="{{ route('admin.automation-runs.index') }}">
                    <span class="material-symbols-outlined text-[18px]">terminal</span>
                    <span>Job Status</span>
                </a>
            </nav>
            <nav class="space-y-1" data-active-classes="bg-surface-container-high text-primary border-l-2 border-primary font-semibold">
                <div class="px-3 pb-1 font-label-sm text-label-sm text-outline tracking-wider uppercase">Community</div>
                <a @class([
                    'flex items-center gap-3 px-3 py-2 rounded transition-all font-body-sm text-body-sm',
                    'bg-surface-container-high text-primary border-l-2 border-primary font-semibold' => request()->routeIs('admin.users.*'),
                    'text-on-surface-variant hover:bg-surface-container hover:text-on-surface' => ! request()->routeIs('admin.users.*'),
                ]) @if (request()->routeIs('admin.users.*')) aria-current="page" @endif data-path="admin-users" href="{{ route('admin.users.index') }}">
                    <span class="material-symbols-outlined text-[18px]">group</span>
                    <span>Users</span>
                </a>
                <a class="flex items-center gap-3 px-3 py-2 rounded text-on-surface-variant hover:bg-surface-container hover:text-on-surface transition-all font-body-sm text-body-sm" data-path="comment-moderation" href="{{ route('admin.comments.index') }}">
                    <span class="material-symbols-outlined text-[18px]">rate_review</span>
                    <span>Comments Moderation</span>
                </a>
                <a class="flex items-center gap-3 px-3 py-2 rounded text-on-surface-variant hover:bg-surface-container hover:text-on-surface transition-all font-body-sm text-body-sm" data-path="direct-inbox" href="#">
                    <span class="material-symbols-outlined text-[18px]">inbox</span>
                    <span>Direct Inbox</span>
                </a>
            </nav>
            <nav class="space-y-1" data-active-classes="bg-surface-container-high text-primary border-l-2 border-primary font-semibold">
                <div class="px-3 pb-1 font-label-sm text-label-sm text-outline tracking-wider uppercase">System</div>
                <a class="flex items-center gap-3 px-3 py-2 rounded text-on-surface-variant hover:bg-surface-container hover:text-on-surface transition-all font-body-sm text-body-sm" data-path="admin-analytics" href="#">
                    <span class="material-symbols-outlined text-[18px]">analytics</span>
                    <span>Analytics</span>
                </a>
                <a class="flex items-center gap-3 px-3 py-2 rounded text-on-surface-variant hover:bg-surface-container hover:text-on-surface transition-all font-body-sm text-body-sm" data-path="admin-settings" href="#">
                    <span class="material-symbols-outlined text-[18px]">settings</span>
                    <span>Settings</span>
                </a>
            </nav>
        </div>

        <div class="p-3 border-t border-outline-variant/20">
            <a class="flex items-center gap-2 px-3 py-2 rounded text-on-surface-variant hover:text-on-surface hover:bg-surface-container transition-colors font-body-sm text-body-sm" data-path="home" href="/" target="_blank">
                <span class="material-symbols-outlined text-[18px]">open_in_new</span>
                <span>Public Publication</span>
            </a>
        </div>
    </aside>

    <div class="pl-64">
        <header class="fixed top-0 left-64 right-0 h-16 bg-surface-container-lowest/80 backdrop-blur-xl border-b border-outline-variant/30 z-40 flex items-center justify-between px-space-lg">
            <div class="flex items-center gap-space-md">
                <div class="flex items-center gap-2 bg-surface-container-low border border-outline-variant/30 px-3 py-1.5 rounded w-72 text-on-surface-variant">
                    <span class="material-symbols-outlined text-[18px] text-outline">search</span>
                    <input class="bg-transparent font-label-sm text-label-sm text-on-surface focus:outline-none w-full placeholder:text-outline" placeholder="Filter logs, tags, articles..." type="text">
                </div>
                <div class="hidden xl:flex items-center gap-2 px-2.5 py-1 bg-surface-container-low rounded border border-outline-variant/20">
                    <span class="w-2 h-2 rounded-full bg-tertiary-container animate-pulse"></span>
                    <span class="font-label-sm text-label-sm text-tertiary">Live Sync: Pipeline Active</span>
                </div>
            </div>
            <div class="flex items-center gap-space-md">
                <details x-data="adminNotifications" data-notifications-url="{{ route('admin.notifications.index') }}" class="relative" @toggle="if ($el.open) refresh()">
                    <summary class="relative cursor-pointer list-none rounded p-2 text-on-surface-variant hover:bg-surface-container hover:text-on-surface" aria-label="Thông báo AI Weekly">
                        <span class="material-symbols-outlined text-[20px]">notifications</span>
                        <span x-show="unreadCount > 0" style="display: none" x-text="unreadCount" aria-live="polite" class="absolute -right-1 -top-1 rounded-full bg-primary px-1.5 text-xs text-on-primary"></span>
                    </summary>
                    <div class="absolute right-0 top-full mt-2 grid w-80 gap-2 rounded border border-outline-variant bg-surface-container-lowest p-4 shadow-xl">
                        <h2 class="font-semibold">Thông báo AI Weekly</h2>
                        <p x-show="items.length === 0" class="text-sm text-on-surface-variant">Chưa có thông báo mới.</p>
                        <template x-for="item in items" :key="item.id">
                            <form method="post" :action="item.open_url">
                                @csrf
                                <button type="submit" class="grid w-full gap-1 rounded p-2 text-left hover:bg-surface-container">
                                    <span class="text-xs text-primary" x-text="item.is_candidate ? 'Bản sinh lại đã sẵn sàng' : 'Bài AI Weekly nháp đã sẵn sàng'"></span>
                                    <span class="text-sm" x-text="item.title"></span>
                                    <span class="text-xs text-on-surface-variant" x-text="item.week_start"></span>
                                </button>
                            </form>
                        </template>
                    </div>
                </details>
                <div class="h-4 w-px bg-outline-variant/40"></div>
                <div class="flex items-center gap-space-sm">
                    <div class="flex flex-col items-end">
                        <span class="font-label-md text-label-md text-on-surface leading-tight">{{ auth()->user()->name ?? 'Administrator' }}</span>
                        <span class="font-label-sm text-label-sm text-primary">Admin</span>
                    </div>
                    <img alt="Profile" class="w-8 h-8 rounded-full object-cover border border-outline-variant/50" src="https://lh3.googleusercontent.com/aida-public/AB6AXuCBu4P927XxYrhvvv5Gv8CkWhpgTddrGIQLConhYpFn11Y0kPtBApvJQmGs8QGLKtVbVJY5lf9HoAlVBP2XV85jqqallJlpASGSlc7qVO7V0MB9EafSsc6MpsdenJydBUHEsqeDApW_z7evjUkL3WcmZ9SX-GSiCn8NgW1McOuPmWpIuzGYLW4PYfjglzp8brgbpaA7iK2VEgGzhWtZPINaH3qj1GR-ZNhLbQvrMNlcFWZsf62pbqrP">
                </div>
            </div>
        </header>

        <main class="relative pt-16 bg-background min-h-screen">
            <div class="flex-1 overflow-y-auto p-8 space-y-6">
                @yield('content')
            </div>
        </main>
    </div>
</body>
</html>
