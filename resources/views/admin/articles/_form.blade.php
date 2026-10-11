@php
    $selectedTags = collect(old('tags', $article?->tags->modelKeys() ?? []))
        ->map(fn ($tagId) => (int) $tagId)
        ->all();
    $selectedStatus = old('status', $article?->status ?? 'DRAFT');
@endphp

<form method="POST" action="{{ $action }}" enctype="multipart/form-data" class="space-y-6">
    @csrf
    @if ($method !== 'POST')
        @method($method)
    @endif

    @if (session('status'))
        <div role="status" class="rounded-lg border border-emerald-500/30 bg-emerald-500/10 px-4 py-3 text-sm text-emerald-300">
            {{ session('status') }}
        </div>
    @endif

    @if ($errors->any())
        <div role="alert" class="rounded-lg border border-red-500/30 bg-red-500/10 px-4 py-3 text-sm text-red-200">
            Vui lòng kiểm tra lại các trường được đánh dấu bên dưới.
        </div>
    @endif

    <section class="space-y-5 rounded-xl bg-[#191c21] p-5 shadow-sm sm:p-6">
        <div>
            <label for="title" class="mb-2 block text-sm font-medium">Tiêu đề <span class="text-red-300">*</span></label>
            <input id="title" name="title" type="text" value="{{ old('title', $article?->title) }}" required maxlength="255" class="w-full rounded-lg border border-[#32353b] bg-[#0b0e13] px-3 py-2.5 text-sm text-[#e1e2ea] placeholder:text-[#87929a] focus:border-sky-400 focus:outline-none">
            @error('title') <p class="mt-1.5 text-xs text-red-300">{{ $message }}</p> @enderror
        </div>

        <div>
            <label for="slug" class="mb-2 block text-sm font-medium">Slug</label>
            <input id="slug" name="slug" type="text" value="{{ old('slug', $article?->slug) }}" maxlength="255" placeholder="Để trống để tự tạo từ tiêu đề" class="w-full rounded-lg border border-[#32353b] bg-[#0b0e13] px-3 py-2.5 font-mono text-sm text-[#e1e2ea] placeholder:font-sans placeholder:text-[#87929a] focus:border-sky-400 focus:outline-none">
            <p class="mt-1.5 text-xs text-[#87929a]">Slug sẽ được tự tạo từ tiêu đề nếu bạn để trống.</p>
            @error('slug') <p class="mt-1.5 text-xs text-red-300">{{ $message }}</p> @enderror
        </div>

        <div class="grid gap-5 md:grid-cols-2">
            <div>
                <label for="category_id" class="mb-2 block text-sm font-medium">Danh mục</label>
                <select id="category_id" name="category_id" class="w-full rounded-lg border border-[#32353b] bg-[#0b0e13] px-3 py-2.5 text-sm text-[#e1e2ea] focus:border-sky-400 focus:outline-none">
                    <option value="">Chưa phân loại</option>
                    @foreach ($categories as $category)
                        <option value="{{ $category->id }}" @selected((string) old('category_id', $article?->category_id) === (string) $category->id)>{{ $category->name }}</option>
                    @endforeach
                </select>
                @error('category_id') <p class="mt-1.5 text-xs text-red-300">{{ $message }}</p> @enderror
            </div>

            <div>
                <label for="status" class="mb-2 block text-sm font-medium">Trạng thái <span class="text-red-300">*</span></label>
                <select id="status" name="status" required class="w-full rounded-lg border border-[#32353b] bg-[#0b0e13] px-3 py-2.5 text-sm text-[#e1e2ea] focus:border-sky-400 focus:outline-none">
                    @if (! in_array($selectedStatus, ['DRAFT', 'PUBLISHED'], true))
                        <option value="{{ $selectedStatus }}" selected>{{ ucfirst(strtolower($selectedStatus)) }} (hiện tại)</option>
                    @endif
                    <option value="DRAFT" @selected($selectedStatus === 'DRAFT')>Draft</option>
                    <option value="PUBLISHED" @selected($selectedStatus === 'PUBLISHED')>Published</option>
                </select>
                @error('status') <p class="mt-1.5 text-xs text-red-300">{{ $message }}</p> @enderror
            </div>
        </div>

        <fieldset>
            <legend class="mb-2 block text-sm font-medium">Thẻ</legend>
            @if ($tags->isEmpty())
                <p class="rounded-lg border border-dashed border-[#32353b] px-3 py-4 text-sm text-[#87929a]">Chưa có thẻ nào. Tạo thẻ trong mục Categories &amp; Tags.</p>
            @else
                <div class="grid gap-2 rounded-lg border border-[#32353b] bg-[#0b0e13] p-3 sm:grid-cols-2 lg:grid-cols-3">
                    @foreach ($tags as $tag)
                        <label class="flex items-center gap-2 rounded-md px-2 py-1.5 text-sm text-[#bdc8d1] hover:bg-[#191c21]">
                            <input type="checkbox" name="tags[]" value="{{ $tag->id }}" @checked(in_array($tag->id, $selectedTags, true)) class="rounded border-[#4b5059] bg-[#0b0e13] text-sky-400 focus:ring-sky-400">
                            {{ $tag->name }}
                        </label>
                    @endforeach
                </div>
            @endif
            @error('tags') <p class="mt-1.5 text-xs text-red-300">{{ $message }}</p> @enderror
            @error('tags.*') <p class="mt-1.5 text-xs text-red-300">{{ $message }}</p> @enderror
        </fieldset>

        <div>
            <label for="thumbnail" class="mb-2 block text-sm font-medium">Ảnh thumbnail / cover</label>
            @if ($article?->thumbnail)
                <div class="mb-3 flex items-center gap-3">
                    <img src="{{ $article->thumbnail->url }}" alt="Ảnh thumbnail hiện tại" class="h-16 w-24 rounded-md object-cover">
                    <span class="text-xs text-[#87929a]">Chọn ảnh mới để thay thế ảnh hiện tại.</span>
                </div>
            @endif
            <input id="thumbnail" name="thumbnail" type="file" accept="image/jpeg,image/png,image/webp" class="block w-full rounded-lg border border-[#32353b] bg-[#0b0e13] text-sm text-[#bdc8d1] file:mr-4 file:border-0 file:bg-[#272a30] file:px-4 file:py-2.5 file:text-sm file:font-medium file:text-[#e1e2ea] hover:file:bg-[#32353b]">
            <p class="mt-1.5 text-xs text-[#87929a]">JPG, PNG hoặc WebP; tối đa 5 MB.</p>
            @error('thumbnail') <p class="mt-1.5 text-xs text-red-300">{{ $message }}</p> @enderror
        </div>

        <div>
            <label for="excerpt" class="mb-2 block text-sm font-medium">Tóm tắt</label>
            <textarea id="excerpt" name="excerpt" rows="3" maxlength="500" class="w-full rounded-lg border border-[#32353b] bg-[#0b0e13] px-3 py-2.5 text-sm text-[#e1e2ea] placeholder:text-[#87929a] focus:border-sky-400 focus:outline-none">{{ old('excerpt', $article?->excerpt) }}</textarea>
            @error('excerpt') <p class="mt-1.5 text-xs text-red-300">{{ $message }}</p> @enderror
        </div>

        <div>
            <label for="markdown_content" class="mb-2 block text-sm font-medium">Nội dung Markdown <span class="text-red-300">*</span></label>
            <textarea id="markdown_content" name="markdown_content" rows="18" required class="w-full rounded-lg border border-[#32353b] bg-[#0b0e13] px-3 py-3 font-mono text-sm leading-6 text-[#e1e2ea] placeholder:text-[#87929a] focus:border-sky-400 focus:outline-none" placeholder="# Tiêu đề bài viết">{{ old('markdown_content', $article?->markdown_content) }}</textarea>
            @error('markdown_content') <p class="mt-1.5 text-xs text-red-300">{{ $message }}</p> @enderror
        </div>
    </section>

    <div class="flex flex-col-reverse justify-end gap-3 sm:flex-row">
        <a href="{{ route('admin.articles.index') }}" class="rounded-lg px-5 py-2.5 text-center text-sm text-[#bdc8d1] transition hover:bg-[#272a30]">Hủy</a>
        <button type="submit" class="inline-flex items-center justify-center gap-2 rounded-lg bg-sky-500 px-5 py-2.5 text-sm font-semibold text-slate-950 transition hover:bg-sky-400">
            <span class="material-symbols-outlined text-lg">save</span>
            {{ $article ? 'Lưu thay đổi' : 'Tạo bài viết' }}
        </button>
    </div>
</form>
