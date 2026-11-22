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
    @if ($article !== null)
        <input type="hidden" name="original_status" value="{{ $article->status }}">
        <input type="hidden" name="revision" value="{{ old('revision', \App\Support\ArticleRevision::for($article)) }}">
    @endif

    @if (session('status'))
        <div role="status" class="rounded-lg border border-emerald-500/30 bg-emerald-500/10 px-4 py-3 text-sm text-emerald-300">
            {{ session('status') }}
        </div>
    @endif
    @error('revision')
        <div role="alert" class="rounded-lg border border-amber-500/40 bg-amber-500/10 px-4 py-3 text-sm text-amber-100">
            {{ $message }} <a class="underline" href="{{ route('admin.articles.edit', $article) }}">Reload article</a>.
        </div>
    @enderror
    @if ($errors->any())
        <div role="alert" class="rounded-lg border border-red-500/30 bg-red-500/10 px-4 py-3 text-sm text-red-200">
            Please review the fields highlighted below.
        </div>
    @endif

    <div class="flex items-start gap-3">
        <div id="toxic-summary" data-toxic-summary hidden role="status" class="flex-1 rounded-lg border border-amber-500/30 bg-amber-500/10 px-4 py-3 text-sm text-amber-200"></div>
        <button type="button" data-toxic-jump="markdown_content" hidden class="rounded-lg border border-amber-500/40 bg-amber-500/10 px-3 py-2 text-xs font-medium text-amber-200 transition-colors hover:bg-amber-500/20">Jump to issue</button>
    </div>

    <script type="application/json" data-toxic-moderation>@json(session('moderation', []))</script>

    {{-- Basics --}}
    <section class="space-y-5 rounded-xl bg-surface-low p-5 shadow-sm sm:p-6">
        <div>
            <div class="mb-2 flex items-end justify-between gap-3">
                <label for="title" class="block text-sm font-medium">Title <span class="text-red-300">*</span></label>
                <span data-char-count data-for="title" data-max="255" class="font-mono text-[11px] text-outline"></span>
            </div>
            <input id="title" name="title" type="text" value="{{ old('title', $article?->title) }}" required maxlength="255" class="w-full rounded-lg border border-surface-highest bg-surface-lowest px-3 py-2.5 text-sm text-on-surface placeholder:text-outline focus:border-sky-400 focus:outline-none text-base font-medium">
            @error('title') <p class="mt-1.5 text-xs text-red-300">{{ $message }}</p> @enderror
            <div data-toxic-snippet="title" hidden class="mt-2 rounded-lg border border-amber-500/30 bg-amber-500/10 px-3 py-2 text-sm text-amber-100"></div>
        </div>

        <div>
            <div class="mb-2 flex items-end justify-between gap-3">
                <label for="slug" class="block text-sm font-medium">Slug</label>
                <button type="button" data-use-generated-slug hidden class="text-xs text-sky-300 hover:underline">Use generated slug</button>
            </div>
            <input id="slug" name="slug" type="text" value="{{ old('slug', $article?->slug) }}" maxlength="255" placeholder="Leave empty to generate from the title" class="w-full rounded-lg border border-surface-highest bg-surface-lowest px-3 py-2.5 text-sm text-on-surface placeholder:text-outline focus:border-sky-400 focus:outline-none font-mono placeholder:font-sans">
            <p class="mt-1.5 truncate font-mono text-xs text-outline">
                <span class="text-outline">{{ rtrim(config('app.url'), '/') }}/articles/</span><span data-slug-preview class="text-sky-300"></span>
                <span data-slug-auto class="ml-1 font-sans text-outline" hidden>(auto-generated)</span>
            </p>
            @error('slug') <p class="mt-1.5 text-xs text-red-300">{{ $message }}</p> @enderror
        </div>

        <div>
            <div class="mb-2 flex items-end justify-between gap-3">
                <label for="excerpt" class="block text-sm font-medium">Excerpt</label>
                <span data-char-count data-for="excerpt" data-max="{{ $maxExcerptLength }}" class="font-mono text-[11px] text-outline"></span>
            </div>
            <textarea id="excerpt" name="excerpt" rows="3" maxlength="{{ $maxExcerptLength }}" placeholder="A short summary shown in lists and search results" class="w-full rounded-lg border border-surface-highest bg-surface-lowest px-3 py-2.5 text-sm text-on-surface placeholder:text-outline focus:border-sky-400 focus:outline-none">{{ old('excerpt', $article?->excerpt) }}</textarea>
            @error('excerpt') <p class="mt-1.5 text-xs text-red-300">{{ $message }}</p> @enderror
            <div data-toxic-snippet="excerpt" hidden class="mt-2 rounded-lg border border-amber-500/30 bg-amber-500/10 px-3 py-2 text-sm text-amber-100"></div>
        </div>
    </section>

    {{-- Publishing --}}
    <section class="space-y-5 rounded-xl bg-surface-low p-5 shadow-sm sm:p-6">
        <h2 class="text-sm font-semibold">Publishing</h2>

        <fieldset>
            <legend class="mb-2 block text-sm font-medium">Status <span class="text-red-300">*</span></legend>
            @php
                $statusMeta = [
                    'DRAFT' => ['edit_note', 'Only admins can see it'],
                    'SCHEDULED' => ['schedule', 'Goes live at the time you set'],
                    'PUBLISHED' => ['public', 'Goes live immediately'],
                    'ARCHIVED' => ['inventory_2', 'Hidden from the public'],
                ];
                $allStatuses = $statusOptions;
                if (! array_key_exists($selectedStatus, $allStatuses)) {
                    $allStatuses = [$selectedStatus => ucfirst(strtolower($selectedStatus))] + $allStatuses;
                }
            @endphp
            <div class="grid gap-2 sm:grid-cols-2 xl:grid-cols-4">
                @foreach ($allStatuses as $value => $label)
                    <label class="cursor-pointer">
                        <input type="radio" name="status" value="{{ $value }}" data-publication-status required @checked($selectedStatus === $value) class="peer sr-only">
                        <span class="flex h-full items-start gap-3 rounded-lg border border-surface-highest bg-surface-lowest p-3 transition hover:border-outline peer-checked:border-sky-400 peer-checked:bg-sky-500/10 peer-focus-visible:ring-2 peer-focus-visible:ring-sky-400">
                            <span class="material-symbols-outlined mt-0.5 text-xl text-outline">{{ $statusMeta[$value][0] ?? 'flag' }}</span>
                            <span class="min-w-0">
                                <span class="block text-sm font-semibold text-on-surface">{{ $label }}</span>
                                <span class="mt-0.5 block text-xs text-outline">{{ $statusMeta[$value][1] ?? 'Current status' }}</span>
                            </span>
                        </span>
                    </label>
                @endforeach
            </div>
            @error('status') <p class="mt-1.5 text-xs text-red-300">{{ $message }}</p> @enderror
        </fieldset>

        <div data-schedule-field @if ($selectedStatus !== 'SCHEDULED') hidden @endif>
            <label for="scheduled_at" class="mb-2 block text-sm font-medium">Publish time <span class="text-red-300">*</span></label>
            <input id="scheduled_at" name="scheduled_at" type="datetime-local" value="{{ old('scheduled_at', $article?->scheduled_at?->format('Y-m-d\TH:i')) }}" class="w-full rounded-lg border border-surface-highest bg-surface-lowest px-3 py-2.5 text-sm text-on-surface placeholder:text-outline focus:border-sky-400 focus:outline-none max-w-md">
            <p class="mt-1.5 text-xs text-outline">Must be in the future (system timezone: {{ config('app.timezone') }}).</p>
            @error('scheduled_at') <p class="mt-1.5 text-xs text-red-300">{{ $message }}</p> @enderror
        </div>

        <div>
            <label for="category_id" class="mb-2 block text-sm font-medium">Category</label>
            <select id="category_id" name="category_id" class="w-full rounded-lg border border-surface-highest bg-surface-lowest px-3 py-2.5 text-sm text-on-surface placeholder:text-outline focus:border-sky-400 focus:outline-none max-w-md">
                <option value="">Uncategorized</option>
                @foreach ($categories as $category)
                    <option value="{{ $category->id }}" @selected((string) old('category_id', $article?->category_id) === (string) $category->id)>{{ $category->name }}</option>
                @endforeach
            </select>
            @error('category_id') <p class="mt-1.5 text-xs text-red-300">{{ $message }}</p> @enderror
        </div>
    </section>

    {{-- Tags + thumbnail --}}
    <div class="grid gap-6 lg:grid-cols-2">
        <section class="space-y-5 rounded-xl bg-surface-low p-5 shadow-sm sm:p-6">
            <fieldset data-tag-picker>
                <div class="mb-3 flex items-center justify-between gap-3">
                    <legend class="text-sm font-semibold">Tags</legend>
                    <span class="font-mono text-[11px] text-outline"><span data-tag-count>0</span> selected</span>
                </div>
                <input type="hidden" name="tags_submitted" value="1">
                @if ($tags->count() > 8)
                    <label class="sr-only" for="tag-filter">Filter tags</label>
                    <input id="tag-filter" type="search" data-tag-filter placeholder="Filter tags..." class="w-full rounded-lg border border-surface-highest bg-surface-lowest px-3 py-2.5 text-sm text-on-surface placeholder:text-outline focus:border-sky-400 focus:outline-none mb-3">
                @endif
                @if ($tags->isEmpty())
                    <p class="mb-3 rounded-lg border border-dashed border-surface-highest px-3 py-4 text-sm text-outline">No tags have been created yet. Add one below.</p>
                @else
                    <div class="flex max-h-48 flex-wrap gap-2 overflow-y-auto" data-tag-list>
                        @foreach ($tags as $tag)
                            <label class="cursor-pointer" data-tag-chip data-tag-name="{{ \Illuminate\Support\Str::lower($tag->name) }}">
                                <input type="checkbox" name="tags[]" value="{{ $tag->id }}" @checked(in_array($tag->id, $selectedTags, true)) class="peer sr-only">
                                <span class="inline-flex items-center rounded-full border border-surface-highest bg-surface-lowest px-3 py-1 text-xs text-on-surface-variant transition hover:border-outline peer-checked:border-sky-400 peer-checked:bg-sky-500/15 peer-checked:text-sky-200 peer-focus-visible:ring-2 peer-focus-visible:ring-sky-400">#{{ $tag->name }}</span>
                            </label>
                        @endforeach
                    </div>
                    <p data-tag-empty hidden class="mt-2 text-xs text-outline">No tags match your filter.</p>
                @endif
                <div class="mt-3 flex gap-2">
                    <label class="sr-only" for="new-tag-name">New tag name</label>
                    <input id="new-tag-name" type="text" maxlength="60" placeholder="Create a new tag and press Enter" data-new-tag-input class="min-w-0 flex-1 w-full rounded-lg border border-surface-highest bg-surface-lowest px-3 py-2.5 text-sm text-on-surface placeholder:text-outline focus:border-sky-400 focus:outline-none">
                    <button type="button" data-add-new-tag class="shrink-0 whitespace-nowrap rounded-lg bg-surface-high px-4 py-2.5 text-sm font-medium text-on-surface transition hover:bg-surface-highest">+ Add tag</button>
                </div>
                <ul data-new-tags-list class="mt-2 flex flex-wrap gap-2">
                    @foreach (old('new_tags', []) as $newTagName)
                        <li class="inline-flex items-center gap-2 rounded-full border border-sky-400 bg-sky-500/15 px-3 py-1 text-xs text-sky-200">
                            <input type="hidden" name="new_tags[]" value="{{ $newTagName }}">
                            <span>#{{ $newTagName }} <span class="text-sky-300/70">(new)</span></span>
                            <button type="button" data-remove-new-tag aria-label="Remove tag {{ $newTagName }}" class="text-sky-200 hover:text-white">&times;</button>
                        </li>
                    @endforeach
                </ul>
                @error('tags') <p class="mt-1.5 text-xs text-red-300">{{ $message }}</p> @enderror
                @error('tags.*') <p class="mt-1.5 text-xs text-red-300">{{ $message }}</p> @enderror
                @error('new_tags') <p class="mt-1.5 text-xs text-red-300">{{ $message }}</p> @enderror
                @error('new_tags.*') <p class="mt-1.5 text-xs text-red-300">{{ $message }}</p> @enderror
                <div data-toxic-snippet="new_tags" hidden class="mt-2 rounded-lg border border-amber-500/30 bg-amber-500/10 px-3 py-2 text-sm text-amber-100"></div>
            </fieldset>
        </section>

        <section class="space-y-5 rounded-xl bg-surface-low p-5 shadow-sm sm:p-6" data-thumbnail-field data-max-kb="{{ $maxThumbnailKb }}">
            <h2 class="text-sm font-semibold">Thumbnail / cover image</h2>
            <div class="flex flex-col gap-4 sm:flex-row sm:items-start">
                <div class="flex h-32 w-full shrink-0 items-center justify-center overflow-hidden rounded-lg border border-dashed border-surface-highest bg-surface-lowest sm:w-48">
                    <img data-thumbnail-preview @if ($article?->thumbnail) src="{{ $article->thumbnail->url }}" data-original-src="{{ $article->thumbnail->url }}" @else hidden @endif alt="Thumbnail preview" class="h-full w-full object-cover">
                    <span data-thumbnail-placeholder @if ($article?->thumbnail) hidden @endif class="material-symbols-outlined text-4xl text-outline">image</span>
                </div>
                <div class="min-w-0 flex-1 space-y-2">
                    <label for="thumbnail" class="inline-flex cursor-pointer items-center gap-2 rounded-lg bg-surface-high px-4 py-2 text-sm font-medium text-on-surface transition hover:bg-surface-highest">
                        <span class="material-symbols-outlined text-lg">upload</span>
                        {{ $article?->thumbnail ? 'Replace image' : 'Choose image' }}
                    </label>
                    <input id="thumbnail" name="thumbnail" type="file" accept="{{ implode(',', array_map(fn ($mime) => '.'.$mime, $allowedImageMimes)) }}" class="sr-only">
                    <p data-thumbnail-name class="truncate text-xs text-on-surface-variant">{{ $article?->thumbnail ? 'Current image. Choose a new one to replace it.' : 'No image selected.' }}</p>
                    <button type="button" data-thumbnail-clear hidden class="text-xs text-red-300 hover:underline">Remove selected image</button>
                    <p data-thumbnail-warning hidden class="text-xs text-amber-300"></p>
                    <p class="text-xs text-outline">{{ strtoupper(implode(', ', $allowedImageMimes)) }}; max {{ $maxThumbnailKb / 1024 }} MB.</p>
                </div>
            </div>
            @error('thumbnail') <p class="mt-1.5 text-xs text-red-300">{{ $message }}</p> @enderror
        </section>
    </div>

    {{-- SEO --}}
    <details class="group rounded-xl bg-surface-low shadow-sm" @if ($errors->hasAny(['seo_title', 'seo_description', 'canonical_url']) || old('seo_title', $article?->seo_title) || old('seo_description', $article?->seo_description) || old('canonical_url') || $article?->canonical_url) open @endif>
        <summary class="flex cursor-pointer list-none items-center justify-between gap-3 p-5 sm:px-6">
            <span>
                <span class="block text-sm font-semibold">SEO metadata</span>
                <span class="mt-1 block text-xs text-outline">Leave the SEO title or description empty to use the article data. A canonical URL is generated on publish if none is set.</span>
            </span>
            <span class="material-symbols-outlined text-outline transition group-open:rotate-180">expand_more</span>
        </summary>
        <div class="space-y-4 border-t border-surface-highest p-5 sm:p-6">
            <div>
                <div class="mb-2 flex items-end justify-between gap-3">
                    <label for="seo_title" class="block text-sm font-medium">SEO title</label>
                    <span data-char-count data-for="seo_title" data-soft="60" data-max="255" class="font-mono text-[11px] text-outline"></span>
                </div>
                <input id="seo_title" name="seo_title" type="text" value="{{ old('seo_title', $article?->seo_title) }}" maxlength="255" placeholder="Defaults to the article title" class="w-full rounded-lg border border-surface-highest bg-surface-lowest px-3 py-2.5 text-sm text-on-surface placeholder:text-outline focus:border-sky-400 focus:outline-none">
                @error('seo_title') <p class="mt-1.5 text-xs text-red-300">{{ $message }}</p> @enderror
            </div>

            <div>
                <div class="mb-2 flex items-end justify-between gap-3">
                    <label for="seo_description" class="block text-sm font-medium">SEO description</label>
                    <span data-char-count data-for="seo_description" data-soft="160" data-max="320" class="font-mono text-[11px] text-outline"></span>
                </div>
                <textarea id="seo_description" name="seo_description" rows="3" maxlength="320" placeholder="Defaults to the excerpt" class="w-full rounded-lg border border-surface-highest bg-surface-lowest px-3 py-2.5 text-sm text-on-surface placeholder:text-outline focus:border-sky-400 focus:outline-none">{{ old('seo_description', $article?->seo_description) }}</textarea>
                @error('seo_description') <p class="mt-1.5 text-xs text-red-300">{{ $message }}</p> @enderror
            </div>

            <div>
                <label for="canonical_url" class="mb-2 block text-sm font-medium">Canonical URL</label>
                <input id="canonical_url" name="canonical_url" type="url" value="{{ old('canonical_url') ?: $article?->canonical_url }}" maxlength="500" placeholder="https://example.com/articles/example" class="w-full rounded-lg border border-surface-highest bg-surface-lowest px-3 py-2.5 text-sm text-on-surface placeholder:text-outline focus:border-sky-400 focus:outline-none">
                <p class="mt-1.5 text-xs text-outline">Only HTTP or HTTPS URLs are accepted. A generated canonical stays stable when the article is saved.</p>
                @error('canonical_url') <p class="mt-1.5 text-xs text-red-300">{{ $message }}</p> @enderror
            </div>
        </div>
    </details>

    {{-- Content --}}
    <section class="space-y-5 rounded-xl bg-surface-low p-5 shadow-sm sm:p-6">
        <div data-markdown-editor>
            <label for="markdown_content" class="mb-2 block text-sm font-medium">Markdown content <span class="text-red-300">*</span></label>
            <div class="mb-2 flex flex-wrap items-center justify-between gap-2">
                <div role="group" aria-label="Markdown view mode" class="inline-flex rounded-lg border border-surface-highest bg-surface-lowest p-0.5">
                    <button type="button" aria-pressed="false" data-markdown-view="write" class="rounded-md px-3 py-1.5 text-xs font-medium text-outline transition hover:text-on-surface aria-pressed:bg-surface-high aria-pressed:text-on-surface">Write</button>
                    <button type="button" aria-pressed="true" data-markdown-view="split" class="rounded-md px-3 py-1.5 text-xs font-medium text-outline transition hover:text-on-surface aria-pressed:bg-surface-high aria-pressed:text-on-surface">Split</button>
                    <button type="button" aria-pressed="false" data-markdown-view="preview" class="rounded-md px-3 py-1.5 text-xs font-medium text-outline transition hover:text-on-surface aria-pressed:bg-surface-high aria-pressed:text-on-surface">Preview</button>
                </div>
                <div class="flex items-center gap-2">
                    <button type="button" data-sidebar-toggle aria-pressed="false" class="inline-flex items-center gap-1.5 rounded-lg border border-surface-highest px-3 py-1.5 text-xs text-on-surface-variant transition hover:bg-surface-high">
                        <span class="material-symbols-outlined text-base">left_panel_close</span>
                        <span data-sidebar-toggle-label>Hide sidebar</span>
                    </button>
                    <button type="button" data-fullscreen-toggle aria-pressed="false" aria-label="Focus mode (fullscreen)" title="Focus mode (fullscreen). Esc to exit" class="inline-flex size-8 items-center justify-center rounded-lg border border-surface-highest text-on-surface-variant transition hover:bg-surface-high hover:text-on-surface">
                        <span class="material-symbols-outlined text-lg" data-fullscreen-icon>fullscreen</span>
                    </button>
                </div>
            </div>
            <div data-markdown-layout data-view="split" class="grid gap-3 data-[view=split]:lg:grid-cols-2">
            <div id="markdown-write-panel" data-markdown-panel="write" class="min-w-0">
                <section data-markdown-command-menu hidden aria-label="Markdown commands" class="fixed z-50 w-80 max-w-[calc(100vw-1rem)] space-y-3 overflow-y-auto rounded-lg border border-surface-highest bg-background p-3 shadow-xl">
                    <div class="flex items-center justify-between gap-2">
                        <h3 data-markdown-command-title class="text-sm font-medium text-on-surface">Markdown commands</h3>
                        <span data-markdown-command-filter class="font-mono text-xs text-sky-300"></span>
                    </div>
                    <div id="markdown-suggestions" data-markdown-suggestions role="listbox" aria-label="Markdown commands" class="grid max-h-56 gap-1 overflow-y-auto"></div>
                    <p data-markdown-no-commands hidden class="text-sm text-outline">No matching commands.</p>
                    <div data-markdown-command-config hidden class="space-y-3">
                        <div data-markdown-command-fields class="grid gap-3"></div>
                        <div class="flex justify-end gap-2">
                            <button type="button" data-markdown-command-cancel class="rounded-lg px-3 py-2 text-sm text-on-surface-variant hover:bg-surface-high">Cancel</button>
                            <button type="button" data-markdown-command-insert class="rounded-lg bg-sky-500 px-3 py-2 text-sm font-medium text-slate-950 hover:bg-sky-400">Insert</button>
                        </div>
                    </div>
                </section>
                <div class="toxic-editor toxic-editor--numbered" data-toxic-editor data-toxic-field="markdown_content">
                    <div class="line-gutter" aria-hidden="true"><div data-line-gutter-inner></div></div>
                    <div class="toxic-editor__overlay rounded-b-lg px-3 py-3 font-mono text-sm leading-6" data-toxic-overlay aria-hidden="true"></div>
                    <textarea id="markdown_content" name="markdown_content" rows="18" required aria-controls="markdown-suggestions" aria-expanded="false" aria-autocomplete="list" aria-describedby="toxic-summary" class="toxic-editor__textarea relative z-10 w-full rounded-b-lg border border-surface-highest bg-surface-lowest px-3 py-3 font-mono text-sm leading-6 text-on-surface placeholder:text-outline focus:border-sky-400 focus:outline-none" placeholder="# Article title">{{ old('markdown_content', $article?->markdown_content) }}</textarea>
                </div>
                <p data-editor-status class="mt-1.5 font-mono text-xs text-outline" aria-live="off">Ln 1, Col 1</p>
            </div>
            <div id="markdown-preview-panel" data-markdown-panel="preview" class="min-w-0">
                <p data-markdown-preview-empty data-toxic-preview-empty="markdown_content" class="min-h-[27rem] rounded-lg border border-surface-highest bg-surface-lowest px-4 py-3 text-sm text-outline">Nothing to preview yet</p>
                <div data-markdown-preview-content data-toxic-preview="markdown_content" hidden class="markdown-preview max-h-[40rem] min-h-[27rem] overflow-y-auto rounded-lg border border-surface-highest bg-surface-lowest px-4 py-3 text-sm leading-7 text-on-surface-variant [&_a]:text-sky-300 [&_a]:underline [&_blockquote]:border-l-2 [&_blockquote]:border-outline-variant [&_blockquote]:pl-4 [&_h1]:mb-4 [&_h1]:text-3xl [&_h1]:font-semibold [&_h2]:mb-3 [&_h2]:text-2xl [&_h2]:font-semibold [&_h3]:mb-2 [&_h3]:text-xl [&_h3]:font-semibold [&_li]:ml-5 [&_ol]:list-decimal [&_p]:mb-4 [&_pre]:my-4 [&_pre]:overflow-x-auto [&_pre]:rounded-lg [&_pre]:bg-surface-low [&_pre]:p-4 [&_ul]:list-disc"></div>
            </div>
            </div>
            @error('markdown_content') <p class="mt-1.5 text-xs text-red-300">{{ $message }}</p> @enderror
        </div>

    </section>

    <div class="sticky bottom-0 z-30 -mx-4 border-t border-surface-highest bg-background/90 px-4 py-3 backdrop-blur sm:-mx-8 sm:px-8">
        <div class="flex flex-col-reverse items-stretch justify-end gap-3 sm:flex-row sm:items-center">
            <p data-dirty-indicator hidden class="mr-auto inline-flex items-center gap-2 text-xs text-amber-300" role="status">
                <span class="size-2 rounded-full bg-amber-400"></span>Unsaved changes
            </p>
            <a href="{{ route('admin.articles.index') }}" class="rounded-lg px-5 py-2.5 text-center text-sm text-on-surface-variant transition hover:bg-surface-high">Cancel</a>
            <button type="submit" class="inline-flex items-center justify-center gap-2 rounded-lg bg-sky-500 px-5 py-2.5 text-sm font-semibold text-slate-950 transition hover:bg-sky-400" title="Ctrl/Cmd + S">
                <span class="material-symbols-outlined text-lg">save</span>
                {{ $article ? 'Save changes' : 'Create article' }}
            </button>
        </div>
    </div>
</form>

<script src="https://cdn.jsdelivr.net/npm/marked@15.0.12/lib/marked.umd.js"></script>
<script src="https://cdn.jsdelivr.net/npm/dompurify@3.2.6/dist/purify.min.js"></script>
<script src="https://cdn.jsdelivr.net/gh/highlightjs/cdn-release@11.11.1/build/highlight.min.js"></script>
<script src="{{ asset('code-blocks.js') }}"></script>
<script>
    (() => {
        const form = document.querySelector('form[enctype="multipart/form-data"]');
        const scheduleField = document.querySelector('[data-schedule-field]');
        const scheduledAtInput = document.querySelector('#scheduled_at');
        const syncScheduleField = () => {
            if (!scheduleField || !scheduledAtInput) {
                return;
            }

            const scheduled = document.querySelector('[data-publication-status]:checked')?.value === 'SCHEDULED';
            scheduleField.hidden = !scheduled;
            scheduledAtInput.required = scheduled;
            scheduledAtInput.disabled = !scheduled;
        };

        document.querySelectorAll('[data-publication-status]').forEach((radio) => radio.addEventListener('change', syncScheduleField));
        syncScheduleField();

        // Character counters: n / max, amber once past the recommended (soft) length.
        document.querySelectorAll('[data-char-count]').forEach((counter) => {
            const field = document.getElementById(counter.dataset.for);
            const soft = Number(counter.dataset.soft || 0);
            const max = Number(counter.dataset.max || 0);
            const update = () => {
                const length = field.value.length;
                counter.textContent = soft ? `${length} / ${soft} recommended` : `${length} / ${max}`;
                counter.classList.toggle('text-amber-300', soft > 0 && length > soft);
                counter.classList.toggle('text-outline', !(soft > 0 && length > soft));
            };
            field?.addEventListener('input', update);
            update();
        });

        // Slug preview: what the URL will be (generated from the title while the slug is empty).
        const titleInput = document.querySelector('#title');
        const slugInput = document.querySelector('#slug');
        const slugPreview = document.querySelector('[data-slug-preview]');
        const slugAuto = document.querySelector('[data-slug-auto]');
        const useGeneratedSlug = document.querySelector('[data-use-generated-slug]');
        const slugify = (text) => text
            .normalize('NFD').replace(/[\u0300-\u036f]/g, '')
            .replace(/đ/gi, 'd')
            .toLowerCase()
            .replace(/[^a-z0-9]+/g, '-')
            .replace(/^-+|-+$/g, '');
        const syncSlugPreview = () => {
            if (!slugInput || !titleInput || !slugPreview) {
                return;
            }

            const generated = slugify(titleInput.value);
            const manual = slugInput.value.trim();
            slugPreview.textContent = manual || generated || '…';
            slugAuto.hidden = manual !== '';
            useGeneratedSlug.hidden = manual === '' || manual === generated || generated === '';
        };
        titleInput?.addEventListener('input', syncSlugPreview);
        slugInput?.addEventListener('input', syncSlugPreview);
        useGeneratedSlug?.addEventListener('click', () => {
            slugInput.value = slugify(titleInput.value);
            slugInput.dispatchEvent(new Event('input', { bubbles: true }));
        });
        syncSlugPreview();

        // Tag picker: selected counter + filter.
        const tagPicker = document.querySelector('[data-tag-picker]');
        const tagCount = document.querySelector('[data-tag-count]');
        const updateTagCount = () => {
            if (tagCount) {
                tagCount.textContent = String(tagPicker.querySelectorAll('input[name="tags[]"]:checked, input[name="new_tags[]"]').length);
            }
        };
        tagPicker?.addEventListener('change', updateTagCount);
        const tagFilter = document.querySelector('[data-tag-filter]');
        tagFilter?.addEventListener('input', () => {
            const needle = tagFilter.value.trim().toLowerCase();
            let visible = 0;
            tagPicker.querySelectorAll('[data-tag-chip]').forEach((chip) => {
                const match = needle === '' || chip.dataset.tagName.includes(needle);
                chip.hidden = !match;
                visible += match ? 1 : 0;
            });
            tagPicker.querySelector('[data-tag-empty]').hidden = visible > 0;
        });
        updateTagCount();

        // Thumbnail: preview the chosen file and warn about size before uploading.
        const thumbnailField = document.querySelector('[data-thumbnail-field]');
        if (thumbnailField) {
            const input = thumbnailField.querySelector('#thumbnail');
            const preview = thumbnailField.querySelector('[data-thumbnail-preview]');
            const placeholder = thumbnailField.querySelector('[data-thumbnail-placeholder]');
            const nameLabel = thumbnailField.querySelector('[data-thumbnail-name]');
            const clearButton = thumbnailField.querySelector('[data-thumbnail-clear]');
            const warning = thumbnailField.querySelector('[data-thumbnail-warning]');
            const originalSrc = preview.dataset.originalSrc || '';
            const originalLabel = nameLabel.textContent;
            let objectUrl = null;
            const showPreview = (src) => {
                preview.hidden = !src;
                placeholder.hidden = Boolean(src);
                if (src) {
                    preview.src = src;
                }
            };
            const reset = () => {
                input.value = '';
                if (objectUrl) {
                    URL.revokeObjectURL(objectUrl);
                    objectUrl = null;
                }
                showPreview(originalSrc);
                nameLabel.textContent = originalLabel;
                clearButton.hidden = true;
                warning.hidden = true;
            };
            input.addEventListener('change', () => {
                const file = input.files[0];
                if (!file) {
                    reset();

                    return;
                }
                if (objectUrl) {
                    URL.revokeObjectURL(objectUrl);
                }
                objectUrl = URL.createObjectURL(file);
                showPreview(objectUrl);
                nameLabel.textContent = `${file.name} · ${(file.size / 1024 / 1024).toFixed(2)} MB`;
                clearButton.hidden = false;
                const tooBig = file.size > Number(thumbnailField.dataset.maxKb) * 1024;
                warning.hidden = !tooBig;
                warning.textContent = tooBig ? `This file is larger than ${Number(thumbnailField.dataset.maxKb) / 1024} MB and will be rejected.` : '';
            });
            clearButton.addEventListener('click', () => {
                reset();
                input.dispatchEvent(new Event('change', { bubbles: true }));
            });
        }

        // Unsaved-changes indicator, leave warning and Ctrl/Cmd+S.
        if (form) {
            const dirtyIndicator = document.querySelector('[data-dirty-indicator]');
            const snapshot = () => JSON.stringify([...new FormData(form).entries()].filter(([key, value]) => !(value instanceof File) && key !== '_token'));
            const thumbnailChosen = () => Boolean(form.querySelector('#thumbnail')?.files?.length);
            let baseline = null;
            let submitting = false;
            const isDirty = () => baseline !== null && (snapshot() !== baseline || thumbnailChosen());
            const refreshDirty = () => {
                if (dirtyIndicator) {
                    dirtyIndicator.hidden = !isDirty();
                }
            };
            form.addEventListener('input', refreshDirty);
            form.addEventListener('change', refreshDirty);
            form.addEventListener('click', () => setTimeout(refreshDirty, 0));
            form.addEventListener('submit', () => { submitting = true; });
            window.addEventListener('beforeunload', (event) => {
                if (!submitting && isDirty()) {
                    event.preventDefault();
                    event.returnValue = '';
                }
            });
            document.addEventListener('keydown', (event) => {
                if ((event.ctrlKey || event.metaKey) && event.key.toLowerCase() === 's') {
                    event.preventDefault();
                    form.requestSubmit();
                }
            });
            setTimeout(() => { baseline = snapshot(); refreshDirty(); }, 0);
        }

        const markdownInput = document.querySelector('#markdown_content');
        const markdownViewButtons = document.querySelectorAll('[data-markdown-view]');
        const markdownLayout = document.querySelector('[data-markdown-layout]');
        const markdownPanels = document.querySelectorAll('[data-markdown-panel]');
        const markdownPreviewEmpty = document.querySelector('[data-markdown-preview-empty]');
        const markdownPreviewContent = document.querySelector('[data-markdown-preview-content]');

        if (!markdownInput || !markdownPreviewEmpty || !markdownPreviewContent) {
            return;
        }

        const alertTitles = { note: 'Note', tip: 'Tip', important: 'Important', warning: 'Warning', caution: 'Caution' };
        const alertMarker = /^\s*\[!(NOTE|TIP|IMPORTANT|WARNING|CAUTION)\]\s*/i;
        const renderGithubAlerts = (html) => {
            if (!html.includes('[!')) {
                return html;
            }

            const template = document.createElement('template');
            template.innerHTML = html;
            template.content.querySelectorAll('blockquote').forEach((quote) => {
                const paragraph = quote.querySelector(':scope > p');
                const first = paragraph?.firstChild;
                const match = first?.nodeType === Node.TEXT_NODE && first.nodeValue.match(alertMarker);

                if (!match) {
                    return;
                }

                const type = match[1].toLowerCase();
                first.nodeValue = first.nodeValue.replace(alertMarker, '');
                if (first.nodeValue === '') {
                    first.remove();
                    if (paragraph.firstChild?.nodeName === 'BR') {
                        paragraph.firstChild.remove();
                    }
                }
                if (!paragraph.hasChildNodes()) {
                    paragraph.remove();
                }

                const alert = document.createElement('div');
                alert.className = `md-alert md-alert--${type}`;
                const title = document.createElement('p');
                title.className = 'md-alert__title';
                title.textContent = alertTitles[type];
                alert.append(title, ...quote.childNodes);
                quote.replaceWith(alert);
            });

            return template.innerHTML;
        };

        // Source line of every list item, in document (pre-order) order.
        const listItemLines = (list, startLine) => {
            const lines = [];
            let cursor = 0;
            const walk = (items) => items.forEach((item) => {
                const at = list.raw.indexOf(item.raw, cursor);
                if (at === -1) {
                    lines.push((lines.at(-1) ?? startLine) + 1);
                } else {
                    cursor = at + 1;
                    lines.push(startLine + (list.raw.slice(0, at).match(/\n/g) || []).length);
                }
                (item.tokens || []).filter((child) => child.type === 'list').forEach((child) => walk(child.items));
            });
            walk(list.items);

            return lines;
        };

        // Render each top-level block on its own and tag it with its source line range,
        // so the editor and the preview can jump to each other.
        const renderMarkdownBlocks = (markdown) => {
            // Locate every token in the real source instead of summing token lengths: marked drops
            // some source (e.g. link reference definitions), which would shift every later block.
            let cursor = 0;
            let line = 0;
            const advance = (to) => {
                line += (markdown.slice(cursor, to).match(/\n/g) || []).length;
                cursor = to;
            };

            return marked.lexer(markdown).map((token) => {
                if (token.type !== 'space') {
                    const at = markdown.indexOf(token.raw, cursor);
                    if (at !== -1) {
                        advance(at);
                    }
                }

                const start = line;
                const last = start + (token.raw.replace(/\n+$/, '').match(/\n/g) || []).length;
                advance(Math.min(markdown.length, cursor + token.raw.length));

                if (token.type === 'space') {
                    return '';
                }

                const attrs = [`data-kind="${token.type}"`, `data-line-start="${start}"`, `data-line-end="${last}"`];
                if (token.type === 'code' && token.codeBlockStyle !== 'indented') {
                    attrs.push('data-fenced="true"');
                }
                if (token.type === 'list') {
                    attrs.push(`data-item-lines="${listItemLines(token, start).join(',')}"`);
                }

                return `<div class="md-src" ${attrs.join(' ')}>${marked.parser([token])}</div>`;
            }).join('');
        };

        // Give list items and table rows their own source line after sanitizing.
        const annotateSourceLines = () => {
            markdownPreviewContent.querySelectorAll('.md-src').forEach((block) => {
                const start = Number(block.dataset.lineStart);
                const itemLines = (block.dataset.itemLines || '').split(',');
                const end = Number(block.dataset.lineEnd);
                block.dataset.lines = end > start ? `L${start + 1}–${end + 1}` : `L${start + 1}`;

                if (block.dataset.kind === 'list') {
                    block.querySelectorAll('li').forEach((item, index) => {
                        if (itemLines[index] !== undefined && itemLines[index] !== '') {
                            item.dataset.line = itemLines[index];
                        }
                    });
                }

                if (block.dataset.kind === 'table') {
                    block.querySelectorAll('tr').forEach((row, index) => {
                        row.dataset.line = String(start + (index === 0 ? 0 : index + 1));
                    });
                }
            });
        };

        const renderMarkdownPreview = () => {
            if (markdownPreviewContent.dataset.toxicPreviewLocked === 'true') {
                return;
            }

            const markdown = markdownInput.value;
            const hasMarkdown = markdown.trim().length > 0;

            markdownPreviewEmpty.hidden = hasMarkdown;
            markdownPreviewContent.hidden = !hasMarkdown;
            markdownPreviewContent.innerHTML = hasMarkdown
                ? DOMPurify.sanitize(renderGithubAlerts(renderMarkdownBlocks(markdown)))
                : '';

            annotateSourceLines();
            markdownPreviewContent.querySelectorAll('a[href]').forEach((link) => {
                link.target = '_blank';
                link.rel = 'noopener noreferrer';
            });
            window.enhanceCodeBlocks?.(markdownPreviewContent);
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
            { trigger: 'table', label: 'Table', description: 'Choose columns and rows', fields: [
                { name: 'columns', label: 'Columns', type: 'number', value: '3', min: 1, max: 12 },
                { name: 'rows', label: 'Body rows', type: 'number', value: '3', min: 1, max: 30 },
            ], build: ({ columns, rows }) => {
                const header = `| ${Array.from({ length: Number(columns) }, (_, index) => `Column ${index + 1}`).join(' | ')} |`;
                const divider = `| ${Array.from({ length: Number(columns) }, () => '---').join(' | ')} |`;
                const body = Array.from({ length: Number(rows) }, () => `| ${Array.from({ length: Number(columns) }, () => ' ').join(' | ')} |`);
                return [header, divider, ...body].join('\n');
            } },
            { trigger: 'h1', label: 'Heading 1', description: 'Level 1 heading', value: '# Heading' },
            { trigger: 'h2', label: 'Heading 2', description: 'Level 2 heading', value: '## Heading' },
            { trigger: 'h3', label: 'Heading 3', description: 'Level 3 heading', value: '### Heading' },
            { trigger: 'bold', label: 'Bold', description: 'Strong emphasis', value: '**text**' },
            { trigger: 'italic', label: 'Italic', description: 'Light emphasis', value: '*text*' },
            { trigger: 'strike', label: 'Strikethrough', description: 'Deleted text', value: '~~text~~' },
            { trigger: 'inline-code', label: 'Inline code', description: 'Code within a sentence', value: '`code`' },
            { trigger: 'code', label: 'Code block', description: 'Choose a language', fields: [
                { name: 'language', label: 'Language', type: 'text', value: 'javascript', pattern: '[a-zA-Z0-9_+#.\\-]*' },
            ], build: ({ language }) => `\`\`\`${language}\ncode\n\`\`\`` },
            { trigger: 'quote', label: 'Quote', description: 'Blockquote', value: '> Quoted text' },
            { trigger: 'bullet-list', label: 'Bullet list', description: 'Unordered list', value: '- Item 1\n- Item 2' },
            { trigger: 'numbered-list', label: 'Numbered list', description: 'Ordered list', value: '1. Item 1\n2. Item 2' },
            { trigger: 'task-list', label: 'Task list', description: 'Checkbox list', value: '- [ ] Task' },
            { trigger: 'link', label: 'Link', description: 'Enter text and URL', fields: [
                { name: 'text', label: 'Link text', type: 'text', value: '' },
                { name: 'url', label: 'URL', type: 'url', value: '', required: true },
            ], build: ({ text, url }) => `[${escapeMarkdownLabel(text)}](<${escapeMarkdownUrl(url)}>)` },
            { trigger: 'image', label: 'Image', description: 'Enter alt text and image URL', fields: [
                { name: 'text', label: 'Alt text', type: 'text', value: '' },
                { name: 'url', label: 'Image URL', type: 'url', value: '', required: true },
            ], build: ({ text, url }) => `![${escapeMarkdownLabel(text)}](<${escapeMarkdownUrl(url)}>)` },
            { trigger: 'hr', label: 'Divider', description: 'Separate sections', value: '\n---\n' },
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
            commandTitle.textContent = 'Markdown commands';
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
                button.className = 'flex flex-col rounded-md px-3 py-2 text-left hover:bg-surface-high aria-selected:bg-sky-500/10';
                const title = document.createElement('span');
                title.className = 'text-sm text-on-surface';
                title.textContent = item.label;
                const description = document.createElement('span');
                description.className = 'text-xs text-outline';
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
                label.className = 'grid gap-1 text-sm text-on-surface';
                label.textContent = field.label;
                const input = document.createElement('input');
                input.dataset.commandField = field.name;
                input.type = field.type;
                input.value = field.value;
                input.className = 'w-full rounded-lg border border-surface-highest bg-surface-low px-3 py-2 text-on-surface focus:border-sky-400 focus:outline-none';
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
                input.setCustomValidity(field.required && !input.value.trim() ? 'Please enter a URL.' : '');
                if (!input.reportValidity()) {
                    return;
                }
                if (input.type === 'number' && !input.value) {
                    input.setCustomValidity('Please enter a whole number within the allowed range.');
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

        // ---- Two-way position sync between editor and preview ----
        let ignoreEditorScrollUntil = 0;
        let activeBlock = null;

        const mirrorProps = ['boxSizing', 'fontFamily', 'fontSize', 'fontWeight', 'fontStyle', 'letterSpacing', 'lineHeight', 'paddingTop', 'paddingRight', 'paddingBottom', 'paddingLeft', 'borderTopWidth', 'borderRightWidth', 'borderBottomWidth', 'borderLeftWidth', 'wordSpacing', 'tabSize', 'textIndent'];
        const textareaOffsetTop = (offset) => {
            const style = getComputedStyle(markdownInput);
            const mirror = document.createElement('div');
            mirrorProps.forEach((property) => { mirror.style[property] = style[property]; });
            Object.assign(mirror.style, { position: 'absolute', visibility: 'hidden', top: '0', left: '-9999px', whiteSpace: 'pre-wrap', overflowWrap: 'break-word', width: `${markdownInput.clientWidth + parseFloat(style.borderLeftWidth) + parseFloat(style.borderRightWidth)}px` });
            mirror.textContent = markdownInput.value.slice(0, offset);
            const marker = document.createElement('span');
            marker.textContent = '.';
            mirror.append(marker);
            document.body.append(mirror);
            const top = marker.offsetTop;
            mirror.remove();

            return top;
        };

        const lineStartOffset = (line) => {
            let offset = 0;
            for (let index = 0; index < line; index += 1) {
                const next = markdownInput.value.indexOf('\n', offset);
                if (next === -1) {
                    return markdownInput.value.length;
                }
                offset = next + 1;
            }

            return offset;
        };

        const setActiveBlock = (block) => {
            if (activeBlock === block) {
                return;
            }
            activeBlock?.classList.remove('md-src--active');
            activeBlock = block;
            block?.classList.add('md-src--active');
        };

        const blockForLine = (line) => {
            const blocks = [...markdownPreviewContent.querySelectorAll('.md-src')];
            const exact = blocks.find((block) => line >= Number(block.dataset.lineStart) && line <= Number(block.dataset.lineEnd));

            return exact ?? blocks.filter((block) => Number(block.dataset.lineStart) <= line).pop() ?? blocks[0] ?? null;
        };

        const codeLineHeight = (code) => parseFloat(getComputedStyle(code).lineHeight) || 24;

        // Source line for a click inside a preview block.
        const sourceLineAt = (block, target, clientY) => {
            const start = Number(block.dataset.lineStart);
            const end = Number(block.dataset.lineEnd);
            const marked = target.closest('[data-line]');

            if (marked && block.contains(marked)) {
                return Number(marked.dataset.line);
            }

            const code = target.closest('pre code');
            if (code && block.dataset.kind === 'code') {
                const rows = code.textContent.replace(/\n$/, '').split('\n').length;
                const index = Math.min(rows - 1, Math.max(0, Math.floor((clientY - code.getBoundingClientRect().top) / codeLineHeight(code))));

                return Math.min(end, start + (block.dataset.fenced ? 1 : 0) + index);
            }

            const box = block.getBoundingClientRect();
            const fraction = box.height > 0 ? (clientY - box.top) / box.height : 0;

            return Math.min(end, start + Math.floor(Math.max(0, fraction) * (end - start + 1)));
        };

        // Preview scroll position (relative to the preview content) for a source line.
        const previewYForLine = (line, block) => {
            const base = markdownPreviewContent.scrollTop - markdownPreviewContent.getBoundingClientRect().top;
            const start = Number(block.dataset.lineStart);
            const end = Number(block.dataset.lineEnd);
            const candidates = [...block.querySelectorAll('[data-line]')].filter((element) => Number(element.dataset.line) <= line);

            if (candidates.length > 0) {
                const best = candidates.reduce((current, element) => (Number(element.dataset.line) >= Number(current.dataset.line) ? element : current));

                return best.getBoundingClientRect().top + base;
            }

            const code = block.dataset.kind === 'code' ? block.querySelector('pre code') : null;
            if (code) {
                const index = Math.max(0, line - start - (block.dataset.fenced ? 1 : 0));

                return code.getBoundingClientRect().top + base + index * codeLineHeight(code);
            }

            const box = block.getBoundingClientRect();
            const fraction = end > start ? Math.min(1, Math.max(0, (line - start) / (end - start + 1))) : 0;

            return box.top + base + fraction * box.height;
        };

        // Editor caret -> preview.
        const syncPreviewToCaret = ({ smooth = true, onlyIfHidden = false } = {}) => {
            if (markdownLayout.dataset.view !== 'split') {
                return;
            }

            const line = markdownInput.value.slice(0, markdownInput.selectionStart).split('\n').length - 1;
            const block = blockForLine(line);
            setActiveBlock(block);

            if (!block) {
                return;
            }

            const targetY = previewYForLine(line, block);
            const visibleTop = markdownPreviewContent.scrollTop;
            const visibleBottom = visibleTop + markdownPreviewContent.clientHeight;

            if (onlyIfHidden && targetY > visibleTop + 24 && targetY < visibleBottom - 24) {
                return;
            }

            markdownPreviewContent.scrollTo({ top: Math.max(0, targetY - markdownPreviewContent.clientHeight * 0.35), behavior: smooth ? 'smooth' : 'auto' });
        };

        // Preview click -> editor.
        markdownPreviewContent.addEventListener('click', (event) => {
            if (markdownLayout.dataset.view !== 'split' || window.getSelection()?.toString() || event.target.closest('a, button')) {
                return;
            }

            const block = event.target.closest('.md-src');
            if (!block) {
                return;
            }

            const line = sourceLineAt(block, event.target, event.clientY);
            const from = lineStartOffset(line);
            const lineEnd = markdownInput.value.indexOf('\n', from);

            setActiveBlock(block);
            ignoreEditorScrollUntil = performance.now() + 300;
            markdownInput.focus({ preventScroll: true });
            markdownInput.setSelectionRange(from, lineEnd === -1 ? markdownInput.value.length : lineEnd);
            markdownInput.scrollTo({ top: Math.max(0, textareaOffsetTop(from) - markdownInput.clientHeight * 0.35), behavior: 'smooth' });
        });

        markdownInput.addEventListener('click', () => {
            ignoreEditorScrollUntil = performance.now() + 300;
            syncPreviewToCaret();
        });
        markdownInput.addEventListener('keyup', (event) => {
            if (['ArrowUp', 'ArrowDown', 'ArrowLeft', 'ArrowRight', 'PageUp', 'PageDown', 'Home', 'End'].includes(event.key)) {
                ignoreEditorScrollUntil = performance.now() + 300;
                syncPreviewToCaret();
            }
        });

        // ---- Line number gutter + caret status ----
        const gutterInner = document.querySelector('[data-line-gutter-inner]');
        const editorStatus = document.querySelector('[data-editor-status]');
        let gutterKey = '';
        let activeGutterRow = null;

        const markActiveLine = () => {
            const before = markdownInput.value.slice(0, markdownInput.selectionStart);
            const line = before.split('\n').length - 1;
            const column = before.length - (before.lastIndexOf('\n') + 1) + 1;
            const total = markdownInput.value.split('\n').length;

            activeGutterRow?.classList.remove('is-active');
            activeGutterRow = gutterInner?.children[line] ?? null;
            activeGutterRow?.classList.add('is-active');

            if (editorStatus) {
                editorStatus.textContent = `Ln ${line + 1}, Col ${column} · ${total} ${total === 1 ? 'line' : 'lines'}`;
            }
        };

        const syncGutterScroll = () => {
            if (gutterInner) {
                gutterInner.style.transform = `translateY(${-markdownInput.scrollTop}px)`;
            }
        };

        // One gutter row per source line, as tall as that line is once wrapped.
        const renderGutter = () => {
            if (!gutterInner || markdownInput.clientWidth === 0) {
                return;
            }

            const style = getComputedStyle(markdownInput);
            const mirror = document.createElement('div');
            mirrorProps.forEach((property) => { mirror.style[property] = style[property]; });
            Object.assign(mirror.style, { position: 'absolute', visibility: 'hidden', top: '0', left: '-9999px', whiteSpace: 'pre-wrap', overflowWrap: 'break-word', width: `${markdownInput.clientWidth + parseFloat(style.borderLeftWidth) + parseFloat(style.borderRightWidth)}px` });
            markdownInput.value.split('\n').forEach((text) => {
                const row = document.createElement('div');
                row.textContent = text === '' ? '\u200b' : text;
                mirror.append(row);
            });
            document.body.append(mirror);
            const heights = [...mirror.children].map((row) => row.offsetHeight);
            mirror.remove();

            const key = `${style.paddingTop}|${style.lineHeight}|${heights.join(',')}`;
            if (key !== gutterKey) {
                gutterKey = key;
                gutterInner.style.paddingTop = style.paddingTop;
                gutterInner.replaceChildren(...heights.map((height, index) => {
                    const row = document.createElement('div');
                    row.textContent = String(index + 1);
                    row.style.height = `${height}px`;
                    row.style.lineHeight = style.lineHeight;

                    return row;
                }));
                activeGutterRow = null;
            }

            syncGutterScroll();
            markActiveLine();
        };

        let gutterFrame = null;
        const scheduleGutter = () => {
            cancelAnimationFrame(gutterFrame);
            gutterFrame = requestAnimationFrame(renderGutter);
        };
        markdownInput.addEventListener('input', scheduleGutter);
        markdownInput.addEventListener('scroll', syncGutterScroll, { passive: true });
        document.addEventListener('selectionchange', () => {
            if (document.activeElement === markdownInput) {
                markActiveLine();
            }
        });
        window.addEventListener('resize', scheduleGutter);
        if (window.ResizeObserver) {
            new ResizeObserver(scheduleGutter).observe(markdownInput);
        }

        // ---- Focus mode: the whole Markdown editor fills the viewport ----
        const editorRoot = document.querySelector('[data-markdown-editor]');
        const fullscreenToggle = document.querySelector('[data-fullscreen-toggle]');
        const setFullscreen = (on) => {
            if (!editorRoot || !fullscreenToggle) {
                return;
            }

            closeCommands();
            editorRoot.classList.toggle('is-fullscreen', on);
            document.documentElement.classList.toggle('md-fullscreen', on);
            fullscreenToggle.setAttribute('aria-pressed', String(on));
            fullscreenToggle.setAttribute('aria-label', on ? 'Exit focus mode' : 'Focus mode (fullscreen)');
            fullscreenToggle.title = on ? 'Exit focus mode (Esc)' : 'Focus mode (fullscreen). Esc to exit';
            fullscreenToggle.querySelector('[data-fullscreen-icon]').textContent = on ? 'fullscreen_exit' : 'fullscreen';
            scheduleGutter();
            markdownInput.focus({ preventScroll: true });
            requestAnimationFrame(() => syncPreviewToCaret({ smooth: false }));
        };

        fullscreenToggle?.addEventListener('click', () => setFullscreen(!editorRoot.classList.contains('is-fullscreen')));
        document.addEventListener('keydown', (event) => {
            // Esc also closes the slash-command menu; that handler runs first and prevents default.
            if (event.key === 'Escape' && !event.defaultPrevented && editorRoot?.classList.contains('is-fullscreen')) {
                setFullscreen(false);
            }
        });

        const selectMarkdownView = (view) => {
            closeCommands();
            markdownLayout.dataset.view = view;
            markdownViewButtons.forEach((button) => {
                button.setAttribute('aria-pressed', String(button.dataset.markdownView === view));
            });

            markdownPanels.forEach((panel) => {
                panel.hidden = view !== 'split' && panel.dataset.markdownPanel !== view;
            });

            markdownInput.required = view !== 'preview';
            renderMarkdownPreview();
            scheduleGutter();

            try {
                localStorage.setItem('apollo.markdownView', view);
            } catch (e) {}
        };

        markdownViewButtons.forEach((button) => {
            button.addEventListener('click', () => selectMarkdownView(button.dataset.markdownView));
        });

        // Live preview: render on every keystroke, coalesced to one render per frame,
        // then keep the block being edited in view.
        let previewFrame = null;
        markdownInput.addEventListener('input', () => {
            cancelAnimationFrame(previewFrame);
            previewFrame = requestAnimationFrame(() => {
                renderMarkdownPreview();
                ignoreEditorScrollUntil = performance.now() + 300;
                syncPreviewToCaret({ smooth: false, onlyIfHidden: true });
            });
        });

        // Wheel/drag scrolling of the editor keeps the preview roughly aligned.
        markdownInput.addEventListener('scroll', () => {
            if (markdownLayout.dataset.view !== 'split' || performance.now() < ignoreEditorScrollUntil) {
                return;
            }

            const max = markdownInput.scrollHeight - markdownInput.clientHeight;
            const previewMax = markdownPreviewContent.scrollHeight - markdownPreviewContent.clientHeight;
            markdownPreviewContent.scrollTop = max > 0 ? (markdownInput.scrollTop / max) * previewMax : 0;
        });

        let savedView = 'split';
        try {
            savedView = localStorage.getItem('apollo.markdownView') || 'split';
        } catch (e) {}
        selectMarkdownView(['write', 'split', 'preview'].includes(savedView) ? savedView : 'split');

        const sidebarToggle = document.querySelector('[data-sidebar-toggle]');
        const syncSidebarToggle = () => {
            const collapsed = document.documentElement.classList.contains('sidebar-collapsed');
            sidebarToggle.setAttribute('aria-pressed', String(collapsed));
            sidebarToggle.querySelector('[data-sidebar-toggle-label]').textContent = collapsed ? 'Show sidebar' : 'Hide sidebar';
            sidebarToggle.querySelector('.material-symbols-outlined').textContent = collapsed ? 'left_panel_open' : 'left_panel_close';
        };
        sidebarToggle?.addEventListener('click', () => {
            window.toggleAdminSidebar?.();
            syncSidebarToggle();
        });
        syncSidebarToggle();

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

            const lower = tagName.toLowerCase();
            const existing = [...document.querySelectorAll('[data-tag-chip]')].find((chip) => chip.dataset.tagName === lower);

            // An existing tag is selected instead of being created twice.
            if (existing) {
                const checkbox = existing.querySelector('input');
                checkbox.checked = true;
                checkbox.dispatchEvent(new Event('change', { bubbles: true }));
                existing.scrollIntoView({ block: 'nearest' });
                tagInput.value = '';

                return;
            }

            if ([...newTagsList.querySelectorAll('input[name="new_tags[]"]')].some((input) => input.value.toLowerCase() === lower)) {
                tagInput.value = '';

                return;
            }

            const tagItem = document.createElement('li');
            tagItem.className = 'inline-flex items-center gap-2 rounded-full border border-sky-400 bg-sky-500/15 px-3 py-1 text-xs text-sky-200';

            const hiddenInput = document.createElement('input');
            hiddenInput.type = 'hidden';
            hiddenInput.name = 'new_tags[]';
            hiddenInput.value = tagName;

            const tagLabel = document.createElement('span');
            tagLabel.textContent = `#${tagName} `;
            const badge = document.createElement('span');
            badge.className = 'text-sky-300/70';
            badge.textContent = '(new)';
            tagLabel.append(badge);

            const removeButton = document.createElement('button');
            removeButton.type = 'button';
            removeButton.dataset.removeNewTag = '';
            removeButton.setAttribute('aria-label', `Remove tag ${tagName}`);
            removeButton.className = 'text-sky-200 hover:text-white';
            removeButton.innerHTML = '&times;';

            tagItem.append(hiddenInput, tagLabel, removeButton);
            newTagsList.append(tagItem);
            tagInput.value = '';
            updateTagCount();
            form?.dispatchEvent(new Event('change', { bubbles: true }));
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
                updateTagCount();
                form?.dispatchEvent(new Event('change', { bubbles: true }));
            }
        });
    })();
</script>
