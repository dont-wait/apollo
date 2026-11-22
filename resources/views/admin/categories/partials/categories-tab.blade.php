<!-- <div
    id="categoryManager"
    data-store-url="{{ route('admin.categories.store') }}"
    data-csrf-token="{{ csrf_token() }}"
>

    {{-- =====================================================
        CATEGORY STATISTICS
    ====================================================== --}}
    <section class="mb-6 grid gap-4 sm:grid-cols-2 xl:grid-cols-4">

        {{-- TOTAL --}}
        <article class="rounded-xl border border-surface-high bg-surface-low p-5">
            <div class="flex items-start justify-between">

                <div>
                    <p class="text-[10px] uppercase tracking-widest text-outline">
                        Total Categories
                    </p>

                    <p class="mt-3 text-3xl font-semibold">
                        {{ $categories->count() }}
                    </p>
                </div>

                <span class="material-symbols-outlined text-primary">
                    category
                </span>

            </div>
        </article>


        {{-- ACTIVE --}}
        <article class="rounded-xl border border-surface-high bg-surface-low p-5">
            <div class="flex items-start justify-between">

                <div>
                    <p class="text-[10px] uppercase tracking-widest text-outline">
                        Active
                    </p>

                    <p class="mt-3 text-3xl font-semibold text-tertiary">
                        {{ $categories->where('status', 'ACTIVE')->count() }}
                    </p>
                </div>

                <span class="material-symbols-outlined text-tertiary">
                    check_circle
                </span>

            </div>
        </article>


        {{-- ROOT --}}
        <article class="rounded-xl border border-surface-high bg-surface-low p-5">
            <div class="flex items-start justify-between">

                <div>
                    <p class="text-[10px] uppercase tracking-widest text-outline">
                        Root Categories
                    </p>

                    <p class="mt-3 text-3xl font-semibold">
                        {{ $categories->whereNull('parent_id')->count() }}
                    </p>
                </div>

                <span class="material-symbols-outlined text-secondary">
                    account_tree
                </span>

            </div>
        </article>


        {{-- CHILD --}}
        <article class="rounded-xl border border-surface-high bg-surface-low p-5">
            <div class="flex items-start justify-between">

                <div>
                    <p class="text-[10px] uppercase tracking-widest text-outline">
                        Child Categories
                    </p>

                    <p class="mt-3 text-3xl font-semibold">
                        {{ $categories->whereNotNull('parent_id')->count() }}
                    </p>
                </div>

                <span class="material-symbols-outlined text-amber-300">
                    schema
                </span>

            </div>
        </article>

    </section>


    {{-- =====================================================
        CONTENT + EDITOR
    ====================================================== --}}
    <div class="grid gap-6 lg:grid-cols-[minmax(0,1fr)_360px]">

        {{-- LEFT --}}
        <section class="min-w-0 space-y-4">

            {{-- SEARCH + FILTER --}}
            <div class="flex flex-col gap-3 rounded-xl border border-surface-high bg-surface-low p-4 lg:flex-row">

                <div class="relative flex-1">

                    <span
                        class="material-symbols-outlined absolute left-3 top-1/2 -translate-y-1/2 text-[18px] text-outline"
                    >
                        search
                    </span>

                    <input
                        type="text"
                        id="categorySearch"
                        placeholder="Tìm category theo tên hoặc slug..."
                        class="w-full rounded-lg border border-surface-highest bg-background py-2.5 pl-10 pr-4 text-sm text-on-surface outline-none placeholder:text-outline focus:border-primary"
                    >

                </div>


                <select
                    id="statusFilter"
                    class="rounded-lg border border-surface-highest bg-background px-4 py-2.5 text-sm text-on-surface-variant outline-none focus:border-primary"
                >
                    <option value="ALL">
                        All Status
                    </option>

                    <option value="ACTIVE">
                        Active
                    </option>

                    <option value="INACTIVE">
                        Inactive
                    </option>
                </select>

            </div>


            {{-- CATEGORY LIST --}}
            <div class="overflow-hidden rounded-xl border border-surface-high bg-surface-low">

                @if ($categories->isEmpty())

                    {{-- EMPTY STATE --}}
                    <div class="p-12 text-center">

                        <span class="material-symbols-outlined text-4xl text-primary">
                            label
                        </span>

                        <h2 class="mt-3 font-semibold">
                            Chưa có category nào
                        </h2>

                        <p class="mt-1 text-sm text-outline">
                            Tạo category đầu tiên bằng form bên phải.
                        </p>

                    </div>

                @else

                    <div class="overflow-x-auto">

                        <table class="w-full min-w-[760px] text-left text-sm">

                            {{-- HEADER --}}
                            <thead class="border-b border-surface-highest bg-surface-lowest text-[10px] uppercase tracking-wider text-outline">

                                <tr>
                                    <th class="px-5 py-3 font-medium">
                                        Category
                                    </th>

                                    <th class="px-4 py-3 font-medium">
                                        Slug
                                    </th>

                                    <th class="px-4 py-3 font-medium">
                                        Parent
                                    </th>

                                    <th class="px-4 py-3 text-center font-medium">
                                        Children
                                    </th>

                                    <th class="px-4 py-3 font-medium">
                                        Status
                                    </th>

                                    <th class="px-5 py-3 text-right font-medium">
                                        Actions
                                    </th>
                                </tr>

                            </thead>


                            {{-- BODY --}}
                            <tbody
                                id="categoryTable"
                                class="divide-y divide-surface-highest/70"
                            >

                                @foreach ($categories as $category)

                                    <tr
                                        class="category-row transition-colors hover:bg-surface"
                                        data-name="{{ $category->name }}"
                                        data-slug="{{ $category->slug }}"
                                        data-status="{{ $category->status }}"
                                    >

                                        {{-- CATEGORY --}}
                                        <td class="px-5 py-4">

                                            <div class="flex items-center gap-3">

                                                <span class="material-symbols-outlined text-[19px] text-primary">
                                                    label
                                                </span>

                                                <div>

                                                    <p class="font-semibold">
                                                        {{ $category->name }}
                                                    </p>

                                                    <p class="mt-0.5 text-xs text-outline">
                                                        #{{ $category->id }}
                                                    </p>

                                                </div>

                                            </div>

                                        </td>


                                        {{-- SLUG --}}
                                        <td class="px-4 py-4 font-mono text-xs text-on-surface-variant">
                                            /categories/{{ $category->slug }}
                                        </td>


                                        {{-- PARENT --}}
                                        <td class="px-4 py-4 text-on-surface-variant">
                                            {{ $category->parent?->name ?? 'Root' }}
                                        </td>


                                        {{-- CHILDREN --}}
                                        <td class="px-4 py-4 text-center text-on-surface-variant">
                                            {{ $category->children_count }}
                                        </td>


                                        {{-- STATUS --}}
                                        <td class="px-4 py-4">

                                            <span @class([
                                                'inline-flex items-center gap-1.5 rounded px-2.5 py-1 text-[10px] font-medium',
                                                'bg-tertiary/15 text-tertiary' => $category->status === 'ACTIVE',
                                                'bg-surface-highest text-on-surface-variant' => $category->status !== 'ACTIVE',
                                            ])>

                                                <span @class([
                                                    'h-1.5 w-1.5 rounded-full',
                                                    'bg-tertiary' => $category->status === 'ACTIVE',
                                                    'bg-outline' => $category->status !== 'ACTIVE',
                                                ])></span>

                                                {{ $category->status }}

                                            </span>

                                        </td>


                                        {{-- ACTIONS --}}
                                        <td class="px-5 py-4">

                                            <div class="flex justify-end gap-2">

                                                {{-- EDIT --}}
                                                <button
                                                    type="button"
                                                    class="edit-category rounded-md p-2 text-primary transition hover:bg-surface-high"
                                                    title="Edit category"

                                                    data-id="{{ $category->id }}"
                                                    data-name="{{ $category->name }}"
                                                    data-slug="{{ $category->slug }}"
                                                    data-parent-id="{{ $category->parent_id }}"
                                                    data-description="{{ $category->description }}"
                                                    data-color="{{ $category->color }}"
                                                    data-icon="{{ $category->icon }}"
                                                    data-status="{{ $category->status }}"
                                                    data-update-url="{{ route('admin.categories.update', $category) }}"
                                                >
                                                    <span class="material-symbols-outlined text-[18px]">
                                                        edit
                                                    </span>
                                                </button>


                                                {{-- ACTIVE / INACTIVE --}}
                                                <button
                                                    type="button"
                                                    class="toggle-category-status rounded-md p-2 text-outline transition hover:bg-surface-high"
                                                    title="{{ $category->status === 'ACTIVE'
                                                        ? 'Deactivate category'
                                                        : 'Activate category' }}"

                                                    data-status="{{ $category->status }}"
                                                    data-status-url="{{ route('admin.categories.update-status', $category) }}"
                                                >
                                                    <span class="material-symbols-outlined text-[18px]">
                                                        power_settings_new
                                                    </span>
                                                </button>

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


        {{-- =================================================
            CATEGORY EDITOR
        ================================================== --}}
        <aside>

            <div class="sticky top-6 rounded-xl border border-surface-high bg-surface-low">

                {{-- HEADER --}}
                <div class="border-b border-surface-highest px-5 py-4">

                    <p class="text-[10px] uppercase tracking-widest text-primary">
                        Category Editor
                    </p>

                    <h2
                        id="categoryEditorTitle"
                        class="mt-1 text-lg font-semibold"
                    >
                        New Category
                    </h2>

                </div>


                {{-- FORM --}}
                <form
                    id="categoryForm"
                    class="space-y-5 p-5"
                >

                    @csrf


                    {{-- NAME --}}
                    <div>

                        <label
                            for="categoryName"
                            class="mb-2 block text-xs font-medium text-on-surface-variant"
                        >
                            Name
                        </label>

                        <input
                            id="categoryName"
                            name="name"
                            type="text"
                            maxlength="100"
                            required
                            placeholder="VD: AI Engineering"
                            class="w-full rounded-lg border border-surface-highest bg-background px-3 py-2.5 text-sm outline-none placeholder:text-outline focus:border-primary"
                        >

                    </div>


                    {{-- SLUG --}}
                    <div>

                        <label
                            for="categorySlug"
                            class="mb-2 block text-xs font-medium text-on-surface-variant"
                        >
                            Slug
                        </label>

                        <input
                            id="categorySlug"
                            name="slug"
                            type="text"
                            maxlength="120"
                            required
                            placeholder="ai-engineering"
                            class="w-full rounded-lg border border-surface-highest bg-background px-3 py-2.5 font-mono text-sm outline-none placeholder:text-outline focus:border-primary"
                        >

                    </div>


                    {{-- PARENT --}}
                    <div>

                        <label
                            for="categoryParent"
                            class="mb-2 block text-xs font-medium text-on-surface-variant"
                        >
                            Parent Category
                        </label>

                        <select
                            id="categoryParent"
                            name="parent_id"
                            class="w-full rounded-lg border border-surface-highest bg-background px-3 py-2.5 text-sm outline-none focus:border-primary"
                        >

                            <option value="">
                                None — Root Category
                            </option>

                           @foreach ($categories as $category)
                                <option
                                    value="{{ $category->id }}"
                                    data-parent-id="{{ $category->parent_id }}"
                                >
                                    {{ $category->name }}
                                </option>
                            @endforeach

                        </select>

                    </div>


                    {{-- DESCRIPTION --}}
                    <div>

                        <label
                            for="categoryDescription"
                            class="mb-2 block text-xs font-medium text-on-surface-variant"
                        >
                            Description
                        </label>

                        <textarea
                            id="categoryDescription"
                            name="description"
                            rows="4"
                            maxlength="500"
                            placeholder="Mô tả ngắn cho category..."
                            class="w-full resize-none rounded-lg border border-surface-highest bg-background px-3 py-2.5 text-sm outline-none placeholder:text-outline focus:border-primary"
                        ></textarea>

                    </div>

                    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                        <div>
                            <label
                                for="categoryColor"
                                class="mb-2 block text-xs font-medium text-on-surface-variant"
                            >
                                Color Accent
                            </label>

                            <input
                                id="categoryColor"
                                name="color"
                                type="color"
                                value="#38bdf8"
                                class="h-10 w-full cursor-pointer rounded-lg border border-outline-variant bg-background p-1"
                            >
                        </div>

                        <div>
                            <label
                                for="categoryIcon"
                                class="mb-2 block text-xs font-medium text-on-surface-variant"
                            >
                                Icon Symbol
                            </label>

                            <input
                                id="categoryIcon"
                                name="icon"
                                type="text"
                                maxlength="50"
                                placeholder="psychology"
                                class="w-full rounded-lg border border-outline-variant bg-background px-3 py-2 text-sm text-on-surface outline-none transition focus:border-primary"
                            >
                        </div>
                    </div>


                    {{-- STATUS --}}
                    <input
                        type="hidden"
                        id="categoryStatus"
                        name="status"
                        value="ACTIVE"
                    >


                    {{-- ERROR --}}
                    <div
                        id="categoryFormError"
                        class="hidden rounded-lg border border-error/40 bg-error/15 px-3 py-2 text-xs text-error"
                    ></div>


                    {{-- SAVE --}}
                    <button
                        type="submit"
                        id="saveCategoryButton"
                        class="flex w-full items-center justify-center gap-2 rounded-lg bg-primary px-4 py-2.5 text-sm font-semibold text-surface-lowest transition hover:bg-primary-fixed"
                    >

                        <span class="material-symbols-outlined text-[18px]">
                            save
                        </span>

                        <span id="saveCategoryButtonText">
                            Create Category
                        </span>

                    </button>


                    {{-- CANCEL --}}
                    <button
                        type="button"
                        id="cancelCategoryEdit"
                        class="hidden w-full rounded-lg border border-surface-highest px-4 py-2.5 text-sm text-on-surface-variant transition hover:bg-surface-high"
                    >
                        Cancel Edit
                    </button>

                </form>

            </div>

        </aside>

    </div>

</div> -->

<div
    id="categoryManager"
    data-store-url="{{ route('admin.categories.store') }}"
    data-csrf-token="{{ csrf_token() }}">
    @php
    $totalCategories = $categories->count();
    $activeCategories = $categories->where('status', 'ACTIVE')->count();
    $rootCategories = $categories->whereNull('parent_id')->count();
    $nestedCategories = $categories->whereNotNull('parent_id')->count();
    $totalTags = isset($tags) ? $tags->count() : 0;

    $orderedCategories = collect();
    $maxDepth = 0;

    $walkCategories = function ($parentId = null, $depth = 0) use (&$walkCategories, &$orderedCategories, &$maxDepth, $categories) {
    $children = $categories
    ->filter(fn ($category) => $category->parent_id === $parentId)
    ->sortBy('name');

    foreach ($children as $category) {
    $orderedCategories->push([
    'category' => $category,
    'depth' => $depth,
    ]);

    $maxDepth = max($maxDepth, $depth);
    $walkCategories($category->id, $depth + 1);
    }
    };

    $walkCategories();
    @endphp

    <section class="mb-5 grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
        <article class="border border-surface-high bg-surface-low p-4">
            <div class="flex items-start justify-between gap-4">
                <div>
                    <p class="font-mono text-[9px] uppercase tracking-[0.14em] text-outline">
                        Total Categories
                    </p>
                    <div class="mt-2 flex items-end gap-2">
                        <span class="text-3xl font-semibold leading-none text-on-surface">
                            {{ $totalCategories }}
                        </span>
                        <span class="pb-0.5 font-mono text-[9px] text-outline">
                            / {{ $rootCategories }} Root, {{ $nestedCategories }} Nested
                        </span>
                    </div>
                </div>
                <div class="flex h-9 w-9 items-center justify-center border border-outline-variant bg-surface-low text-primary">
                    <span class="material-symbols-outlined text-[20px]">folder_copy</span>
                </div>
            </div>
        </article>

        <article class="border border-surface-high bg-surface-low p-4">
            <div class="flex items-start justify-between gap-4">
                <div>
                    <p class="font-mono text-[9px] uppercase tracking-[0.14em] text-outline">
                        Active Coverage
                    </p>
                    <div class="mt-2 flex items-end gap-2">
                        <span class="text-3xl font-semibold leading-none text-tertiary">
                            {{ $totalCategories > 0 ? (int) round(($activeCategories / $totalCategories) * 100) : 0 }}%
                        </span>
                        <span class="pb-0.5 font-mono text-[9px] text-outline">
                            {{ $activeCategories }}/{{ $totalCategories }} Active
                        </span>
                    </div>
                </div>
                <div class="flex h-9 w-9 items-center justify-center border border-surface-high bg-surface-low text-tertiary">
                    <span class="material-symbols-outlined text-[20px]">verified</span>
                </div>
            </div>
        </article>

        <article class="border border-surface-high bg-surface-low p-4">
            <div class="flex items-start justify-between gap-4">
                <div>
                    <p class="font-mono text-[9px] uppercase tracking-[0.14em] text-outline">
                        Total Tags Schema
                    </p>
                    <div class="mt-2 flex items-end gap-2">
                        <span class="text-3xl font-semibold leading-none text-secondary">
                            {{ $totalTags }}
                        </span>
                        <span class="pb-0.5 font-mono text-[9px] text-outline">
                            Taxonomy Tags
                        </span>
                    </div>
                </div>
                <div class="flex h-9 w-9 items-center justify-center border border-surface-high bg-secondary/15 text-secondary">
                    <span class="material-symbols-outlined text-[20px]">sell</span>
                </div>
            </div>
        </article>

        <article class="border border-surface-high bg-surface-low p-4">
            <div class="flex items-start justify-between gap-4">
                <div>
                    <p class="font-mono text-[9px] uppercase tracking-[0.14em] text-outline">
                        Hierarchy Depth
                    </p>
                    <div class="mt-2 flex items-end gap-2">
                        <span class="text-3xl font-semibold leading-none text-primary">
                            {{ $totalCategories > 0 ? $maxDepth + 1 : 0 }}
                        </span>
                        <span class="pb-0.5 font-mono text-[9px] text-tertiary">
                            LEVELS
                        </span>
                    </div>
                </div>
                <div class="flex h-9 w-9 items-center justify-center border border-primary/30 bg-primary/10 text-primary">
                    <span class="material-symbols-outlined text-[20px]">account_tree</span>
                </div>
            </div>
        </article>
    </section>

    <div class="grid gap-5 xl:grid-cols-[minmax(0,1fr)_330px]">
        <section class="min-w-0">
            <div class="mb-3 flex flex-col gap-2 border border-surface-high bg-surface-lowest p-3 lg:flex-row lg:items-center">
                <div class="relative min-w-0 flex-1">
                    <span class="material-symbols-outlined absolute left-3 top-1/2 -translate-y-1/2 text-[17px] text-outline">
                        search
                    </span>
                    <input
                        id="categorySearch"
                        type="text"
                        placeholder="Tìm danh mục theo tên hoặc slug..."
                        class="w-full border border-surface-high bg-surface-lowest py-2 pl-9 pr-3 font-mono text-[11px] text-on-surface outline-none placeholder:text-outline focus:border-primary">
                </div>

                <select
                    id="statusFilter"
                    class="border border-surface-high bg-surface-lowest px-3 py-2 font-mono text-[10px] text-on-surface outline-none focus:border-primary">
                    <option value="ALL">Status: ALL ({{ $totalCategories }})</option>
                    <option value="ACTIVE">Status: ACTIVE</option>
                    <option value="INACTIVE">Status: INACTIVE</option>
                </select>

                <select
                    id="hierarchyFilter"
                    class="border border-surface-high bg-surface-lowest px-3 py-2 font-mono text-[10px] text-on-surface outline-none focus:border-primary">
                    <option value="ALL">Hierarchy: ALL Levels</option>
                    <option value="ROOT">Root Only</option>
                    <option value="NESTED">Nested Only</option>
                </select>

                <button
                    id="refreshCategoryView"
                    type="button"
                    class="flex h-9 w-9 shrink-0 items-center justify-center border border-surface-high bg-surface-lowest text-primary transition hover:bg-surface"
                    title="Refresh category view">
                    <span class="material-symbols-outlined text-[18px]">refresh</span>
                </button>
            </div>

            <div class="overflow-hidden border border-surface-high bg-surface-lowest">
                @if ($categories->isEmpty())
                <div class="px-6 py-16 text-center">
                    <span class="material-symbols-outlined text-4xl text-primary">account_tree</span>
                    <p class="mt-3 font-semibold text-on-surface">Chưa có category nào</p>
                    <p class="mt-1 text-sm text-outline">
                        Tạo category đầu tiên bằng Schema Inspector bên phải.
                    </p>
                </div>
                @else
                <div class="overflow-x-auto">
                    <table class="w-full min-w-[760px] table-fixed text-left">
                        <thead class="border-b border-surface-high bg-surface-lowest">
                            <tr class="font-mono text-[9px] uppercase tracking-[0.12em] text-outline">
                                <th class="w-[36%] px-4 py-3 font-medium">Category / Hierarchy</th>
                                <th class="w-[27%] px-3 py-3 font-medium">Slug (Route)</th>
                                <th class="w-[15%] px-3 py-3 text-center font-medium">Posts Count</th>
                                <th class="w-[22%] px-3 py-3 font-medium">Parent Link</th>
                            </tr>
                        </thead>

                        <tbody id="categoryTable" class="divide-y divide-surface-low">
                            @foreach ($orderedCategories as $node)
                            @php
                            $category = $node['category'];
                            $depth = $node['depth'];
                            $accent = $category->color ?: '#38bdf8';
                            $icon = $category->icon ?: ($depth === 0 ? 'hub' : 'adjust');
                            @endphp

                            <tr
                                class="category-row group transition-colors hover:bg-surface-lowest {{ $category->status !== 'ACTIVE' ? 'opacity-55' : '' }}"
                                data-name="{{ $category->name }}"
                                data-slug="{{ $category->slug }}"
                                data-status="{{ $category->status }}"
                                data-depth="{{ $depth }}">
                                <td class="px-4 py-3 align-top">
                                    <button
                                        type="button"
                                        class="edit-category block w-full text-left"
                                        data-id="{{ $category->id }}"
                                        data-name="{{ $category->name }}"
                                        data-slug="{{ $category->slug }}"
                                        data-parent-id="{{ $category->parent_id }}"
                                        data-description="{{ $category->description }}"
                                        data-color="{{ $category->color }}"
                                        data-icon="{{ $category->icon }}"
                                        data-status="{{ $category->status }}"
                                        data-update-url="{{ route('admin.categories.update', $category) }}">
                                        <div
                                            class="flex min-w-0 items-start gap-2"
                                            @style([ 'padding-left: ' . (min($depth, 5) * 18) . 'px' ,
                                            ])>
                                            @if ($depth > 0)
                                            <span class="mt-[8px] font-mono text-[11px] text-outline">
                                                └─
                                            </span>
                                            @endif

                                            <span
                                                class="mt-[7px] h-1.5 w-1.5 shrink-0 rounded-full"
                                                @style([ 'background-color: ' . $accent,
                                                ])></span>

                                            <span
                                                class="material-symbols-outlined mt-[1px] shrink-0 text-[17px]"
                                                @style([ 'color: ' . $accent,
                                                ])>
                                                {{ $icon }}
                                            </span>

                                            <span class="min-w-0">
                                                <span class="block truncate text-[12px] font-semibold text-on-surface">
                                                    {{ $category->name }}
                                                </span>

                                                <span class="mt-0.5 block truncate font-mono text-[9px] text-outline">
                                                    {{ \Illuminate\Support\Str::limit(
                $category->description ?: 'No description',
                28
            ) }}
                                                </span>
                                            </span>
                                        </div>
                                    </button>
                                </td>

                                <td class="px-3 py-3 align-middle">
                                    <div class="flex items-center gap-2">
                                        <code class="min-w-0 flex-1 break-all border border-surface-high bg-surface-low px-2 py-1 font-mono text-[9px] leading-4 text-primary">
                                            /category/{{ $category->slug }}
                                        </code>

                                        <button
                                            type="button"
                                            class="copy-category-slug shrink-0 text-outline transition hover:text-primary"
                                            data-slug="/category/{{ $category->slug }}"
                                            title="Copy route">
                                            <span class="material-symbols-outlined text-[14px]">content_copy</span>
                                        </button>
                                    </div>
                                </td>

                                <td class="px-3 py-3 text-center align-middle">
                                    <span class="inline-flex items-center gap-1 border border-surface-high bg-surface-low px-2 py-1 font-mono text-[9px] text-on-surface">
                                        <span class="material-symbols-outlined text-[12px]">description</span>
                                        {{ $category->posts_count }}
                                    </span>
                                </td>

                                <td class="px-3 py-3 align-middle">
                                    <div class="flex items-center justify-between gap-2">
                                        @if ($category->parent)
                                        <span class="truncate font-mono text-[9px] text-on-surface-variant">
                                            {{ $category->parent->name }}
                                        </span>
                                        @else
                                        <span class="border border-surface-high bg-surface-low px-2 py-1 font-mono text-[9px] text-outline">
                                            None (Root)
                                        </span>
                                        @endif

                                        <button
                                            type="button"
                                            class="toggle-category-status invisible shrink-0 text-outline transition hover:text-primary group-hover:visible"
                                            title="{{ $category->status === 'ACTIVE' ? 'Deactivate category' : 'Activate category' }}"
                                            data-status="{{ $category->status }}"
                                            data-status-url="{{ route('admin.categories.update-status', $category) }}">
                                            <span class="material-symbols-outlined text-[16px]">power_settings_new</span>
                                        </button>
                                    </div>
                                </td>
                            </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>

                <div class="flex flex-col gap-2 border-t border-surface-high bg-surface-lowest px-4 py-3 sm:flex-row sm:items-center sm:justify-between">
                    <div class="font-mono text-[9px] text-outline">
                        Showing {{ $rootCategories }} top-level hierarchies
                        ({{ $totalCategories }} total items)
                    </div>

                    <div class="flex items-center gap-4 font-mono text-[9px] text-outline">
                        <span>
                            Tree depth level:
                            <strong class="text-on-surface">
                                Max {{ $totalCategories > 0 ? $maxDepth + 1 : 0 }}
                            </strong>
                        </span>
                        <span class="border border-primary/30 bg-surface-low px-2 py-1 text-primary">
                            Page 1 of 1
                        </span>
                    </div>
                </div>
                @endif
            </div>
        </section>

        <aside class="min-w-0">
            <div class="sticky top-6 border border-surface-high bg-surface-lowest">
                <div class="border-b border-surface-high px-4 py-3">
                    <div class="flex items-start justify-between gap-3">
                        <div>
                            <h2 id="categoryEditorTitle" class="mt-1 text-sm font-semibold text-on-surface">
                                New Category
                            </h2>
                        </div>

                        <label class="flex items-center gap-2 font-mono text-[9px] text-on-surface">
                            Active
                            <input
                                id="categoryActiveCheckbox"
                                type="checkbox"
                                checked
                                class="h-3.5 w-3.5 accent-primary">
                        </label>
                    </div>
                </div>

                <form id="categoryForm" class="space-y-4 p-4">
                    @csrf

                    <div>
                        <div class="mb-1.5 flex items-center justify-between gap-3">
                            <label for="categoryName" class="font-mono text-[10px] font-semibold text-on-surface">
                                Tên Danh mục (Category Name) *
                            </label>
                            <span class="font-mono text-[8px] text-outline">Max 100 ký tự</span>
                        </div>

                        <input
                            id="categoryName"
                            name="name"
                            type="text"
                            maxlength="100"
                            required
                            placeholder="LLM & Generative Systems"
                            class="w-full border border-surface-high bg-surface-lowest px-3 py-2.5 text-xs text-on-surface outline-none placeholder:text-outline focus:border-primary">
                    </div>

                    <div>
                        <div class="mb-1.5 flex items-center justify-between gap-3">
                            <label for="categorySlug" class="font-mono text-[10px] font-semibold text-on-surface">
                                Slug URL duy nhất *
                            </label>
                            <span class="font-mono text-[8px] text-primary">Generate from Name</span>
                        </div>

                        <div class="flex items-center border border-surface-high bg-surface-lowest focus-within:border-primary">
                            <span class="pl-3 font-mono text-[10px] text-outline">/category/</span>
                            <input
                                id="categorySlug"
                                name="slug"
                                type="text"
                                maxlength="120"
                                required
                                placeholder="llm-generative-systems"
                                class="min-w-0 flex-1 bg-transparent px-1 py-2.5 font-mono text-[10px] text-primary outline-none">
                            <span class="material-symbols-outlined mr-2 text-[15px] text-tertiary">check_circle</span>
                        </div>

                        <p class="mt-1.5 font-mono text-[8px] text-tertiary">
                            ✓ Canonical path khả dụng & không trùng lặp
                        </p>
                    </div>

                    <div>
                        <label for="categoryParent" class="mb-1.5 block font-mono text-[10px] font-semibold text-on-surface">
                            Danh mục cha (Parent Category)
                        </label>

                        <select
                            id="categoryParent"
                            name="parent_id"
                            class="w-full border border-surface-high bg-surface-lowest px-3 py-2.5 text-[11px] text-on-surface outline-none focus:border-primary">
                            <option value="">None — Là danh mục gốc (Root Level)</option>

                            @foreach ($categories as $category)
                            <option
                                value="{{ $category->id }}"
                                data-parent-id="{{ $category->parent_id }}">
                                {{ $category->name }}
                            </option>
                            @endforeach
                        </select>
                    </div>

                    <div>
                        <label for="categoryDescription" class="mb-1.5 block font-mono text-[10px] font-semibold text-on-surface">
                            Mô tả chủ đề (Description / SEO Snippet)
                        </label>

                        <textarea
                            id="categoryDescription"
                            name="description"
                            rows="4"
                            maxlength="500"
                            placeholder="Nghiên cứu kiến trúc, mô hình, chủ đề kỹ thuật..."
                            class="w-full resize-none border border-surface-high bg-surface-lowest px-3 py-2.5 text-[10px] leading-5 text-on-surface outline-none placeholder:text-outline focus:border-primary"></textarea>
                    </div>

                    <div class="grid grid-cols-2 gap-2">
                        <div>
                            <label for="categoryColor" class="mb-1.5 block font-mono text-[10px] font-semibold text-on-surface">
                                Color Accent
                            </label>

                            <div class="flex h-9 items-center gap-2 border border-surface-high bg-surface-lowest px-2">
                                <input
                                    id="categoryColor"
                                    name="color"
                                    type="color"
                                    value="#38bdf8"
                                    class="h-5 w-6 cursor-pointer border-0 bg-transparent p-0">
                                <span id="categoryColorValue" class="font-mono text-[9px] text-on-surface-variant">
                                    #38bdf8
                                </span>
                            </div>
                        </div>

                        <div>
                            <label for="categoryIcon" class="mb-1.5 block font-mono text-[10px] font-semibold text-on-surface">
                                Icon Symbol
                            </label>

                            <div class="flex h-9 items-center gap-2 border border-surface-high bg-surface-lowest px-2">
                                <span class="material-symbols-outlined text-[16px] text-primary">psychology</span>
                                <input
                                    id="categoryIcon"
                                    name="icon"
                                    type="text"
                                    maxlength="50"
                                    placeholder="psychology"
                                    class="min-w-0 flex-1 bg-transparent font-mono text-[9px] text-on-surface-variant outline-none">
                            </div>
                        </div>
                    </div>

                    <input id="categoryStatus" name="status" type="hidden" value="ACTIVE">

                    <div
                        id="categoryFormError"
                        class="hidden border border-error/40 bg-error/15 px-3 py-2 text-[10px] text-error"></div>

                    <div class="grid grid-cols-[1fr_auto] gap-2">
                        <button
                            id="saveCategoryButton"
                            type="submit"
                            class="flex items-center justify-center gap-2 bg-primary-fixed-dim px-4 py-2.5 text-[11px] font-semibold text-surface-lowest transition hover:bg-primary">
                            <span class="material-symbols-outlined text-[16px]">save</span>
                            <span id="saveCategoryButtonText">Create Category</span>
                        </button>

                        <button
                            id="cancelCategoryEdit"
                            type="button"
                            class="hidden border border-surface-highest bg-surface-low px-4 py-2.5 text-[10px] text-on-surface transition hover:bg-surface-high">
                            Hủy bỏ
                        </button>
                    </div>

                    <button
                        id="inspectorToggleCategoryStatus"
                        type="button"
                        class="hidden w-full items-center justify-center gap-2 border border-surface-high bg-surface-lowest px-4 py-2.5 text-[10px] font-semibold text-error transition hover:bg-surface-lowest">
                        <span class="material-symbols-outlined text-[15px]">block</span>
                        <span id="inspectorToggleCategoryStatusText">
                            Vô hiệu hóa (Deactivate Category)
                        </span>
                    </button>
                </form>
            </div>
        </aside>
    </div>

    <section class="mt-6 border border-surface-high bg-surface-lowest p-5">
        <div class="grid gap-6 lg:grid-cols-[minmax(0,1fr)_260px] lg:items-center">
            <div>
                <p class="flex items-center gap-2 font-mono text-[9px] uppercase tracking-[0.12em] text-primary">
                    <span class="material-symbols-outlined text-[17px]">account_tree</span>
                    Taxonomy Graph Visualization
                </p>

                <h3 class="mt-2 text-base font-semibold text-on-surface">
                    Ontology Linker & Search Weighting
                </h3>

                <p class="mt-1 max-w-3xl text-[11px] leading-5 text-on-surface-variant">
                    Category cha/con tạo thành cây taxonomy để hỗ trợ điều hướng,
                    tổ chức nội dung và mở rộng Semantic Search trong hệ thống.
                </p>
            </div>

            <div class="border border-surface-high bg-surface-lowest p-4">
                <svg viewBox="0 0 220 90" class="h-24 w-full" role="img" aria-label="Taxonomy graph visualization">
                    <line x1="35" y1="45" x2="90" y2="45" stroke="#38bdf8" stroke-width="1.5" />
                    <line x1="90" y1="45" x2="145" y2="20" stroke="#38bdf8" stroke-width="1.5" />
                    <line x1="90" y1="45" x2="145" y2="70" stroke="#38bdf8" stroke-width="1.5" />
                    <line x1="145" y1="20" x2="190" y2="20" stroke="#56e5a9" stroke-width="1.5" />
                    <line x1="145" y1="70" x2="190" y2="70" stroke="#56e5a9" stroke-width="1.5" />

                    <circle cx="35" cy="45" r="8" fill="#111419" stroke="#87929a" stroke-width="2" />
                    <circle cx="90" cy="45" r="11" fill="#102332" stroke="#38bdf8" stroke-width="2" />
                    <circle cx="145" cy="20" r="8" fill="#102332" stroke="#38bdf8" stroke-width="2" />
                    <circle cx="145" cy="70" r="8" fill="#102332" stroke="#38bdf8" stroke-width="2" />
                    <circle cx="190" cy="20" r="6" fill="#10251e" stroke="#56e5a9" stroke-width="2" />
                    <circle cx="190" cy="70" r="6" fill="#10251e" stroke="#56e5a9" stroke-width="2" />

                    <text x="22" y="65" fill="#87929a" font-size="7">ROOT</text>
                    <text x="82" y="65" fill="#80d4ff" font-size="7">L1</text>
                    <text x="137" y="87" fill="#87929a" font-size="7">L2</text>
                </svg>
            </div>
        </div>
    </section>
</div>