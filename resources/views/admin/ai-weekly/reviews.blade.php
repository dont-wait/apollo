@extends('layouts.admin')

@section('title', 'AI Weekly Reviews - Apollo Admin')

@section('content')
<div class="-m-8 min-h-screen bg-[#111319] p-4 text-[#e1e2ea] sm:p-8">
    <div class="mx-auto max-w-[1440px] space-y-6">
        @include('admin.ai-weekly.nav')
        <header class="flex flex-col justify-between gap-4 lg:flex-row lg:items-end">
            <div>
                <p class="font-label-sm text-label-sm uppercase tracking-widest text-[#8ed5ff]">AI Automation / Editorial</p>
                <h1 class="mt-2 font-headline-sm text-3xl font-semibold tracking-tight">AI Weekly Reviews</h1>
                <p class="mt-2 text-sm text-[#bdc8d1]">Tạo và biên tập bản tổng hợp trước khi chuyển thành Post draft.</p>
            </div>
            <form data-ai-form method="post" action="{{ route('admin.weekly-reviews.generate') }}" class="flex flex-wrap items-end gap-3 rounded border border-[#3e484f]/50 bg-[#1d2025] p-4">
                @csrf
                <label class="grid gap-1.5 font-label-sm text-label-sm text-[#bdc8d1]">Thứ Hai bắt đầu tuần
                    <input name="week_start" type="date" required value="{{ now()->timezone(config('ai_weekly.timezone'))->startOfWeek()->subWeek()->toDateString() }}" class="min-h-9 rounded border border-[#3e484f] bg-[#111319] px-3 text-[#e1e2ea] focus:border-[#38bdf8]">
                </label>
                <button type="submit" class="inline-flex min-h-9 items-center gap-2 rounded bg-[#38bdf8] px-4 text-sm font-semibold text-[#00354a] hover:bg-[#8ed5ff]"><span class="material-symbols-outlined text-base">auto_awesome</span>Tạo review</button>
                <span data-ai-feedback role="status" class="w-full text-xs text-[#bdc8d1]"></span>
            </form>
        </header>
        <section class="space-y-3" aria-label="Các bản review">
            @forelse ($reviews as $review)
                <a href="{{ route('admin.weekly-reviews.show', $review) }}" class="group block rounded border border-[#3e484f]/50 bg-[#1d2025] p-5 transition-colors hover:border-[#38bdf8]/60 hover:bg-[#272a30]">
                    <div class="flex flex-wrap items-center gap-3 font-label-sm text-label-sm">
                        <span class="text-[#8ed5ff]">{{ $review->week_start->format('d/m/Y') }} – {{ $review->week_end->format('d/m/Y') }}</span>
                        <span @class([
                            'rounded border px-2 py-0.5',
                            'border-amber-500/30 bg-amber-500/10 text-amber-300' => $review->status === 'REVIEW',
                            'border-emerald-500/30 bg-emerald-500/10 text-emerald-300' => $review->status === 'CONVERTED',
                            'border-[#3e484f] text-[#bdc8d1]' => ! in_array($review->status, ['REVIEW', 'CONVERTED'], true),
                        ])>{{ $review->status }}</span>
                        <span class="text-[#87929a]">{{ $review->source_articles_count }} nguồn · revision {{ $review->revision }}</span>
                    </div>
                    <div class="mt-3 flex items-start justify-between gap-4"><h2 class="font-headline-sm text-lg font-semibold group-hover:text-[#8ed5ff]">{{ $review->title }}</h2><span class="material-symbols-outlined text-[#8ed5ff]">arrow_forward</span></div>
                    @if ($review->excerpt)<p class="mt-2 line-clamp-2 text-sm leading-6 text-[#bdc8d1]">{{ $review->excerpt }}</p>@endif
                </a>
            @empty
                <div class="rounded border border-dashed border-[#3e484f] bg-[#1d2025] p-10 text-center text-sm text-[#bdc8d1]">Chưa có review nào. Chọn thứ Hai bắt đầu tuần để tạo bản đầu tiên.</div>
            @endforelse
        </section>
        {{ $reviews->links() }}
    </div>
</div>
@endsection
