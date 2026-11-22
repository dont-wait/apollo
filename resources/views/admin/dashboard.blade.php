@extends('layouts.admin')

@section('title', 'Admin Dashboard - Apollo')
@section('page-title', 'Dashboard')

@section('content')
    <div class="-m-8 flex min-h-full flex-col gap-6 bg-background p-4 text-on-surface sm:p-8">
        <section class="flex flex-col justify-between gap-4 sm:flex-row sm:items-end">
            <div class="flex flex-col gap-2">
                <h1 class="text-3xl font-semibold tracking-tight text-on-surface">Tổng quan Apollo</h1>
                <p class="text-sm text-on-surface-variant">
                    Thống kê bài viết, tương tác và thành viên. Số liệu mới tính từ {{ $periodStart->format('d/m/Y') }} (30 ngày gần đây).
                </p>
            </div>
            <a href="{{ route('admin.articles.create') }}" class="inline-flex w-fit items-center gap-2 rounded-lg bg-primary-container px-4 py-2 text-sm font-semibold text-on-primary transition-colors hover:bg-primary-fixed-dim">
                <i class="fa-solid fa-circle-plus" aria-hidden="true"></i>
                <span>Tạo bài viết</span>
            </a>
        </section>

        <section class="grid grid-cols-1 gap-4 sm:grid-cols-2 xl:grid-cols-5" aria-label="Thống kê tổng quan">
            <article class="flex flex-col gap-4 rounded-xl bg-surface-low p-5 shadow-sm">
                <div class="flex items-center justify-between text-on-surface-variant">
                    <span class="text-xs uppercase tracking-wider">Bài viết</span>
                    <i class="fa-regular fa-newspaper text-lg text-primary" aria-hidden="true"></i>
                </div>
                <div class="text-3xl font-semibold">{{ number_format($postStats['total']) }}</div>
                <dl class="grid grid-cols-1 gap-y-2 text-xs">
                    <div class="grid grid-cols-[minmax(0,1fr)_auto] items-center gap-x-4"><dt class="min-w-0 text-on-surface-variant">Bản nháp</dt><dd class="m-0 shrink-0 text-right font-medium tabular-nums">{{ number_format($postStats['draft']) }}</dd></div>
                    <div class="grid grid-cols-[minmax(0,1fr)_auto] items-center gap-x-4"><dt class="min-w-0 text-on-surface-variant">Chờ duyệt</dt><dd class="m-0 shrink-0 text-right font-medium tabular-nums">{{ number_format($postStats['pending']) }}</dd></div>
                    <div class="grid grid-cols-[minmax(0,1fr)_auto] items-center gap-x-4"><dt class="min-w-0 text-on-surface-variant">Lên lịch</dt><dd class="m-0 shrink-0 text-right font-medium tabular-nums">{{ number_format($postStats['scheduled']) }}</dd></div>
                    <div class="grid grid-cols-[minmax(0,1fr)_auto] items-center gap-x-4"><dt class="min-w-0 text-on-surface-variant">Đã xuất bản</dt><dd class="m-0 shrink-0 text-right font-medium tabular-nums">{{ number_format($postStats['published']) }}</dd></div>
                </dl>
            </article>

            <article class="flex flex-col gap-4 rounded-xl bg-surface-low p-5 shadow-sm">
                <div class="flex items-center justify-between text-on-surface-variant">
                    <span class="text-xs uppercase tracking-wider">Lượt xem tích lũy</span>
                    <i class="fa-regular fa-eye text-lg text-primary" aria-hidden="true"></i>
                </div>
                <div class="text-3xl font-semibold">{{ number_format($postStats['total_views']) }}</div>
                <p class="text-xs text-on-surface-variant">Tổng lượt xem trên tất cả bài viết.</p>
            </article>

            <article class="flex flex-col gap-4 rounded-xl bg-surface-low p-5 shadow-sm">
                <div class="flex items-center justify-between text-on-surface-variant">
                    <span class="text-xs uppercase tracking-wider">Bình luận</span>
                    <i class="fa-regular fa-comments text-lg text-secondary" aria-hidden="true"></i>
                </div>
                <div class="text-3xl font-semibold">{{ number_format($commentStats['total']) }}</div>
                <dl class="grid grid-cols-1 gap-y-2 text-xs">
                    <div class="grid grid-cols-[minmax(0,1fr)_auto] items-center gap-x-4"><dt class="min-w-0 text-on-surface-variant">30 ngày qua</dt><dd class="m-0 shrink-0 text-right font-medium tabular-nums">{{ number_format($commentStats['recent']) }}</dd></div>
                    <div class="grid grid-cols-[minmax(0,1fr)_auto] items-center gap-x-4"><dt class="min-w-0 text-on-surface-variant">Chờ duyệt</dt><dd class="m-0 shrink-0 text-right font-medium tabular-nums">{{ number_format($commentStats['pending']) }}</dd></div>
                    <div class="grid grid-cols-[minmax(0,1fr)_auto] items-center gap-x-4"><dt class="min-w-0 text-on-surface-variant">Đã ẩn</dt><dd class="m-0 shrink-0 text-right font-medium tabular-nums">{{ number_format($commentStats['hidden']) }}</dd></div>
                </dl>
            </article>

            <article class="flex flex-col gap-4 rounded-xl bg-surface-low p-5 shadow-sm">
                <div class="flex items-center justify-between text-on-surface-variant">
                    <span class="text-xs uppercase tracking-wider">Thành viên</span>
                    <i class="fa-solid fa-users text-lg text-tertiary" aria-hidden="true"></i>
                </div>
                <div class="text-3xl font-semibold">{{ number_format($userStats['total']) }}</div>
                <dl class="grid grid-cols-1 gap-y-2 text-xs">
                    <div class="grid grid-cols-[minmax(0,1fr)_auto] items-center gap-x-4"><dt class="min-w-0 text-on-surface-variant">Đăng ký mới</dt><dd class="m-0 shrink-0 text-right font-medium tabular-nums">{{ number_format($userStats['recent']) }}</dd></div>
                    <div class="grid grid-cols-[minmax(0,1fr)_auto] items-center gap-x-4"><dt class="min-w-0 text-on-surface-variant">Đang hoạt động</dt><dd class="m-0 shrink-0 text-right font-medium tabular-nums">{{ number_format($userStats['active']) }}</dd></div>
                    <div class="grid grid-cols-[minmax(0,1fr)_auto] items-center gap-x-4"><dt class="min-w-0 text-on-surface-variant">Không hoạt động</dt><dd class="m-0 shrink-0 text-right font-medium tabular-nums">{{ number_format($userStats['inactive']) }}</dd></div>
                </dl>
            </article>

            <article class="flex flex-col gap-4 rounded-xl bg-surface-low p-5 shadow-sm">
                <div class="flex items-center justify-between text-on-surface-variant">
                    <span class="text-xs uppercase tracking-wider">Lượt lưu</span>
                    <i class="fa-regular fa-bookmark text-lg text-secondary" aria-hidden="true"></i>
                </div>
                <div class="text-3xl font-semibold">{{ number_format($totalBookmarks) }}</div>
                <p class="text-xs text-on-surface-variant">Tổng lượt lưu bài viết của thành viên.</p>
            </article>
        </section>

        <section class="grid grid-cols-1 gap-4 xl:grid-cols-2" aria-label="Bài viết dẫn đầu">
            <article class="overflow-hidden rounded-xl bg-surface-low shadow-sm">
                <div class="flex items-center justify-between gap-3 p-5">
                    <div>
                        <h2 class="text-lg font-semibold">Bài viết nhiều lượt xem</h2>
                        <p class="text-xs text-on-surface-variant">Xếp hạng theo lượt xem tích lũy.</p>
                    </div>
                    <i class="fa-regular fa-eye text-lg text-primary" aria-hidden="true"></i>
                </div>
                <ol class="divide-y divide-surface-high">
                    @forelse ($topViewedPosts as $post)
                        <li class="flex items-center justify-between gap-4 px-5 py-3">
                            <a class="min-w-0 truncate text-sm font-medium text-on-surface transition-colors hover:text-primary" href="{{ route('admin.articles.edit', $post) }}">{{ $post->title }}</a>
                            <span class="shrink-0 font-mono text-xs text-on-surface-variant">{{ number_format($post->view_count) }}</span>
                        </li>
                    @empty
                        <li class="px-5 py-4 text-sm text-on-surface-variant">Chưa có bài viết nào.</li>
                    @endforelse
                </ol>
            </article>

            <article class="overflow-hidden rounded-xl bg-surface-low shadow-sm">
                <div class="flex items-center justify-between gap-3 p-5">
                    <div>
                        <h2 class="text-lg font-semibold">Bài viết được lưu nhiều nhất</h2>
                        <p class="text-xs text-on-surface-variant">Số thành viên đã lưu từng bài viết.</p>
                    </div>
                    <i class="fa-regular fa-bookmark text-lg text-secondary" aria-hidden="true"></i>
                </div>
                <ol class="divide-y divide-surface-high">
                    @forelse ($topBookmarkedPosts as $post)
                        <li class="flex items-center justify-between gap-4 px-5 py-3">
                            <a class="min-w-0 truncate text-sm font-medium text-on-surface transition-colors hover:text-primary" href="{{ route('admin.articles.edit', $post) }}">{{ $post->title }}</a>
                            <span class="shrink-0 font-mono text-xs text-on-surface-variant">{{ number_format($post->bookmarks_count) }}</span>
                        </li>
                    @empty
                        <li class="px-5 py-4 text-sm text-on-surface-variant">Chưa có bài viết được lưu.</li>
                    @endforelse
                </ol>
            </article>
        </section>

        <section class="overflow-hidden rounded-xl bg-surface-low shadow-sm">
            <div class="flex flex-col justify-between gap-3 p-5 sm:flex-row sm:items-center">
                <div>
                    <h2 class="text-lg font-semibold">Bài viết cập nhật gần đây</h2>
                    <p class="text-xs text-on-surface-variant">Hiển thị {{ $recentPosts->count() }} trong tổng số {{ number_format($postStats['total']) }} bài viết.</p>
                </div>
                <a class="w-fit rounded-lg bg-surface px-3 py-2 text-xs font-medium text-on-surface transition-colors hover:bg-surface-bright" href="{{ route('admin.articles.index') }}">Tất cả bài viết</a>
            </div>

            <div class="w-full overflow-x-auto">
                <table class="w-full min-w-[760px] border-collapse text-left">
                    <thead>
                        <tr class="bg-surface-lowest text-[10px] uppercase tracking-wider text-on-surface-variant">
                            <th class="px-5 py-3">Bài viết</th>
                            <th class="px-4 py-3">Chuyên mục / Tác giả</th>
                            <th class="px-4 py-3">Trạng thái</th>
                            <th class="px-4 py-3 text-right">Lượt xem</th>
                            <th class="px-4 py-3 text-right">Lượt lưu</th>
                            <th class="px-5 py-3 text-right">Cập nhật</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-surface-high text-xs">
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
                                    'PUBLISHED' => 'text-tertiary',
                                    'REVIEW' => 'text-secondary',
                                    'SCHEDULED' => 'text-primary',
                                    default => 'text-on-surface-variant',
                                };
                            @endphp
                            <tr class="transition-colors hover:bg-surface/50">
                                <td class="max-w-sm px-5 py-4">
                                    <a class="block truncate font-semibold text-on-surface transition-colors hover:text-primary" href="{{ $post->isPublished() ? route('articles.show', ['article' => $post->slug]) : route('admin.articles.edit', $post) }}">{{ $post->title }}</a>
                                    <span class="mt-1 block truncate text-[10px] text-on-surface-variant">{{ $post->excerpt }}</span>
                                </td>
                                <td class="whitespace-nowrap px-4 py-4">
                                    <span class="block text-on-surface">{{ $post->category?->name ?? '—' }}</span>
                                    <span class="mt-1 block text-[10px] text-on-surface-variant">{{ $post->author?->name ?? '—' }}</span>
                                </td>
                                <td class="whitespace-nowrap px-4 py-4">
                                    <span class="rounded bg-surface-high px-2 py-1 text-[10px] {{ $statusClass }}">{{ $statusLabel }}</span>
                                </td>
                                <td class="whitespace-nowrap px-4 py-4 text-right font-mono">{{ number_format($post->view_count) }}</td>
                                <td class="whitespace-nowrap px-4 py-4 text-right font-mono">{{ number_format($post->bookmarks_count) }}</td>
                                <td class="whitespace-nowrap px-5 py-4 text-right text-on-surface-variant">{{ $post->updated_at?->format('d/m/Y H:i') }}</td>
                            </tr>
                        @empty
                            <tr>
                                <td class="px-5 py-8 text-center text-sm text-on-surface-variant" colspan="6">Chưa có bài viết nào.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </section>
    </div>
@endsection
