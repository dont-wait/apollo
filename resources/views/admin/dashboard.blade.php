@extends('layouts.admin')

@section('title', 'Operations Dashboard - Apollo')
@section('page-title', 'Operations Dashboard')

@section('content')
    @php
        $pipelineStatus = $latestPipelineRun?->status ?? 'IDLE';
        $pipelineStatusLabel = match ($pipelineStatus) {
            'RUNNING' => 'Pipeline Running',
            'PENDING' => 'Pipeline Queued',
            'FAILED' => 'Needs Attention',
            'PARTIAL' => 'Partial Success',
            'SUCCESS' => 'Pipeline Healthy',
            default => 'Pipeline Standby',
        };
        $pipelineStatusClass = match ($pipelineStatus) {
            'FAILED' => 'text-error',
            'RUNNING', 'PENDING' => 'text-primary',
            default => 'text-tertiary',
        };
    @endphp

    <div class="-m-8 min-h-full overflow-hidden bg-[#0d1015] px-4 py-6 text-[#e5e7eb] sm:px-8 lg:px-10">
        <div class="mx-auto flex max-w-[1480px] flex-col gap-6">
            <header class="flex flex-col justify-between gap-6 lg:flex-row lg:items-end">
                <div class="max-w-2xl">
                    <p class="mb-3 font-mono text-[10px] uppercase tracking-[0.16em] text-[#758092]">Telemetry // Node Orchestrator</p>
                    <h1 class="font-display text-4xl font-semibold tracking-tight text-[#f2f4f8] sm:text-5xl">Operations Dashboard</h1>
                    <p class="mt-3 max-w-xl text-sm leading-6 text-[#929baa]">Real-time metrics, autonomous research ingestion pipelines, and editorial state monitoring.</p>
                </div>

                <div class="flex flex-col items-stretch gap-3 sm:flex-row sm:items-center">
                    <div class="flex gap-2">
                        <a class="inline-flex flex-1 items-center justify-center gap-2 rounded border border-white/10 bg-[#151920] px-3 py-2.5 font-mono text-[10px] uppercase tracking-wider text-[#bdc5d2] transition-colors hover:border-primary/40 hover:text-primary" href="{{ route('admin.weekly-reviews.index') }}">
                            <span class="material-symbols-outlined text-[17px] text-tertiary">bolt</span>
                            Open AI Weekly
                        </a>
                        <a class="inline-flex flex-1 items-center justify-center gap-2 rounded bg-[#36b9f2] px-3 py-2.5 font-mono text-[10px] font-semibold uppercase tracking-wider text-[#06243a] shadow-[0_0_22px_rgba(54,185,242,0.18)] transition-colors hover:bg-[#72d2ff]" href="{{ route('admin.articles.create') }}">
                            <span class="material-symbols-outlined text-[17px]">add_circle</span>
                            New Article
                        </a>
                    </div>
                </div>
            </header>

            <section class="relative overflow-hidden rounded border border-white/5 bg-[#171b22] px-5 py-4 shadow-[0_18px_50px_rgba(0,0,0,0.16)]" aria-label="Autonomous pipeline alert">
                <div class="absolute inset-y-0 left-0 w-1 bg-gradient-to-b from-tertiary via-primary to-secondary"></div>
                <div class="flex flex-col gap-4 pl-2 sm:flex-row sm:items-center sm:justify-between">
                    <div class="flex items-start gap-4">
                        <div class="mt-0.5 flex size-10 shrink-0 items-center justify-center rounded bg-[#10232a] text-primary">
                            <span class="material-symbols-outlined">auto_awesome</span>
                        </div>
                        <div>
                            <p class="font-mono text-[10px] uppercase tracking-widest text-primary">Autonomous pipeline alert <span class="text-[#687382]">• {{ $latestWeeklyReview?->generated_at?->diffForHumans() ?? 'awaiting first run' }}</span></p>
                            <p class="mt-1 text-sm text-[#c5cad3]">
                                @if ($latestWeeklyReview)
                                    Bản nháp <span class="font-semibold text-primary">{{ $latestWeeklyReview->title }}</span> đã sẵn sàng cho vòng kiểm duyệt.
                                @else
                                    Chưa có bản tổng hợp AI Weekly nào được tạo. Pipeline đang chờ dữ liệu đầu vào.
                                @endif
                            </p>
                            @if ($latestWeeklyReview?->excerpt)
                                <p class="mt-1 max-w-3xl text-xs text-[#7f8998]">{{ \Illuminate\Support\Str::limit($latestWeeklyReview->excerpt, 150) }}</p>
                            @endif
                        </div>
                    </div>
                    <a class="inline-flex shrink-0 items-center justify-center gap-2 rounded bg-[#2f9bc9] px-4 py-2.5 font-mono text-[10px] font-semibold uppercase tracking-wider text-[#061c2a] transition-colors hover:bg-[#6bd0fa]" href="{{ $latestWeeklyReview ? route('admin.weekly-reviews.show', $latestWeeklyReview) : route('admin.weekly-reviews.index') }}">
                        {{ $latestWeeklyReview ? 'Review Draft Now' : 'Open Pipeline' }}
                        <span class="material-symbols-outlined text-[18px]">arrow_forward</span>
                    </a>
                </div>
            </section>

            <section class="grid grid-cols-1 gap-4 sm:grid-cols-2 xl:grid-cols-4" aria-label="Operations metrics">
                <article class="relative overflow-hidden rounded border border-white/5 bg-[#171b22] p-5">
                    <div class="flex items-center justify-between font-mono text-[10px] uppercase tracking-widest text-[#788291]">
                        <span>Total Readers</span>
                        <span class="material-symbols-outlined text-[20px] text-primary">group</span>
                    </div>
                    <div class="mt-7 flex items-end justify-between gap-4">
                        <div>
                            <p class="font-display text-4xl font-semibold tracking-tight text-[#f2f4f8]">{{ number_format($postStats['total_views']) }}</p>
                            <p class="mt-2 text-xs text-[#8b95a5]">Across all editorial entries</p>
                        </div>
                    </div>
                    <svg class="mt-5 h-10 w-full" viewBox="0 0 240 40" fill="none" preserveAspectRatio="none" aria-hidden="true">
                        <path d="M0 32C20 31 25 25 45 27C65 29 70 16 91 21C111 26 124 13 144 18C164 23 175 4 194 11C213 18 220 3 240 5V40H0V32Z" fill="url(#readers-fill)"></path>
                        <path d="M0 32C20 31 25 25 45 27C65 29 70 16 91 21C111 26 124 13 144 18C164 23 175 4 194 11C213 18 220 3 240 5" stroke="#78d6ff" stroke-width="1.5"></path>
                        <defs><linearGradient id="readers-fill" x1="120" y1="0" x2="120" y2="40" gradientUnits="userSpaceOnUse"><stop stop-color="#78d6ff" stop-opacity=".25"></stop><stop offset="1" stop-color="#78d6ff" stop-opacity="0"></stop></linearGradient></defs>
                    </svg>
                </article>

                <article class="rounded border border-white/5 bg-[#171b22] p-5">
                    <div class="flex items-center justify-between font-mono text-[10px] uppercase tracking-widest text-[#788291]">
                        <span>Engagement Rate</span>
                        <span class="material-symbols-outlined text-[20px] text-tertiary">track_changes</span>
                    </div>
                    <div class="mt-7 flex items-end gap-3">
                        <p class="font-display text-4xl font-semibold tracking-tight text-[#f2f4f8]">{{ number_format($engagementRate, 1) }}%</p>
                        <span class="pb-1 font-mono text-xs text-tertiary">30d</span>
                    </div>
                    <p class="mt-2 text-xs text-[#8b95a5]">Recent comments / total reads</p>
                    <div class="mt-6 h-1.5 overflow-hidden rounded-full bg-[#2a3039]"><div class="h-full rounded-full bg-gradient-to-r from-tertiary to-primary" style="width: {{ min(100, max(3, $engagementRate)) }}%"></div></div>
                </article>

                <article class="rounded border border-white/5 bg-[#171b22] p-5">
                    <div class="flex items-center justify-between font-mono text-[10px] uppercase tracking-widest text-[#788291]">
                        <span>Community Comments</span>
                        <span class="material-symbols-outlined text-[20px] text-secondary">forum</span>
                    </div>
                    <div class="mt-7 flex items-end justify-between gap-4">
                        <p class="font-display text-4xl font-semibold tracking-tight text-[#f2f4f8]">{{ number_format($commentStats['total']) }}</p>
                        <span class="rounded bg-[#252337] px-2 py-1 font-mono text-[10px] text-secondary">{{ number_format($commentStats['pending']) }} pending</span>
                    </div>
                    <p class="mt-2 text-xs text-[#8b95a5]">{{ number_format($commentStats['recent']) }} added in the last 30 days</p>
                    <div class="mt-5 flex items-center gap-2 font-mono text-[10px] text-[#768090]"><span class="size-1.5 rounded-full bg-secondary"></span> Moderation queue active</div>
                </article>

                <article class="rounded border border-white/5 bg-[#171b22] p-5">
                    <div class="flex items-center justify-between font-mono text-[10px] uppercase tracking-widest text-[#788291]">
                        <span>AI Weekly Status</span>
                        <span class="material-symbols-outlined text-[20px] text-tertiary">hub</span>
                    </div>
                    <p class="mt-7 flex items-center gap-2 text-lg font-semibold {{ $pipelineStatusClass }}"><span class="size-2 rounded-full bg-current"></span>{{ $pipelineStatusLabel }}</p>
                    <p class="mt-2 text-xs text-[#8b95a5]">Last executed {{ $latestPipelineRun?->created_at?->diffForHumans() ?? 'never' }} <span class="text-[#5f6978]">• {{ $failedPipelineRuns }} errors</span></p>
                    <div class="mt-5 flex justify-between font-mono text-[10px] text-[#768090]"><span>Sources active</span><span class="text-primary">{{ $pipelineSources->count() }}</span></div>
                </article>
            </section>

            <section class="grid grid-cols-1 gap-5 xl:grid-cols-[minmax(0,1.7fr)_minmax(300px,0.8fr)]" aria-label="Pipeline overview">
                <article class="rounded border border-white/5 bg-[#171b22] p-5 sm:p-6">
                    <div class="flex flex-col justify-between gap-3 sm:flex-row sm:items-start">
                        <div>
                            <h2 class="flex items-center gap-2 text-lg font-semibold text-[#edf0f5]"><span class="material-symbols-outlined text-primary">monitoring</span>Editorial Throughput</h2>
                            <p class="mt-1 text-xs text-[#858f9e]">Current distribution across the editorial workflow.</p>
                        </div>
                    </div>
                    <div class="mt-6 grid gap-4 rounded border border-white/5 bg-[#0a0e13] p-4">
                        @php
                            $statusTotal = max(1, $postStats['total']);
                        @endphp
                        @foreach ([['label' => 'Published', 'value' => $postStats['published'], 'color' => 'bg-tertiary'], ['label' => 'In review', 'value' => $postStats['pending'], 'color' => 'bg-secondary'], ['label' => 'Scheduled', 'value' => $postStats['scheduled'], 'color' => 'bg-primary'], ['label' => 'Draft', 'value' => $postStats['draft'], 'color' => 'bg-[#788291]']] as $status)
                            <div>
                                <div class="mb-1.5 flex items-center justify-between font-mono text-[10px] uppercase tracking-wider text-[#8b95a5]"><span>{{ $status['label'] }}</span><span class="text-[#dce2ea]">{{ number_format($status['value']) }}</span></div>
                                <div class="h-2 overflow-hidden rounded-full bg-[#252b34]"><div class="h-full rounded-full {{ $status['color'] }}" style="width: {{ min(100, ($status['value'] / $statusTotal) * 100) }}%"></div></div>
                            </div>
                        @endforeach
                    </div>
                </article>

                <article class="rounded border border-white/5 bg-[#171b22] p-5 sm:p-6">
                    <div class="flex items-start justify-between gap-3">
                        <div><h2 class="text-lg font-semibold text-[#edf0f5]">Pipeline Ingestion</h2><p class="mt-1 text-xs text-[#858f9e]">Active data crawlers</p></div>
                        <span class="material-symbols-outlined text-primary">sync</span>
                    </div>
                    <div class="mt-5 grid gap-3">
                        @forelse ($pipelineSources as $source)
                            <a class="flex items-center justify-between gap-3 rounded bg-[#1c2129] p-3 transition-colors hover:bg-[#252d37]" href="{{ route('admin.ai-sources.index') }}">
                                <div class="flex min-w-0 items-center gap-3">
                                    <span class="flex size-8 shrink-0 items-center justify-center rounded bg-[#25303a] font-mono text-[10px] text-primary">{{ strtoupper(mb_substr($source->name, 0, 2)) }}</span>
                                    <span class="min-w-0"><span class="block truncate text-sm font-medium text-[#dfe4eb]">{{ $source->name }}</span><span class="mt-1 block text-[10px] text-[#7e8998]">{{ $source->articles_count }} articles parsed</span></span>
                                </div>
                                <span class="shrink-0 rounded bg-tertiary/10 px-2 py-1 font-mono text-[9px] uppercase tracking-wider text-tertiary">• Active</span>
                            </a>
                        @empty
                            <p class="rounded bg-[#1c2129] p-4 text-sm text-[#7e8998]">No active sources configured.</p>
                        @endforelse
                    </div>
                </article>
            </section>

            <section class="grid grid-cols-1 gap-4 md:grid-cols-3" aria-label="Editorial signals">
                @forelse ($topViewedPosts->take(3) as $post)
                    <a class="group relative flex min-h-40 flex-col justify-end overflow-hidden rounded border border-white/5 bg-gradient-to-br from-[#172c38] via-[#17202a] to-[#12161c] p-5" href="{{ route('admin.articles.edit', $post) }}">
                        <div class="absolute inset-0 opacity-30 [background-image:radial-gradient(circle_at_20%_20%,rgba(123,212,255,.4),transparent_35%),linear-gradient(135deg,transparent_30%,rgba(71,230,174,.15),transparent_60%)]"></div>
                        <div class="relative"><span class="font-mono text-[10px] uppercase tracking-widest text-primary">{{ $post->category?->name ?? 'Uncategorized' }}</span><h3 class="mt-8 truncate text-base font-semibold text-[#eef2f7] transition-colors group-hover:text-primary">{{ $post->title }}</h3><p class="mt-1 text-xs text-[#8993a1]">{{ number_format($post->view_count) }} views · {{ number_format($post->like_count) }} likes</p></div>
                    </a>
                @empty
                    <div class="rounded border border-white/5 bg-[#171b22] p-5 text-sm text-[#7e8998] md:col-span-3">Chưa có bài viết nào.</div>
                @endforelse
            </section>

            <section class="overflow-hidden rounded border border-white/5 bg-[#171b22]" aria-label="Recent articles and editorial log">
                <div class="flex flex-col justify-between gap-3 border-b border-white/5 p-5 sm:flex-row sm:items-center sm:px-6">
                    <div><h2 class="text-lg font-semibold text-[#edf0f5]">Recent Articles &amp; Editorial Log</h2><p class="mt-1 text-xs text-[#858f9e]">Manage publications, triage review drafts, and audit release cycles.</p></div>
                    <a class="inline-flex w-fit items-center gap-2 rounded border border-white/10 px-3 py-2 font-mono text-[10px] uppercase tracking-wider text-[#aab3c0] transition-colors hover:border-primary/40 hover:text-primary" href="{{ route('admin.articles.index') }}">View All ({{ number_format($postStats['total']) }}) <span class="material-symbols-outlined text-[15px]">arrow_forward</span></a>
                </div>
                <div class="overflow-x-auto">
                    <table class="w-full min-w-[760px] border-collapse text-left">
                        <thead class="bg-[#0b0f14] font-mono text-[9px] uppercase tracking-widest text-[#778292]"><tr><th class="px-6 py-3">Title &amp; Subtitle</th><th class="px-4 py-3">Category</th><th class="px-4 py-3">Status</th><th class="px-4 py-3 text-right">Views</th><th class="px-6 py-3 text-right">Upvotes / Saves</th></tr></thead>
                        <tbody class="divide-y divide-white/5 text-xs">
                            @forelse ($recentPosts as $post)
                                @php
                                    $statusLabel = match ($post->status) {
                                        'DRAFT' => 'DRAFT', 'REVIEW' => 'REVIEW', 'SCHEDULED' => 'SCHEDULED', 'PUBLISHED' => 'PUBLISHED', default => 'ARCHIVED',
                                    };
                                    $statusClass = match ($post->status) {
                                        'PUBLISHED' => 'text-tertiary bg-tertiary/10', 'REVIEW' => 'text-secondary bg-secondary/10', 'SCHEDULED' => 'text-primary bg-primary/10', default => 'text-[#8e98a7] bg-white/5',
                                    };
                                @endphp
                                <tr class="transition-colors hover:bg-white/[0.025]">
                                    <td class="max-w-lg px-6 py-4"><a class="block truncate font-semibold text-[#e4e8ee] transition-colors hover:text-primary" href="{{ $post->isPublished() ? route('articles.show', ['article' => $post->slug]) : route('admin.articles.edit', $post) }}">{{ $post->title }}</a><span class="mt-1 block truncate text-[10px] text-[#7d8796]">{{ $post->excerpt }}</span></td>
                                    <td class="whitespace-nowrap px-4 py-4 font-mono text-[10px] text-[#a1aab7]">{{ $post->category?->name ?? '—' }}</td>
                                    <td class="whitespace-nowrap px-4 py-4"><span class="rounded px-2 py-1 font-mono text-[9px] uppercase tracking-wider {{ $statusClass }}">● {{ $statusLabel }}</span></td>
                                    <td class="whitespace-nowrap px-4 py-4 text-right font-mono text-[10px] text-[#a1aab7]">{{ number_format($post->view_count) }}</td>
                                    <td class="whitespace-nowrap px-6 py-4 text-right font-mono text-[10px] text-[#a1aab7]">{{ number_format($post->like_count) }} / {{ number_format($post->bookmarks_count) }}</td>
                                </tr>
                            @empty
                                <tr><td class="px-6 py-10 text-center text-sm text-[#7e8998]" colspan="5">Chưa có bài viết nào.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
                <div class="flex flex-col justify-between gap-2 border-t border-white/5 px-6 py-3 font-mono text-[9px] uppercase tracking-wider text-[#697382] sm:flex-row"><span>Showing {{ $recentPosts->count() }} of {{ number_format($postStats['total']) }} total recorded editorial entries</span><span>Sort: By Last Updated (Descending)</span></div>
            </section>
        </div>
    </div>
@endsection
