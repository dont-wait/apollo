@extends('layouts.admin')

@section('title', 'AI Sources - Apollo Admin')

@section('content')
<div class="-m-8 min-h-full bg-background p-4 text-on-surface sm:p-8">
    <div class="mx-auto max-w-[1440px] space-y-6">
        @include('admin.ai-weekly.nav')
        <header class="flex flex-col justify-between gap-4 sm:flex-row sm:items-end">
            <div>
                <p class="font-mono text-[10px] uppercase tracking-[0.18em] text-primary">AI Automation / Sources</p>
                <h1 class="mt-2 font-display text-3xl font-semibold tracking-tight">Nguồn AI</h1>
                <p class="mt-2 max-w-2xl text-sm leading-6 text-on-surface-variant">Quản lý nguồn tin, độ tin cậy và mức ưu tiên cho nội dung được thu thập.</p>
            </div>
            <a href="#new-source" class="inline-flex shrink-0 items-center justify-center gap-2 rounded bg-primary-container px-4 py-2.5 text-sm font-semibold text-on-primary transition hover:bg-primary focus-visible:outline-2 focus-visible:outline-offset-4 focus-visible:outline-primary">
                <span class="material-symbols-outlined text-lg" aria-hidden="true">add</span>Thêm nguồn
            </a>
        </header>

        <div class="grid items-start gap-6 xl:grid-cols-[minmax(0,1fr)_360px]">
            <section class="min-w-0 space-y-3" aria-label="Danh sách nguồn">
                <div class="flex flex-wrap items-center justify-between gap-3 border-b border-outline-variant/50 pb-3">
                    <h2 class="text-sm font-semibold">Thư viện nguồn <span class="ml-2 rounded border border-outline-variant bg-surface-container px-2 py-0.5 font-mono text-xs text-on-surface-variant">{{ $sources->total() }}</span></h2>
                    <span class="font-mono text-[10px] text-outline">Ưu tiên cao → thấp</span>
                </div>
                @forelse ($sources as $source)
                    <details class="group overflow-hidden rounded-lg border border-outline-variant/50 bg-surface-container-low transition-colors open:border-primary/40">
                        <summary class="flex cursor-pointer list-none items-start gap-3 p-4 outline-none transition hover:bg-surface-container focus-visible:bg-surface-container [&::-webkit-details-marker]:hidden sm:gap-4 sm:p-5">
                            <span class="flex size-10 shrink-0 items-center justify-center rounded-lg border border-primary/20 bg-primary/10 text-primary"><span class="material-symbols-outlined text-xl" aria-hidden="true">{{ $source->source_type === 'RSS' ? 'rss_feed' : ($source->source_type === 'API' ? 'data_object' : 'language') }}</span></span>
                            <div class="min-w-0 flex-1 space-y-2">
                                <div class="flex flex-wrap items-center gap-2">
                                    <h3 class="break-words text-sm font-semibold">{{ $source->name }}</h3>
                                    <span class="rounded border border-outline-variant px-1.5 py-0.5 font-mono text-[9px] text-outline">{{ $source->source_type }}</span>
                                    <span @class(['inline-flex items-center gap-1.5 rounded-full px-2 py-0.5 text-[10px]', 'bg-tertiary/10 text-tertiary' => $source->status === 'ACTIVE', 'bg-surface-container-high text-outline' => $source->status !== 'ACTIVE'])><span class="size-1.5 rounded-full bg-current"></span>{{ $source->status === 'ACTIVE' ? 'Hoạt động' : 'Tạm dừng' }}</span>
                                </div>
                                <p class="truncate font-mono text-[11px] text-outline">{{ $source->site_url }}</p>
                                <div class="flex flex-wrap gap-x-4 gap-y-1 text-xs text-on-surface-variant">
                                    <span>{{ $source->articles_count }} bài thu thập</span>
                                    <span>Tin cậy <strong class="font-medium text-on-surface">{{ $source->trust_score }}/10</strong></span>
                                    <span>Ưu tiên <strong class="font-medium text-on-surface">{{ $source->priority }}/10</strong></span>
                                </div>
                                <p class="text-[11px] text-outline">Thu thập gần nhất: {{ $source->last_fetched_at?->format('d/m/Y H:i') ?? 'Chưa chạy' }}</p>
                            </div>
                            <span class="material-symbols-outlined mt-1 text-lg text-outline transition-transform group-open:rotate-180" aria-hidden="true">expand_more</span>
                        </summary>
                        <form data-ai-form data-reload="true" data-method="PUT" method="post" action="{{ route('admin.ai-sources.update', $source) }}" class="space-y-4 border-t border-outline-variant/50 bg-surface-container-lowest/40 p-4 sm:p-5">
                            @csrf
                            @include('admin.ai-weekly.source-fields', ['source' => $source])
                            <div class="flex flex-wrap items-center justify-between gap-3 border-t border-outline-variant/50 pt-4">
                                <p data-ai-feedback role="status" class="text-xs text-on-surface-variant"></p>
                                <button type="submit" class="inline-flex items-center gap-2 rounded bg-primary-container px-4 py-2 text-sm font-semibold text-on-primary transition hover:bg-primary disabled:cursor-wait disabled:opacity-50"><span class="material-symbols-outlined text-base" aria-hidden="true">save</span>Lưu thay đổi</button>
                            </div>
                        </form>
                    </details>
                @empty
                    <div class="rounded-lg border border-dashed border-outline-variant bg-surface-container-low px-6 py-14 text-center">
                        <span class="material-symbols-outlined mb-3 text-4xl text-primary" aria-hidden="true">hub</span>
                        <h3 class="font-semibold">Bắt đầu với nguồn tin đầu tiên</h3>
                        <p class="mx-auto mt-2 max-w-sm text-sm leading-6 text-on-surface-variant">Thêm RSS, API hoặc website để tổ chức thư viện nguồn của bạn.</p>
                        <a href="#new-source" class="mt-4 inline-flex items-center gap-1 text-sm font-medium text-primary">Thêm nguồn<span class="material-symbols-outlined text-base" aria-hidden="true">arrow_forward</span></a>
                    </div>
                @endforelse
                {{ $sources->links() }}
            </section>

            <aside id="new-source" class="scroll-mt-24 rounded-lg border border-outline-variant/50 bg-surface-container-low xl:sticky xl:top-20">
                <div class="flex items-start gap-3 border-b border-outline-variant/50 p-5">
                    <span class="material-symbols-outlined text-xl text-primary" aria-hidden="true">add_circle</span>
                    <div><h2 class="font-semibold">Thêm nguồn mới</h2><p class="mt-1 text-xs leading-5 text-outline">Kết nối một nguồn nội dung vào thư viện.</p></div>
                </div>
                <form data-ai-form data-reload="true" method="post" action="{{ route('admin.ai-sources.store') }}" class="space-y-5 p-5">
                    @csrf
                    @include('admin.ai-weekly.source-fields', ['source' => null])
                    <p class="rounded border border-outline-variant/50 bg-surface-container-lowest p-3 text-xs leading-5 text-on-surface-variant">RSS và API hỗ trợ thu thập tự động. Website hiện chỉ được lưu để quản lý nguồn.</p>
                    <button type="submit" class="inline-flex w-full items-center justify-center gap-2 rounded bg-primary-container px-4 py-2.5 text-sm font-semibold text-on-primary transition hover:bg-primary disabled:cursor-wait disabled:opacity-50"><span class="material-symbols-outlined text-lg" aria-hidden="true">add</span>Thêm nguồn</button>
                    <p data-ai-feedback role="status" class="text-xs text-on-surface-variant"></p>
                </form>
            </aside>
        </div>
    </div>
</div>
@endsection
