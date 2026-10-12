@php
    $selectedTagIds = old('tags_submitted')
        ? old('tags', [])
        : old('tags', $article?->tags->modelKeys() ?? []);
    $selectedTags = collect($selectedTagIds)
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
                    @if (! array_key_exists($selectedStatus, $statusOptions))
                        <option value="{{ $selectedStatus }}" selected>{{ ucfirst(strtolower($selectedStatus)) }} (hiện tại)</option>
                    @endif
                    @foreach ($statusOptions as $value => $label)
                        <option value="{{ $value }}" @selected($selectedStatus === $value)>{{ $label }}</option>
                    @endforeach
                </select>
                @error('status') <p class="mt-1.5 text-xs text-red-300">{{ $message }}</p> @enderror
            </div>
        </div>

        <fieldset>
            <legend class="mb-2 block text-sm font-medium">Thẻ</legend>
            <input type="hidden" name="tags_submitted" value="1">
            @if ($tags->isEmpty())
                <p class="mb-3 rounded-lg border border-dashed border-[#32353b] px-3 py-4 text-sm text-[#87929a]">Chưa có thẻ nào được tạo.</p>
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
            <div class="mt-3 flex max-w-md gap-2">
                <label class="sr-only" for="new-tag-name">Tên tag mới</label>
                <input id="new-tag-name" type="text" maxlength="60" placeholder="Nhập tên tag mới" data-new-tag-input class="min-w-0 flex-1 rounded-lg border border-[#32353b] bg-[#0b0e13] px-3 py-2.5 text-sm text-[#e1e2ea] placeholder:text-[#87929a] focus:border-sky-400 focus:outline-none">
                <button type="button" data-add-new-tag class="shrink-0 whitespace-nowrap rounded-lg bg-[#272a30] px-4 py-2.5 text-sm font-medium text-[#e1e2ea] transition hover:bg-[#32353b]">+ Thêm tag</button>
            </div>
            <ul data-new-tags-list class="mt-2 flex flex-wrap gap-2">
                @foreach (old('new_tags', []) as $newTagName)
                    <li class="inline-flex items-center gap-2 rounded-full bg-sky-500/10 px-3 py-1.5 text-sm text-sky-200">
                        <input type="hidden" name="new_tags[]" value="{{ $newTagName }}">
                        <span>{{ $newTagName }}</span>
                        <button type="button" data-remove-new-tag aria-label="Xóa tag {{ $newTagName }}" class="text-sky-200 hover:text-white">&times;</button>
                    </li>
                @endforeach
            </ul>
            @error('tags') <p class="mt-1.5 text-xs text-red-300">{{ $message }}</p> @enderror
            @error('tags.*') <p class="mt-1.5 text-xs text-red-300">{{ $message }}</p> @enderror
            @error('new_tags') <p class="mt-1.5 text-xs text-red-300">{{ $message }}</p> @enderror
            @error('new_tags.*') <p class="mt-1.5 text-xs text-red-300">{{ $message }}</p> @enderror
        </fieldset>

        <div>
            <label for="thumbnail" class="mb-2 block text-sm font-medium">Ảnh thumbnail / cover</label>
            @if ($article?->thumbnail)
                <div class="mb-3 flex items-center gap-3">
                    <img src="{{ $article->thumbnail->url }}" alt="Ảnh thumbnail hiện tại" class="h-16 w-24 rounded-md object-cover">
                    <span class="text-xs text-[#87929a]">Chọn ảnh mới để thay thế ảnh hiện tại.</span>
                </div>
            @endif
            <input id="thumbnail" name="thumbnail" type="file" accept="{{ implode(',', array_map(fn ($mime) => '.'.$mime, $allowedImageMimes)) }}" class="block w-full rounded-lg border border-[#32353b] bg-[#0b0e13] text-sm text-[#bdc8d1] file:mr-4 file:border-0 file:bg-[#272a30] file:px-4 file:py-2.5 file:text-sm file:font-medium file:text-[#e1e2ea] hover:file:bg-[#32353b]">
            <p class="mt-1.5 text-xs text-[#87929a]">{{ strtoupper(implode(', ', $allowedImageMimes)) }}; tối đa {{ $maxThumbnailKb / 1024 }} MB.</p>
            @error('thumbnail') <p class="mt-1.5 text-xs text-red-300">{{ $message }}</p> @enderror
        </div>

        <div>
            <label for="excerpt" class="mb-2 block text-sm font-medium">Tóm tắt</label>
            <textarea id="excerpt" name="excerpt" rows="3" maxlength="{{ $maxExcerptLength }}" class="w-full rounded-lg border border-[#32353b] bg-[#0b0e13] px-3 py-2.5 text-sm text-[#e1e2ea] placeholder:text-[#87929a] focus:border-sky-400 focus:outline-none">{{ old('excerpt', $article?->excerpt) }}</textarea>
            @error('excerpt') <p class="mt-1.5 text-xs text-red-300">{{ $message }}</p> @enderror
        </div>

        <div>
            <label for="markdown_content" class="mb-2 block text-sm font-medium">Nội dung Markdown <span class="text-red-300">*</span></label>
            <div role="tablist" aria-label="Chế độ nội dung Markdown" class="flex border-b border-[#32353b]">
                <button type="button" role="tab" id="markdown-write-tab" aria-controls="markdown-write-panel" aria-selected="true" data-markdown-tab="write" class="rounded-t-lg border-b-2 border-transparent px-4 py-2 text-sm font-medium text-[#87929a] transition hover:text-[#e1e2ea] aria-selected:border-sky-400 aria-selected:text-[#e1e2ea]">Write</button>
                <button type="button" role="tab" id="markdown-preview-tab" aria-controls="markdown-preview-panel" aria-selected="false" data-markdown-tab="preview" class="rounded-t-lg border-b-2 border-transparent px-4 py-2 text-sm font-medium text-[#87929a] transition hover:text-[#e1e2ea] aria-selected:border-sky-400 aria-selected:text-[#e1e2ea]">Preview</button>
            </div>
            <div id="markdown-write-panel" role="tabpanel" aria-labelledby="markdown-write-tab" data-markdown-panel="write">
                <textarea id="markdown_content" name="markdown_content" rows="18" required class="w-full rounded-b-lg border border-[#32353b] bg-[#0b0e13] px-3 py-3 font-mono text-sm leading-6 text-[#e1e2ea] placeholder:text-[#87929a] focus:border-sky-400 focus:outline-none" placeholder="# Tiêu đề bài viết">{{ old('markdown_content', $article?->markdown_content) }}</textarea>
            </div>
            <div id="markdown-preview-panel" role="tabpanel" aria-labelledby="markdown-preview-tab" data-markdown-panel="preview" hidden>
                <p data-markdown-preview-empty class="min-h-[27rem] rounded-b-lg border border-[#32353b] bg-[#0b0e13] px-4 py-3 text-sm text-[#87929a]">Chưa có nội dung để xem trước</p>
                <div data-markdown-preview-content hidden class="markdown-preview min-h-[27rem] rounded-b-lg border border-[#32353b] bg-[#0b0e13] px-4 py-3 text-sm leading-7 text-[#bdc8d1] [&_a]:text-sky-300 [&_a]:underline [&_blockquote]:border-l-2 [&_blockquote]:border-[#4b5059] [&_blockquote]:pl-4 [&_h1]:mb-4 [&_h1]:text-3xl [&_h1]:font-semibold [&_h2]:mb-3 [&_h2]:text-2xl [&_h2]:font-semibold [&_h3]:mb-2 [&_h3]:text-xl [&_h3]:font-semibold [&_li]:ml-5 [&_ol]:list-decimal [&_p]:mb-4 [&_pre]:my-4 [&_pre]:overflow-x-auto [&_pre]:rounded-lg [&_pre]:bg-[#191c21] [&_pre]:p-4 [&_ul]:list-disc"></div>
            </div>
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

<script src="https://cdn.jsdelivr.net/npm/marked@15.0.12/lib/marked.umd.js"></script>
<script src="https://cdn.jsdelivr.net/npm/dompurify@3.2.6/dist/purify.min.js"></script>
<script>
    (() => {
        const markdownInput = document.querySelector('#markdown_content');
        const markdownTabs = document.querySelectorAll('[data-markdown-tab]');
        const markdownPanels = document.querySelectorAll('[data-markdown-panel]');
        const markdownPreviewEmpty = document.querySelector('[data-markdown-preview-empty]');
        const markdownPreviewContent = document.querySelector('[data-markdown-preview-content]');

        if (!markdownInput || !markdownPreviewEmpty || !markdownPreviewContent) {
            return;
        }

        const renderMarkdownPreview = () => {
            const markdown = markdownInput.value.trim();
            const hasMarkdown = markdown.length > 0;

            markdownPreviewEmpty.hidden = hasMarkdown;
            markdownPreviewContent.hidden = !hasMarkdown;
            markdownPreviewContent.innerHTML = hasMarkdown
                ? DOMPurify.sanitize(marked.parse(markdown))
                : '';
        };

        const selectMarkdownTab = (selectedTab) => {
            markdownTabs.forEach((tab) => {
                const isSelected = tab.dataset.markdownTab === selectedTab;
                tab.setAttribute('aria-selected', String(isSelected));
            });

            markdownPanels.forEach((panel) => {
                panel.hidden = panel.dataset.markdownPanel !== selectedTab;
            });

            markdownInput.required = selectedTab === 'write';

            if (selectedTab === 'preview') {
                renderMarkdownPreview();
            }
        };

        markdownTabs.forEach((tab, index) => {
            tab.addEventListener('click', () => {
                selectMarkdownTab(tab.dataset.markdownTab);
            });
            tab.addEventListener('keydown', (event) => {
                if (event.key === 'ArrowLeft' || event.key === 'ArrowRight') {
                    event.preventDefault();
                    const direction = event.key === 'ArrowRight' ? 1 : -1;
                    const nextIndex = (index + direction + markdownTabs.length) % markdownTabs.length;
                    markdownTabs[nextIndex].focus();
                    markdownTabs[nextIndex].click();
                }
            });
        });

        const tagInput = document.querySelector('[data-new-tag-input]');
        const addTagButton = document.querySelector('[data-add-new-tag]');
        const newTagsList = document.querySelector('[data-new-tags-list]');

        if (!tagInput || !addTagButton || !newTagsList) {
            return;
        }

        const addTag = () => {
            const tagName = tagInput.value.trim();

            if (!tagName) {
                return;
            }

            const tagItem = document.createElement('li');
            tagItem.className = 'inline-flex items-center gap-2 rounded-full bg-sky-500/10 px-3 py-1.5 text-sm text-sky-200';

            const hiddenInput = document.createElement('input');
            hiddenInput.type = 'hidden';
            hiddenInput.name = 'new_tags[]';
            hiddenInput.value = tagName;

            const tagLabel = document.createElement('span');
            tagLabel.textContent = tagName;

            const removeButton = document.createElement('button');
            removeButton.type = 'button';
            removeButton.dataset.removeNewTag = '';
            removeButton.setAttribute('aria-label', `Xóa tag ${tagName}`);
            removeButton.className = 'text-sky-200 hover:text-white';
            removeButton.innerHTML = '&times;';

            tagItem.append(hiddenInput, tagLabel, removeButton);
            newTagsList.append(tagItem);
            tagInput.value = '';
        };

        addTagButton.addEventListener('click', addTag);
        tagInput.addEventListener('keydown', (event) => {
            if (event.key === 'Enter') {
                event.preventDefault();
                addTag();
            }
        });
        newTagsList.addEventListener('click', (event) => {
            if (event.target instanceof Element && event.target.closest('[data-remove-new-tag]')) {
                event.target.closest('li').remove();
            }
        });
    })();
</script>
