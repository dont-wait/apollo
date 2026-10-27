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

    <div class="flex items-start gap-3">
        <div id="toxic-summary" data-toxic-summary hidden role="status" class="flex-1 rounded-lg border border-amber-500/30 bg-amber-500/10 px-4 py-3 text-sm text-amber-200"></div>
        <button type="button" data-toxic-jump="markdown_content" hidden class="rounded-lg border border-amber-500/40 bg-amber-500/10 px-3 py-2 text-xs font-medium text-amber-200 transition-colors hover:bg-amber-500/20">Nhảy đến lỗi</button>
    </div>

    <script type="application/json" data-toxic-moderation>@json(session('moderation', []))</script>

    <section class="space-y-5 rounded-xl bg-[#191c21] p-5 shadow-sm sm:p-6">
        <div>
            <label for="title" class="mb-2 block text-sm font-medium">Tiêu đề <span class="text-red-300">*</span></label>
            <input id="title" name="title" type="text" value="{{ old('title', $article?->title) }}" required maxlength="255" class="w-full rounded-lg border border-[#32353b] bg-[#0b0e13] px-3 py-2.5 text-sm text-[#e1e2ea] placeholder:text-[#87929a] focus:border-sky-400 focus:outline-none">
            @error('title') <p class="mt-1.5 text-xs text-red-300">{{ $message }}</p> @enderror
            <div data-toxic-snippet="title" hidden class="mt-2 rounded-lg border border-amber-500/30 bg-amber-500/10 px-3 py-2 text-sm text-amber-100"></div>
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
                <select id="status" name="status" required data-publication-status class="w-full rounded-lg border border-[#32353b] bg-[#0b0e13] px-3 py-2.5 text-sm text-[#e1e2ea] focus:border-sky-400 focus:outline-none">
                    @if (! array_key_exists($selectedStatus, $statusOptions))
                        <option value="{{ $selectedStatus }}" selected>{{ ucfirst(strtolower($selectedStatus)) }} (hiện tại)</option>
                    @endif
                    @foreach ($statusOptions as $value => $label)
                        <option value="{{ $value }}" @selected($selectedStatus === $value)>{{ $label }}</option>
                    @endforeach
                </select>
                <p class="mt-1.5 text-xs text-[#87929a]">Publish công khai ngay; Schedule sẽ tự publish khi đến thời điểm; Archive ngừng hiển thị public.</p>
                @error('status') <p class="mt-1.5 text-xs text-red-300">{{ $message }}</p> @enderror
            </div>
        </div>

        <div data-schedule-field @if ($selectedStatus !== 'SCHEDULED') hidden @endif>
            <label for="scheduled_at" class="mb-2 block text-sm font-medium">Thời điểm xuất bản <span class="text-red-300">*</span></label>
            <input id="scheduled_at" name="scheduled_at" type="datetime-local" value="{{ old('scheduled_at', $article?->scheduled_at?->format('Y-m-d\TH:i')) }}" class="w-full max-w-md rounded-lg border border-[#32353b] bg-[#0b0e13] px-3 py-2.5 text-sm text-[#e1e2ea] focus:border-sky-400 focus:outline-none">
            <p class="mt-1.5 text-xs text-[#87929a]">Phải là thời điểm trong tương lai (timezone hệ thống: {{ config('app.timezone') }}).</p>
            @error('scheduled_at') <p class="mt-1.5 text-xs text-red-300">{{ $message }}</p> @enderror
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
            <div data-toxic-snippet="new_tags" hidden class="mt-2 rounded-lg border border-amber-500/30 bg-amber-500/10 px-3 py-2 text-sm text-amber-100"></div>
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
            <div data-toxic-snippet="excerpt" hidden class="mt-2 rounded-lg border border-amber-500/30 bg-amber-500/10 px-3 py-2 text-sm text-amber-100"></div>
        </div>

        <div>
            <label for="markdown_content" class="mb-2 block text-sm font-medium">Nội dung Markdown <span class="text-red-300">*</span></label>
            <div role="tablist" aria-label="Chế độ nội dung Markdown" class="flex border-b border-[#32353b]">
                <button type="button" role="tab" id="markdown-write-tab" aria-controls="markdown-write-panel" aria-selected="true" data-markdown-tab="write" class="rounded-t-lg border-b-2 border-transparent px-4 py-2 text-sm font-medium text-[#87929a] transition hover:text-[#e1e2ea] aria-selected:border-sky-400 aria-selected:text-[#e1e2ea]">Write</button>
                <button type="button" role="tab" id="markdown-preview-tab" aria-controls="markdown-preview-panel" aria-selected="false" data-markdown-tab="preview" class="rounded-t-lg border-b-2 border-transparent px-4 py-2 text-sm font-medium text-[#87929a] transition hover:text-[#e1e2ea] aria-selected:border-sky-400 aria-selected:text-[#e1e2ea]">Preview</button>
            </div>
            <div id="markdown-write-panel" role="tabpanel" aria-labelledby="markdown-write-tab" data-markdown-panel="write">
                <section data-markdown-command-menu hidden aria-label="Lệnh Markdown" class="fixed z-50 w-80 max-w-[calc(100vw-1rem)] space-y-3 overflow-y-auto rounded-lg border border-[#32353b] bg-[#111319] p-3 shadow-xl">
                    <div class="flex items-center justify-between gap-2">
                        <h3 data-markdown-command-title class="text-sm font-medium text-[#e1e2ea]">Lệnh Markdown</h3>
                        <span data-markdown-command-filter class="font-mono text-xs text-sky-300"></span>
                    </div>
                    <div id="markdown-suggestions" data-markdown-suggestions role="listbox" aria-label="Lệnh Markdown" class="grid max-h-56 gap-1 overflow-y-auto"></div>
                    <p data-markdown-no-commands hidden class="text-sm text-[#87929a]">Không tìm thấy lệnh phù hợp.</p>
                    <div data-markdown-command-config hidden class="space-y-3">
                        <div data-markdown-command-fields class="grid gap-3"></div>
                        <div class="flex justify-end gap-2">
                            <button type="button" data-markdown-command-cancel class="rounded-lg px-3 py-2 text-sm text-[#bdc8d1] hover:bg-[#272a30]">Hủy</button>
                            <button type="button" data-markdown-command-insert class="rounded-lg bg-sky-500 px-3 py-2 text-sm font-medium text-slate-950 hover:bg-sky-400">Chèn</button>
                        </div>
                    </div>
                </section>
                <div class="toxic-editor" data-toxic-editor data-toxic-field="markdown_content">
                    <div class="toxic-editor__overlay rounded-b-lg px-3 py-3 font-mono text-sm leading-6" data-toxic-overlay aria-hidden="true"></div>
                    <textarea id="markdown_content" name="markdown_content" rows="18" required aria-controls="markdown-suggestions" aria-expanded="false" aria-autocomplete="list" aria-describedby="toxic-summary" class="toxic-editor__textarea relative z-10 w-full rounded-b-lg border border-[#32353b] bg-[#0b0e13] px-3 py-3 font-mono text-sm leading-6 text-[#e1e2ea] placeholder:text-[#87929a] focus:border-sky-400 focus:outline-none" placeholder="# Tiêu đề bài viết">{{ old('markdown_content', $article?->markdown_content) }}</textarea>
                </div>
            </div>
            <div id="markdown-preview-panel" role="tabpanel" aria-labelledby="markdown-preview-tab" data-markdown-panel="preview" hidden>
                <p data-markdown-preview-empty data-toxic-preview-empty="markdown_content" class="min-h-[27rem] rounded-b-lg border border-[#32353b] bg-[#0b0e13] px-4 py-3 text-sm text-[#87929a]">Chưa có nội dung để xem trước</p>
                <div data-markdown-preview-content data-toxic-preview="markdown_content" hidden class="markdown-preview min-h-[27rem] rounded-b-lg border border-[#32353b] bg-[#0b0e13] px-4 py-3 text-sm leading-7 text-[#bdc8d1] [&_a]:text-sky-300 [&_a]:underline [&_blockquote]:border-l-2 [&_blockquote]:border-[#4b5059] [&_blockquote]:pl-4 [&_h1]:mb-4 [&_h1]:text-3xl [&_h1]:font-semibold [&_h2]:mb-3 [&_h2]:text-2xl [&_h2]:font-semibold [&_h3]:mb-2 [&_h3]:text-xl [&_h3]:font-semibold [&_li]:ml-5 [&_ol]:list-decimal [&_p]:mb-4 [&_pre]:my-4 [&_pre]:overflow-x-auto [&_pre]:rounded-lg [&_pre]:bg-[#191c21] [&_pre]:p-4 [&_ul]:list-disc"></div>
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
        const publicationStatus = document.querySelector('[data-publication-status]');
        const scheduleField = document.querySelector('[data-schedule-field]');
        const scheduledAtInput = document.querySelector('#scheduled_at');
        const syncScheduleField = () => {
            if (!publicationStatus || !scheduleField || !scheduledAtInput) {
                return;
            }

            const scheduled = publicationStatus.value === 'SCHEDULED';
            scheduleField.hidden = !scheduled;
            scheduledAtInput.required = scheduled;
            scheduledAtInput.disabled = !scheduled;
        };

        publicationStatus?.addEventListener('change', syncScheduleField);
        syncScheduleField();

        const markdownInput = document.querySelector('#markdown_content');
        const markdownTabs = document.querySelectorAll('[data-markdown-tab]');
        const markdownPanels = document.querySelectorAll('[data-markdown-panel]');
        const markdownPreviewEmpty = document.querySelector('[data-markdown-preview-empty]');
        const markdownPreviewContent = document.querySelector('[data-markdown-preview-content]');

        if (!markdownInput || !markdownPreviewEmpty || !markdownPreviewContent) {
            return;
        }

        const renderMarkdownPreview = () => {
            if (markdownPreviewContent.dataset.toxicPreviewLocked === 'true') {
                return;
            }

            const markdown = markdownInput.value.trim();
            const hasMarkdown = markdown.length > 0;

            markdownPreviewEmpty.hidden = hasMarkdown;
            markdownPreviewContent.hidden = !hasMarkdown;
            markdownPreviewContent.innerHTML = hasMarkdown
                ? DOMPurify.sanitize(marked.parse(markdown))
                : '';
        };

        const suggestions = document.querySelector('[data-markdown-suggestions]');
        const commandMenu = document.querySelector('[data-markdown-command-menu]');
        const commandTitle = document.querySelector('[data-markdown-command-title]');
        const commandConfig = document.querySelector('[data-markdown-command-config]');
        const commandFields = document.querySelector('[data-markdown-command-fields]');
        const noCommands = document.querySelector('[data-markdown-no-commands]');
        const commandFilter = document.querySelector('[data-markdown-command-filter]');
        let suggestionItems = [];
        let activeSuggestion = 0;
        let replacementRange = null;
        let selectedCommand = null;

        const snippets = [
            { trigger: 'table', label: 'Bảng', description: 'Chọn số cột và dòng', fields: [
                { name: 'columns', label: 'Số cột', type: 'number', value: '3', min: 1, max: 12 },
                { name: 'rows', label: 'Số dòng nội dung', type: 'number', value: '3', min: 1, max: 30 },
            ], build: ({ columns, rows }) => {
                const header = `| ${Array.from({ length: Number(columns) }, (_, index) => `Cột ${index + 1}`).join(' | ')} |`;
                const divider = `| ${Array.from({ length: Number(columns) }, () => '---').join(' | ')} |`;
                const body = Array.from({ length: Number(rows) }, () => `| ${Array.from({ length: Number(columns) }, () => ' ').join(' | ')} |`);
                return [header, divider, ...body].join('\n');
            } },
            { trigger: 'h1', label: 'Tiêu đề lớn', description: 'Tiêu đề cấp 1', value: '# Tiêu đề' },
            { trigger: 'h2', label: 'Tiêu đề', description: 'Tiêu đề cấp 2', value: '## Tiêu đề' },
            { trigger: 'h3', label: 'Tiêu đề nhỏ', description: 'Tiêu đề cấp 3', value: '### Tiêu đề' },
            { trigger: 'bold', label: 'In đậm', description: 'Văn bản nhấn mạnh', value: '**văn bản**' },
            { trigger: 'italic', label: 'In nghiêng', description: 'Văn bản nhấn nhẹ', value: '*văn bản*' },
            { trigger: 'strike', label: 'Gạch ngang', description: 'Văn bản đã xóa', value: '~~văn bản~~' },
            { trigger: 'inline-code', label: 'Mã nội dòng', description: 'Đoạn mã trong câu', value: '`code`' },
            { trigger: 'code', label: 'Khối mã', description: 'Chọn ngôn ngữ', fields: [
                { name: 'language', label: 'Ngôn ngữ', type: 'text', value: 'javascript', pattern: '[a-zA-Z0-9_+#.\\-]*' },
            ], build: ({ language }) => `\`\`\`${language}\ncode\n\`\`\`` },
            { trigger: 'quote', label: 'Trích dẫn', description: 'Blockquote', value: '> Nội dung trích dẫn' },
            { trigger: 'bullet-list', label: 'Danh sách', description: 'Danh sách gạch đầu dòng', value: '- Mục 1\n- Mục 2' },
            { trigger: 'numbered-list', label: 'Danh sách số', description: 'Danh sách có thứ tự', value: '1. Mục 1\n2. Mục 2' },
            { trigger: 'task-list', label: 'Danh sách việc', description: 'Danh sách checkbox', value: '- [ ] Việc cần làm' },
            { trigger: 'link', label: 'Liên kết', description: 'Nhập văn bản và URL', fields: [
                { name: 'text', label: 'Văn bản liên kết', type: 'text', value: '' },
                { name: 'url', label: 'URL', type: 'url', value: '', required: true },
            ], build: ({ text, url }) => `[${escapeMarkdownLabel(text)}](<${escapeMarkdownUrl(url)}>)` },
            { trigger: 'image', label: 'Hình ảnh', description: 'Nhập mô tả và URL ảnh', fields: [
                { name: 'text', label: 'Mô tả ảnh', type: 'text', value: '' },
                { name: 'url', label: 'URL ảnh', type: 'url', value: '', required: true },
            ], build: ({ text, url }) => `![${escapeMarkdownLabel(text)}](<${escapeMarkdownUrl(url)}>)` },
            { trigger: 'hr', label: 'Đường phân cách', description: 'Ngăn cách các phần', value: '\n---\n' },
        ];

        const escapeMarkdownLabel = (text) => text.replace(/[\\[\]]/g, '\\$&');
        const escapeMarkdownUrl = (url) => url.replace(/</g, '%3C').replace(/>/g, '%3E');

        const closeCommands = () => {
            commandMenu.hidden = true;
            commandConfig.hidden = true;
            commandFields.replaceChildren();
            selectedCommand = null;
            replacementRange = null;
            suggestionItems = [];
            markdownInput.setAttribute('aria-expanded', 'false');
            markdownInput.removeAttribute('aria-activedescendant');
        };

        const positionCommands = () => {
            if (commandMenu.hidden) {
                return;
            }

            const rect = markdownInput.getBoundingClientRect();
            const style = getComputedStyle(markdownInput);
            const mirror = document.createElement('div');
            for (const property of ['boxSizing', 'fontFamily', 'fontSize', 'fontWeight', 'fontStyle', 'letterSpacing', 'lineHeight', 'paddingTop', 'paddingRight', 'paddingBottom', 'paddingLeft', 'borderTopWidth', 'borderRightWidth', 'borderBottomWidth', 'borderLeftWidth', 'wordSpacing', 'tabSize', 'textIndent']) {
                mirror.style[property] = style[property];
            }
            Object.assign(mirror.style, {
                position: 'fixed', visibility: 'hidden', top: '0', left: '0',
                width: `${markdownInput.clientWidth + parseFloat(style.borderLeftWidth) + parseFloat(style.borderRightWidth)}px`,
                borderStyle: 'solid', whiteSpace: 'pre-wrap', overflowWrap: 'break-word',
            });
            mirror.textContent = markdownInput.value.slice(0, replacementRange.end);
            const caret = document.createElement('span');
            caret.textContent = markdownInput.value.slice(replacementRange.end) || '.';
            mirror.append(caret);
            document.body.append(mirror);
            const caretRect = caret.getClientRects()[0];
            const caretX = rect.left + caretRect.left - markdownInput.scrollLeft;
            const caretY = rect.top + caretRect.top - markdownInput.scrollTop;
            const lineHeight = parseFloat(style.lineHeight);
            mirror.remove();

            if (caretY + lineHeight < rect.top || caretY > rect.bottom || rect.bottom < 0 || rect.top > window.innerHeight) {
                closeCommands();
                return;
            }

            const below = window.innerHeight - Math.max(0, caretY + lineHeight) - 16;
            const above = Math.min(window.innerHeight, caretY) - 16;
            const openBelow = below >= Math.min(commandMenu.scrollHeight, 280) || below >= above;
            commandMenu.style.maxHeight = `${Math.max(0, openBelow ? below : above)}px`;
            const height = commandMenu.getBoundingClientRect().height;
            commandMenu.style.left = `${Math.max(8, Math.min(caretX, window.innerWidth - commandMenu.offsetWidth - 8))}px`;
            commandMenu.style.top = `${Math.max(8, openBelow ? caretY + lineHeight + 4 : caretY - height - 4)}px`;
        };

        const highlightSuggestion = () => {
            [...suggestions.children].forEach((item, index) => item.setAttribute('aria-selected', String(index === activeSuggestion)));
            const activeItem = suggestions.children[activeSuggestion];
            if (activeItem) {
                markdownInput.setAttribute('aria-activedescendant', activeItem.id);
                activeItem.scrollIntoView({ block: 'nearest' });
            } else {
                markdownInput.removeAttribute('aria-activedescendant');
            }
        };

        const showSuggestions = () => {
            if (document.activeElement !== markdownInput || markdownInput.selectionStart !== markdownInput.selectionEnd) {
                closeCommands();
                return;
            }
            const cursor = markdownInput.selectionStart;
            const lineStart = markdownInput.value.lastIndexOf('\n', cursor - 1) + 1;
            const beforeCursor = markdownInput.value.slice(lineStart, cursor);
            const match = beforeCursor.match(/(?:^|\s)\/([\w-]*)$/u);
            if (!match || /[\w-]/u.test(markdownInput.value[cursor] ?? '')) {
                closeCommands();
                return;
            }
            const query = match[1].toLowerCase();
            suggestionItems = snippets.filter((item) => item.trigger.includes(query));
            replacementRange = { start: cursor - query.length - 1, end: cursor };
            selectedCommand = null;
            activeSuggestion = 0;
            commandTitle.textContent = 'Lệnh Markdown';
            commandFilter.textContent = `/${query}`;
            commandConfig.hidden = true;
            noCommands.hidden = suggestionItems.length > 0;
            suggestions.hidden = false;
            suggestions.replaceChildren(...suggestionItems.map((item, index) => {
                const button = document.createElement('button');
                button.type = 'button';
                button.role = 'option';
                button.id = `markdown-command-${item.trigger}`;
                button.setAttribute('aria-selected', String(index === activeSuggestion));
                button.className = 'flex flex-col rounded-md px-3 py-2 text-left hover:bg-[#272a30] aria-selected:bg-sky-500/10';
                const title = document.createElement('span');
                title.className = 'text-sm text-[#e1e2ea]';
                title.textContent = item.label;
                const description = document.createElement('span');
                description.className = 'text-xs text-[#87929a]';
                description.textContent = `/${item.trigger} · ${item.description}`;
                button.append(title, description);
                button.addEventListener('mousedown', (event) => event.preventDefault());
                button.addEventListener('click', () => chooseSuggestion(index));
                return button;
            }));
            commandMenu.hidden = false;
            markdownInput.setAttribute('aria-expanded', 'true');
            positionCommands();
            highlightSuggestion();
        };

        const insertText = (text) => {
            const { start, end } = replacementRange;
            closeCommands();
            markdownInput.setRangeText(text, start, end, 'end');
            markdownInput.focus();
            markdownInput.dispatchEvent(new Event('input', { bubbles: true }));
        };

        const chooseSuggestion = (index) => {
            const item = suggestionItems[index];
            if (!item || !replacementRange) {
                return;
            }
            if (!item.fields) {
                insertText(item.value);
                return;
            }
            selectedCommand = item;
            suggestions.hidden = true;
            noCommands.hidden = true;
            markdownInput.setAttribute('aria-expanded', 'false');
            markdownInput.removeAttribute('aria-activedescendant');
            commandTitle.textContent = item.label;
            commandFilter.textContent = `/${item.trigger}`;
            commandFields.replaceChildren(...item.fields.map((field) => {
                const label = document.createElement('label');
                label.className = 'grid gap-1 text-sm text-[#e1e2ea]';
                label.textContent = field.label;
                const input = document.createElement('input');
                input.dataset.commandField = field.name;
                input.type = field.type;
                input.value = field.value;
                input.className = 'w-full rounded-lg border border-[#32353b] bg-[#191c21] px-3 py-2 text-[#e1e2ea] focus:border-sky-400 focus:outline-none';
                for (const attribute of ['min', 'max', 'pattern']) {
                    if (field[attribute] !== undefined) {
                        input.setAttribute(attribute, field[attribute]);
                    }
                }
                label.append(input);
                return label;
            }));
            commandConfig.hidden = false;
            positionCommands();
            const firstInput = commandFields.querySelector('input');
            firstInput.focus({ preventScroll: true });
            firstInput.select();
        };

        const insertConfiguredCommand = () => {
            if (!selectedCommand || !replacementRange) {
                return;
            }
            const values = {};
            for (const input of commandFields.querySelectorAll('input')) {
                const field = selectedCommand.fields.find((field) => field.name === input.dataset.commandField);
                input.setCustomValidity(field.required && !input.value.trim() ? 'Vui lòng nhập URL.' : '');
                if (!input.reportValidity()) {
                    return;
                }
                if (input.type === 'number' && !input.value) {
                    input.setCustomValidity('Vui lòng nhập số nguyên trong giới hạn.');
                    input.reportValidity();
                    return;
                }
                values[input.dataset.commandField] = input.value.trim();
            }
            let text = selectedCommand.build(values);
            if (['table', 'code'].includes(selectedCommand.trigger)) {
                const { start, end } = replacementRange;
                const prefix = start > 0 && markdownInput.value[start - 1] !== '\n' ? '\n' : '';
                const suffix = markdownInput.value[end] !== '\n' ? '\n' : '';
                text = `${prefix}${text}${suffix}`;
            }
            insertText(text);
        };

        markdownInput.addEventListener('input', (event) => {
            markdownPreviewContent.dataset.toxicPreviewLocked = 'false';

            if (!event.isComposing) {
                showSuggestions();
            }
        });
        markdownInput.addEventListener('compositionstart', closeCommands);
        markdownInput.addEventListener('compositionend', showSuggestions);
        markdownInput.addEventListener('click', showSuggestions);
        markdownInput.addEventListener('keyup', (event) => {
            if (!commandMenu.hidden && !selectedCommand && ['ArrowLeft', 'ArrowRight', 'Home', 'End'].includes(event.key)) {
                showSuggestions();
            }
        });
        markdownInput.addEventListener('keydown', (event) => {
            if (event.isComposing || commandMenu.hidden) {
                return;
            }
            if (event.key === 'Escape') {
                event.preventDefault();
                closeCommands();
            } else if (suggestionItems.length && ['ArrowDown', 'ArrowUp'].includes(event.key)) {
                event.preventDefault();
                activeSuggestion = (activeSuggestion + (event.key === 'ArrowDown' ? 1 : -1) + suggestionItems.length) % suggestionItems.length;
                highlightSuggestion();
            } else if (suggestionItems.length && ['Enter', 'Tab'].includes(event.key)) {
                event.preventDefault();
                chooseSuggestion(activeSuggestion);
            }
        });
        commandConfig.addEventListener('keydown', (event) => {
            if (event.isComposing) {
                return;
            }
            if (event.key === 'Enter' && event.target instanceof HTMLInputElement) {
                event.preventDefault();
                insertConfiguredCommand();
            }
        });
        commandMenu.addEventListener('keydown', (event) => {
            if (event.key === 'Escape') {
                event.preventDefault();
                closeCommands();
                markdownInput.focus({ preventScroll: true });
            }
        });
        commandMenu.addEventListener('input', (event) => {
            event.target.setCustomValidity?.('');
        });
        document.querySelector('[data-markdown-command-cancel]').addEventListener('click', () => {
            closeCommands();
            markdownInput.focus({ preventScroll: true });
        });
        document.querySelector('[data-markdown-command-insert]').addEventListener('click', insertConfiguredCommand);
        document.addEventListener('pointerdown', (event) => {
            if (!commandMenu.contains(event.target) && event.target !== markdownInput) {
                closeCommands();
            }
        });
        document.addEventListener('focusin', (event) => {
            if (event.target !== markdownInput && !commandMenu.contains(event.target)) {
                closeCommands();
            }
        });
        document.addEventListener('scroll', (event) => {
            if (!commandMenu.contains(event.target)) {
                positionCommands();
            }
        }, true);
        window.addEventListener('resize', positionCommands);

        const selectMarkdownTab = (selectedTab) => {
            closeCommands();
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
