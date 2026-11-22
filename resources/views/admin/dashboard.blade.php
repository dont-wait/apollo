@extends('layouts.admin')

@section('title', 'Admin Dashboard - Apollo')
@section('page-title', 'Dashboard')

@section('content')
    <div class="-m-8 flex min-h-full flex-col gap-6 bg-[#111319] p-4 text-[#e1e2ea] sm:p-8">
        <section class="flex flex-col justify-between gap-4 sm:flex-row sm:items-end">
            <div class="flex flex-col gap-2">
                <h1 class="text-3xl font-semibold tracking-tight text-[#e1e2ea]">Tổng quan Apollo</h1>
                <p class="text-sm text-[#bdc8d1]">
                    Thống kê bài viết, tương tác và thành viên. Số liệu mới tính từ {{ $periodStart->format('d/m/Y') }} (30 ngày gần đây).
                </p>
            </div>
            <a href="{{ route('admin.articles.create') }}" class="inline-flex w-fit items-center gap-2 rounded-lg bg-[#38bdf8] px-4 py-2 text-sm font-semibold text-[#00354a] transition-colors hover:bg-[#7bd0ff]">
                <i class="fa-solid fa-circle-plus" aria-hidden="true"></i>
                <span>Tạo bài viết</span>
            </a>
        </section>

        <section class="grid grid-cols-1 gap-4 sm:grid-cols-2 xl:grid-cols-5" aria-label="Thống kê tổng quan">
            <article class="flex flex-col gap-4 rounded-xl bg-[#191c21] p-5 shadow-sm">
                <div class="flex items-center justify-between text-[#bdc8d1]">
                    <span class="text-xs uppercase tracking-wider">Bài viết</span>
                    <i class="fa-regular fa-newspaper text-lg text-[#8ed5ff]" aria-hidden="true"></i>
                </div>
                <div class="text-3xl font-semibold">{{ number_format($postStats['total']) }}</div>
                <dl class="grid grid-cols-1 gap-y-2 text-xs">
                    <div class="grid grid-cols-[minmax(0,1fr)_auto] items-center gap-x-4"><dt class="min-w-0 text-[#bdc8d1]">Bản nháp</dt><dd class="m-0 shrink-0 text-right font-medium tabular-nums">{{ number_format($postStats['draft']) }}</dd></div>
                    <div class="grid grid-cols-[minmax(0,1fr)_auto] items-center gap-x-4"><dt class="min-w-0 text-[#bdc8d1]">Chờ duyệt</dt><dd class="m-0 shrink-0 text-right font-medium tabular-nums">{{ number_format($postStats['pending']) }}</dd></div>
                    <div class="grid grid-cols-[minmax(0,1fr)_auto] items-center gap-x-4"><dt class="min-w-0 text-[#bdc8d1]">Lên lịch</dt><dd class="m-0 shrink-0 text-right font-medium tabular-nums">{{ number_format($postStats['scheduled']) }}</dd></div>
                    <div class="grid grid-cols-[minmax(0,1fr)_auto] items-center gap-x-4"><dt class="min-w-0 text-[#bdc8d1]">Đã xuất bản</dt><dd class="m-0 shrink-0 text-right font-medium tabular-nums">{{ number_format($postStats['published']) }}</dd></div>
                </dl>
            </article>

            <article class="flex flex-col gap-4 rounded-xl bg-[#191c21] p-5 shadow-sm">
                <div class="flex items-center justify-between text-[#bdc8d1]">
                    <span class="text-xs uppercase tracking-wider">Lượt xem tích lũy</span>
                    <i class="fa-regular fa-eye text-lg text-[#8ed5ff]" aria-hidden="true"></i>
                </div>
                <div class="text-3xl font-semibold">{{ number_format($postStats['total_views']) }}</div>
                <p class="text-xs text-[#bdc8d1]">Tổng lượt xem trên tất cả bài viết.</p>
            </article>

            <article class="flex flex-col gap-4 rounded-xl bg-[#191c21] p-5 shadow-sm">
                <div class="flex items-center justify-between text-[#bdc8d1]">
                    <span class="text-xs uppercase tracking-wider">Bình luận</span>
                    <i class="fa-regular fa-comments text-lg text-[#c0c1ff]" aria-hidden="true"></i>
                </div>
                <div class="text-3xl font-semibold">{{ number_format($commentStats['total']) }}</div>
                <dl class="grid grid-cols-1 gap-y-2 text-xs">
                    <div class="grid grid-cols-[minmax(0,1fr)_auto] items-center gap-x-4"><dt class="min-w-0 text-[#bdc8d1]">30 ngày qua</dt><dd class="m-0 shrink-0 text-right font-medium tabular-nums">{{ number_format($commentStats['recent']) }}</dd></div>
                    <div class="grid grid-cols-[minmax(0,1fr)_auto] items-center gap-x-4"><dt class="min-w-0 text-[#bdc8d1]">Chờ duyệt</dt><dd class="m-0 shrink-0 text-right font-medium tabular-nums">{{ number_format($commentStats['pending']) }}</dd></div>
                    <div class="grid grid-cols-[minmax(0,1fr)_auto] items-center gap-x-4"><dt class="min-w-0 text-[#bdc8d1]">Đã ẩn</dt><dd class="m-0 shrink-0 text-right font-medium tabular-nums">{{ number_format($commentStats['hidden']) }}</dd></div>
                </dl>
            </article>

            <article class="flex flex-col gap-4 rounded-xl bg-[#191c21] p-5 shadow-sm">
                <div class="flex items-center justify-between text-[#bdc8d1]">
                    <span class="text-xs uppercase tracking-wider">Thành viên</span>
                    <i class="fa-solid fa-users text-lg text-[#56e5a9]" aria-hidden="true"></i>
                </div>
                <div class="text-3xl font-semibold">{{ number_format($userStats['total']) }}</div>
                <dl class="grid grid-cols-1 gap-y-2 text-xs">
                    <div class="grid grid-cols-[minmax(0,1fr)_auto] items-center gap-x-4"><dt class="min-w-0 text-[#bdc8d1]">Đăng ký mới</dt><dd class="m-0 shrink-0 text-right font-medium tabular-nums">{{ number_format($userStats['recent']) }}</dd></div>
                    <div class="grid grid-cols-[minmax(0,1fr)_auto] items-center gap-x-4"><dt class="min-w-0 text-[#bdc8d1]">Đang hoạt động</dt><dd class="m-0 shrink-0 text-right font-medium tabular-nums">{{ number_format($userStats['active']) }}</dd></div>
                    <div class="grid grid-cols-[minmax(0,1fr)_auto] items-center gap-x-4"><dt class="min-w-0 text-[#bdc8d1]">Không hoạt động</dt><dd class="m-0 shrink-0 text-right font-medium tabular-nums">{{ number_format($userStats['inactive']) }}</dd></div>
                </dl>
            </article>

            <article class="flex flex-col gap-4 rounded-xl bg-[#191c21] p-5 shadow-sm">
                <div class="flex items-center justify-between text-[#bdc8d1]">
                    <span class="text-xs uppercase tracking-wider">Lượt lưu</span>
                    <i class="fa-regular fa-bookmark text-lg text-[#c0c1ff]" aria-hidden="true"></i>
                </div>
                <div class="text-3xl font-semibold">{{ number_format($totalBookmarks) }}</div>
                <p class="text-xs text-[#bdc8d1]">Tổng lượt lưu bài viết của thành viên.</p>
            </article>
        </section>

        <section class="grid grid-cols-1 gap-4 xl:grid-cols-2" aria-label="Bài viết dẫn đầu">
            <article class="overflow-hidden rounded-xl bg-[#191c21] shadow-sm">
                <div class="flex items-center justify-between gap-3 p-5">
                    <div>
                        <h2 class="text-lg font-semibold">Bài viết nhiều lượt xem</h2>
                        <p class="text-xs text-[#bdc8d1]">Xếp hạng theo lượt xem tích lũy.</p>
                    </div>
                    <i class="fa-regular fa-eye text-lg text-[#8ed5ff]" aria-hidden="true"></i>
                </div>
                <ol class="divide-y divide-[#272a30]">
                    @forelse ($topViewedPosts as $post)
                        <li class="flex items-center justify-between gap-4 px-5 py-3">
                            <a class="min-w-0 truncate text-sm font-medium text-[#e1e2ea] transition-colors hover:text-[#8ed5ff]" href="{{ route('admin.articles.edit', $post) }}">{{ $post->title }}</a>
                            <span class="shrink-0 font-mono text-xs text-[#bdc8d1]">{{ number_format($post->view_count) }}</span>
                        </li>
                    @empty
                        <li class="px-5 py-4 text-sm text-[#bdc8d1]">Chưa có bài viết nào.</li>
                    @endforelse
                </ol>
            </article>

            <article class="overflow-hidden rounded-xl bg-[#191c21] shadow-sm">
                <div class="flex items-center justify-between gap-3 p-5">
                    <div>
                        <h2 class="text-lg font-semibold">Bài viết được lưu nhiều nhất</h2>
                        <p class="text-xs text-[#bdc8d1]">Số thành viên đã lưu từng bài viết.</p>
                    </div>
                    <i class="fa-regular fa-bookmark text-lg text-[#c0c1ff]" aria-hidden="true"></i>
                </div>
                <ol class="divide-y divide-[#272a30]">
                    @forelse ($topBookmarkedPosts as $post)
                        <li class="flex items-center justify-between gap-4 px-5 py-3">
                            <a class="min-w-0 truncate text-sm font-medium text-[#e1e2ea] transition-colors hover:text-[#8ed5ff]" href="{{ route('admin.articles.edit', $post) }}">{{ $post->title }}</a>
                            <span class="shrink-0 font-mono text-xs text-[#bdc8d1]">{{ number_format($post->bookmarks_count) }}</span>
                        </li>
                    @empty
                        <li class="px-5 py-4 text-sm text-[#bdc8d1]">Chưa có bài viết được lưu.</li>
                    @endforelse
                </ol>
            </article>
        </section>

        <section class="overflow-hidden rounded-xl bg-[#191c21] shadow-sm">
            <div class="flex flex-col justify-between gap-3 p-5 sm:flex-row sm:items-center">
                <div>
                    <h2 class="text-lg font-semibold">Bài viết cập nhật gần đây</h2>
                    <p class="text-xs text-[#bdc8d1]">Hiển thị {{ $recentPosts->count() }} trong tổng số {{ number_format($postStats['total']) }} bài viết.</p>
                </div>
                <a class="w-fit rounded-lg bg-[#1d2025] px-3 py-2 text-xs font-medium text-[#e1e2ea] transition-colors hover:bg-[#36393f]" href="{{ route('admin.articles.index') }}">Tất cả bài viết</a>
            </div>

            <div class="w-full overflow-x-auto">
                <table class="w-full min-w-[760px] border-collapse text-left">
                    <thead>
                        <tr class="bg-[#0b0e13] text-[10px] uppercase tracking-wider text-[#bdc8d1]">
                            <th class="px-5 py-3">Bài viết</th>
                            <th class="px-4 py-3">Chuyên mục / Tác giả</th>
                            <th class="px-4 py-3">Trạng thái</th>
                            <th class="px-4 py-3 text-right">Lượt xem</th>
                            <th class="px-4 py-3 text-right">Lượt lưu</th>
                            <th class="px-5 py-3 text-right">Cập nhật</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-[#272a30] text-xs">
                        @forelse ($recentPosts as $post)
                            @php
                                $statusLabel = match ($post->status) {
                                    'DRAFT' => 'Bản nháp',
                                    'REVIEW' => 'Chờ duyệt',
                                    'SCHEDULED' => 'Đã lên lịch',
                                    'PUBLISHED' => 'Đã xuất bản',
                                    default => 'Lưu trữ',
                                };
                                $statusClass = match ($post->status) {
                                    'PUBLISHED' => 'text-[#56e5a9]',
                                    'REVIEW' => 'text-[#c0c1ff]',
                                    'SCHEDULED' => 'text-[#8ed5ff]',
                                    default => 'text-[#bdc8d1]',
                                };
                            @endphp
                            <tr class="transition-colors hover:bg-[#1d2025]/50">
                                <td class="max-w-sm px-5 py-4">
                                    <a class="block truncate font-semibold text-[#e1e2ea] transition-colors hover:text-[#8ed5ff]" href="{{ $post->isPublished() ? route('articles.show', ['article' => $post->slug]) : route('admin.articles.edit', $post) }}">{{ $post->title }}</a>
                                    <span class="mt-1 block truncate text-[10px] text-[#bdc8d1]">{{ $post->excerpt }}</span>
                                </td>
                                <td class="whitespace-nowrap px-4 py-4">
                                    <span class="block text-[#e1e2ea]">{{ $post->category?->name ?? '—' }}</span>
                                    <span class="mt-1 block text-[10px] text-[#bdc8d1]">{{ $post->author?->name ?? '—' }}</span>
                                </td>
                                <td class="whitespace-nowrap px-4 py-4">
                                    <span class="rounded bg-[#272a30] px-2 py-1 text-[10px] {{ $statusClass }}">{{ $statusLabel }}</span>
                                </td>
                                <td class="whitespace-nowrap px-4 py-4 text-right font-mono">{{ number_format($post->view_count) }}</td>
                                <td class="whitespace-nowrap px-4 py-4 text-right font-mono">{{ number_format($post->bookmarks_count) }}</td>
                                <td class="whitespace-nowrap px-5 py-4 text-right text-[#bdc8d1]">{{ $post->updated_at?->format('d/m/Y H:i') }}</td>
                            </tr>
                        @empty
                            <tr>
                                <td class="px-5 py-8 text-center text-sm text-[#bdc8d1]" colspan="6">Chưa có bài viết nào.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </section>
    </div>
@endsection
