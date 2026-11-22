@extends('layouts.admin')

@section('title', 'Toxic Words - Apollo Admin')

@section('content')
<div class="-m-8 min-h-screen bg-background p-4 text-on-surface sm:p-8" data-toxic-words-page>
    <header class="mb-6 flex flex-col justify-between gap-4 xl:flex-row xl:items-end">
        <div>
            <p class="text-[11px] uppercase tracking-[0.16em] text-outline">Community // Safety</p>
            <h1 class="mt-2 text-3xl font-semibold tracking-tight">Toxic Word Dictionary</h1>
            <p class="mt-2 max-w-3xl text-sm text-on-surface-variant">
                Quản lý từ ngữ bị chặn khi tạo bài viết, tag, category, bình luận và tên người dùng.
            </p>
        </div>
        <a href="{{ route('admin.categories.index') }}" class="inline-flex items-center gap-2 rounded border border-outline-variant px-4 py-2 text-sm text-on-surface-variant transition hover:bg-surface-high">
            <span class="material-symbols-outlined text-base">arrow_back</span>
            Taxonomy
        </a>
    </header>

    @if (session('status'))
        <div role="status" class="mb-4 rounded border border-emerald-500/30 bg-emerald-500/10 px-4 py-3 text-sm text-emerald-200">
            {{ session('status') }}
        </div>
    @endif

    @if ($errors->any())
        <div role="alert" class="mb-4 rounded border border-red-500/30 bg-red-500/10 px-4 py-3 text-sm text-red-200">
            <ul class="list-inside list-disc space-y-1">
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <div class="grid gap-6 xl:grid-cols-[minmax(0,1fr)_380px]">
        <section class="min-w-0 space-y-4">
            <details class="rounded border border-surface-high bg-surface-low" @if (session('import_errors') || $errors->has('file')) open @endif>
                <summary class="flex cursor-pointer items-center gap-2 px-4 py-3 text-sm font-medium text-on-surface">
                    <span class="material-symbols-outlined text-base text-primary">upload_file</span>
                    Import từ Excel / CSV
                </summary>
                <div class="space-y-3 border-t border-surface-highest px-4 py-4">
                    <p class="text-xs leading-5 text-outline">
                        File <code>.xlsx</code> hoặc <code>.csv</code> (tối đa 2MB, 2000 dòng). Dòng đầu là tiêu đề, bắt buộc có cột <code>term</code>.
                        Cột tuỳ chọn: <code>scopes</code> (cách nhau bằng dấu phẩy; trống = tất cả), <code>match_mode</code> (WORD/LOOSE),
                        <code>fold_diacritics</code>, <code>is_active</code>, <code>note</code>. Từ đã có trong từ điển sẽ được bỏ qua. Dòng có <code>term</code> bắt đầu bằng <code>#</code> được coi là ghi chú và không import. CSV dùng dấu phẩy, chấm phẩy hoặc tab đều được.
                    </p>
                    <form method="POST" action="{{ route('admin.toxic-words.import') }}" enctype="multipart/form-data" class="flex flex-wrap items-center gap-3">
                        @csrf
                        <label class="sr-only" for="toxic-word-import-file">File import</label>
                        <input id="toxic-word-import-file" type="file" name="file" accept=".xlsx,.csv" required class="min-w-0 flex-1 rounded border border-surface-highest bg-surface-lowest px-3 py-2 text-sm text-on-surface-variant file:mr-3 file:rounded file:border-0 file:bg-surface-high file:px-3 file:py-1 file:text-on-surface">
                        <button type="submit" class="inline-flex min-h-10 items-center gap-2 rounded bg-primary-container px-4 text-sm font-semibold text-surface-lowest transition hover:bg-primary">
                            <span class="material-symbols-outlined text-base">upload</span>
                            Import
                        </button>
                        <a href="{{ route('admin.toxic-words.import-template') }}" class="inline-flex min-h-10 items-center gap-2 rounded border border-outline-variant px-4 text-sm text-on-surface-variant transition hover:bg-surface-high">
                            <span class="material-symbols-outlined text-base">download</span>
                            Tải file mẫu
                        </a>
                    </form>
                    @if (session('import_errors'))
                        <ul class="list-inside list-disc space-y-1 rounded border border-amber-500/30 bg-amber-500/10 px-4 py-3 text-xs text-amber-200">
                            @foreach (session('import_errors') as $importError)
                                <li>{{ $importError }}</li>
                            @endforeach
                        </ul>
                    @endif
                </div>
            </details>

            <form method="GET" action="{{ route('admin.toxic-words.index') }}" class="grid gap-3 rounded border border-surface-high bg-surface-low p-4 sm:grid-cols-[minmax(0,1fr)_160px_140px_auto]">
                <label class="sr-only" for="toxic-word-search">Tìm từ</label>
                <input id="toxic-word-search" name="q" value="{{ $filters['q'] ?? '' }}" type="search" placeholder="Tìm theo từ hoặc cụm từ..." class="min-h-10 rounded border border-surface-highest bg-surface-lowest px-3 text-sm text-on-surface outline-none placeholder:text-outline focus:border-primary">

                <label class="sr-only" for="toxic-word-scope">Scope</label>
                <select id="toxic-word-scope" name="scope" class="min-h-10 rounded border border-surface-highest bg-surface-lowest px-3 text-sm text-on-surface outline-none focus:border-primary">
                    <option value="">Tất cả scope</option>
                    @foreach ($scopes as $scope)
                        <option value="{{ $scope }}" @selected(($filters['scope'] ?? '') === $scope)>{{ $scope }}</option>
                    @endforeach
                </select>

                <label class="sr-only" for="toxic-word-status">Trạng thái</label>
                <select id="toxic-word-status" name="status" class="min-h-10 rounded border border-surface-highest bg-surface-lowest px-3 text-sm text-on-surface outline-none focus:border-primary">
                    <option value="">Tất cả trạng thái</option>
                    <option value="active" @selected(($filters['status'] ?? '') === 'active')>Đang bật</option>
                    <option value="inactive" @selected(($filters['status'] ?? '') === 'inactive')>Đang tắt</option>
                </select>

                <button type="submit" class="inline-flex min-h-10 items-center justify-center gap-2 rounded bg-primary-container px-4 text-sm font-semibold text-surface-lowest transition hover:bg-primary">
                    <span class="material-symbols-outlined text-base">filter_alt</span>
                    Lọc
                </button>
            </form>

            <div class="overflow-hidden rounded border border-surface-high bg-surface-low">
                @if ($words->isEmpty())
                    <div class="px-6 py-16 text-center">
                        <span class="material-symbols-outlined text-4xl text-primary">shield</span>
                        <p class="mt-3 font-semibold">Chưa có từ nào trong từ điển</p>
                        <p class="mt-1 text-sm text-outline">Thêm từ đầu tiên bằng form bên phải.</p>
                    </div>
                @else
                    <div class="overflow-x-auto">
                        <table class="w-full min-w-[760px] text-left text-sm">
                            <thead class="border-b border-surface-highest bg-surface-lowest text-[10px] uppercase tracking-wider text-outline">
                                <tr>
                                    <th class="px-5 py-3 font-medium">Từ / Cụm từ</th>
                                    <th class="px-4 py-3 font-medium">Scope</th>
                                    <th class="px-4 py-3 font-medium">Mode</th>
                                    <th class="px-4 py-3 font-medium">Trạng thái</th>
                                    <th class="px-5 py-3 text-right font-medium">Hành động</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-surface-highest/70">
                                @foreach ($words as $word)
                                    <tr class="transition hover:bg-surface">
                                        <td class="px-5 py-4">
                                            <p class="font-semibold">{{ $word->term }}</p>
                                            <p class="mt-0.5 font-mono text-[10px] text-outline">{{ $word->normalized_term }}</p>
                                            @if ($word->note)
                                                <p class="mt-1 text-xs text-outline">{{ $word->note }}</p>
                                            @endif
                                        </td>
                                        <td class="px-4 py-4">
                                            <div class="flex max-w-56 flex-wrap gap-1">
                                                @foreach ($word->scopes as $scope)
                                                    <span class="rounded border border-primary/30 bg-primary/10 px-2 py-0.5 font-mono text-[10px] text-primary">{{ $scope }}</span>
                                                @endforeach
                                            </div>
                                        </td>
                                        <td class="px-4 py-4">
                                            <span class="font-mono text-xs text-on-surface-variant">{{ $word->match_mode }}</span>
                                            @if ($word->fold_diacritics)
                                                <span class="ml-1 rounded bg-secondary/15 px-1.5 py-0.5 font-mono text-[10px] text-secondary">no-diacritics</span>
                                            @endif
                                        </td>
                                        <td class="px-4 py-4">
                                            <span @class([
                                                'inline-flex items-center gap-1.5 rounded px-2.5 py-1 text-[10px] font-medium',
                                                'bg-tertiary/15 text-tertiary' => $word->is_active,
                                                'bg-surface-highest text-on-surface-variant' => ! $word->is_active,
                                            ])>
                                                <span @class(['h-1.5 w-1.5 rounded-full', 'bg-tertiary' => $word->is_active, 'bg-outline' => ! $word->is_active])></span>
                                                {{ $word->is_active ? 'ACTIVE' : 'INACTIVE' }}
                                            </span>
                                        </td>
                                        <td class="px-5 py-4">
                                            <div class="flex justify-end gap-2">
                                                <button
                                                    type="button"
                                                    class="rounded p-2 text-primary transition hover:bg-surface-high"
                                                    title="Sửa từ"
                                                    data-edit-word="{{ json_encode([
                                                        'id' => $word->id,
                                                        'term' => $word->term,
                                                        'scopes' => $word->scopes,
                                                        'match_mode' => $word->match_mode,
                                                        'fold_diacritics' => $word->fold_diacritics,
                                                        'is_active' => $word->is_active,
                                                        'note' => $word->note,
                                                        'update_url' => route('admin.toxic-words.update', $word),
                                                    ], JSON_HEX_QUOT | JSON_HEX_APOS) }}"
                                                >
                                                    <span class="material-symbols-outlined text-[18px]">edit</span>
                                                </button>

                                                <form method="POST" action="{{ route('admin.toxic-words.update-status', $word) }}">
                                                    @csrf
                                                    @method('PATCH')
                                                    <input type="hidden" name="is_active" value="{{ $word->is_active ? 0 : 1 }}">
                                                    <button type="submit" class="rounded p-2 text-outline transition hover:bg-surface-high hover:text-on-surface" title="{{ $word->is_active ? 'Tắt từ' : 'Bật từ' }}">
                                                        <span class="material-symbols-outlined text-[18px]">power_settings_new</span>
                                                    </button>
                                                </form>

                                                <form method="POST" action="{{ route('admin.toxic-words.destroy', $word) }}" onsubmit="return confirm('Xóa từ này khỏi từ điển?')">
                                                    @csrf
                                                    @method('DELETE')
                                                    <button type="submit" class="rounded p-2 text-error transition hover:bg-error/15" title="Xóa từ">
                                                        <span class="material-symbols-outlined text-[18px]">delete</span>
                                                    </button>
                                                </form>
                                            </div>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif
            </div>
        </section>

        <aside class="min-w-0">
            <div class="sticky top-6 rounded border border-surface-high bg-surface-low">
                <div class="border-b border-surface-highest px-5 py-4">
                    <p class="text-[10px] uppercase tracking-widest text-primary">Dictionary Editor</p>
                    <h2 id="toxicWordEditorTitle" class="mt-1 text-lg font-semibold">Thêm từ mới</h2>
                </div>

                <form
                    id="toxicWordForm"
                    method="POST"
                    action="{{ route('admin.toxic-words.store') }}"
                    class="space-y-4 p-5"
                    data-toxic-word-form
                    data-store-url="{{ route('admin.toxic-words.store') }}"
                >
                    @csrf
                    <input type="hidden" name="_method" value="POST" data-toxic-word-method>

                    <div>
                        <label for="toxicWordTerm" class="mb-1.5 block text-xs font-medium text-on-surface-variant">Từ / Cụm từ *</label>
                        <input id="toxicWordTerm" name="term" type="text" value="{{ old('term') }}" maxlength="100" required class="w-full rounded border border-surface-highest bg-surface-lowest px-3 py-2.5 text-sm text-on-surface outline-none focus:border-primary">
                    </div>

                    <fieldset>
                        <legend class="mb-1.5 block text-xs font-medium text-on-surface-variant">Áp dụng cho *</legend>
                        <div class="grid grid-cols-2 gap-2">
                            @foreach ($scopes as $scope)
                                <label class="flex items-center gap-2 rounded border border-surface-highest bg-surface-lowest px-2.5 py-2 text-xs text-on-surface-variant">
                                    <input type="checkbox" name="scopes[]" value="{{ $scope }}" @checked(in_array($scope, (array) old('scopes', config('toxic_words.scopes')), true)) class="rounded border-outline-variant bg-surface-lowest text-sky-400 focus:ring-sky-400">
                                    {{ $scope }}
                                </label>
                            @endforeach
                        </div>
                    </fieldset>

                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <label for="toxicWordMode" class="mb-1.5 block text-xs font-medium text-on-surface-variant">Match mode</label>
                            <select id="toxicWordMode" name="match_mode" class="w-full rounded border border-surface-highest bg-surface-lowest px-3 py-2.5 text-sm text-on-surface outline-none focus:border-primary">
                                @foreach ($matchModes as $mode)
                                    <option value="{{ $mode }}" @selected(old('match_mode', 'WORD') === $mode)>{{ $mode }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="flex flex-col justify-end gap-2 pb-1">
                            <label class="flex items-center gap-2 text-xs text-on-surface-variant">
                                <input type="hidden" name="fold_diacritics" value="0">
                                <input type="checkbox" name="fold_diacritics" value="1" @checked(old('fold_diacritics')) class="rounded border-outline-variant bg-surface-lowest text-sky-400 focus:ring-sky-400">
                                Bỏ dấu khi so khớp
                            </label>
                            <label class="flex items-center gap-2 text-xs text-on-surface-variant">
                                <input type="hidden" name="is_active" value="0">
                                <input type="checkbox" name="is_active" value="1" @checked(old('is_active', true)) class="rounded border-outline-variant bg-surface-lowest text-sky-400 focus:ring-sky-400">
                                Đang bật
                            </label>
                        </div>
                    </div>

                    <div>
                        <label for="toxicWordNote" class="mb-1.5 block text-xs font-medium text-on-surface-variant">Ghi chú nội bộ</label>
                        <input id="toxicWordNote" name="note" type="text" value="{{ old('note') }}" maxlength="255" class="w-full rounded border border-surface-highest bg-surface-lowest px-3 py-2.5 text-sm text-on-surface outline-none focus:border-primary">
                    </div>

                    <p class="rounded border border-amber-500/30 bg-amber-500/10 px-3 py-2 text-[11px] leading-5 text-amber-300">
                        LOOSE chỉ dùng cho từ từ 4 ký tự. Bật bỏ dấu có thể chặn nhầm từ tiếng Việt hợp lệ.
                    </p>

                    <button type="submit" class="flex w-full items-center justify-center gap-2 rounded bg-primary-container px-4 py-2.5 text-sm font-semibold text-surface-lowest transition hover:bg-primary">
                        <span class="material-symbols-outlined text-base">save</span>
                        <span data-toxic-word-submit-label>Thêm từ</span>
                    </button>

                    <button type="button" data-toxic-word-cancel class="hidden w-full rounded border border-surface-highest px-4 py-2.5 text-sm text-on-surface-variant transition hover:bg-surface-high">
                        Hủy sửa
                    </button>
                </form>
            </div>
        </aside>
    </div>
</div>
@endsection
