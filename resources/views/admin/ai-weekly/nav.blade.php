<nav aria-label="AI Weekly" class="flex flex-wrap gap-1 border-b border-outline-variant/50 pb-3 font-label-sm text-label-sm">
    @foreach ([
        ['route' => 'admin.weekly-reviews.index', 'active' => 'admin.weekly-reviews.*', 'label' => 'Weekly Reviews', 'icon' => 'auto_awesome'],
        ['route' => 'admin.ai-sources.index', 'active' => 'admin.ai-sources.*', 'label' => 'Nguồn', 'icon' => 'hub'],
        ['route' => 'admin.ai-articles.index', 'active' => 'admin.ai-articles.*', 'label' => 'Bài thu thập', 'icon' => 'article'],
        ['route' => 'admin.automation-runs.index', 'active' => 'admin.automation-runs.*', 'label' => 'Pipeline', 'icon' => 'terminal'],
    ] as $item)
        <a href="{{ route($item['route']) }}" @if (request()->routeIs($item['active'])) aria-current="page" @endif
            @class([
                'inline-flex items-center gap-1.5 rounded px-3 py-2 transition-colors',
                'bg-surface-high text-primary' => request()->routeIs($item['active']),
                'text-on-surface-variant hover:bg-surface-high hover:text-white' => ! request()->routeIs($item['active']),
            ])>
            <span class="material-symbols-outlined text-base">{{ $item['icon'] }}</span>{{ $item['label'] }}
        </a>
    @endforeach
</nav>
