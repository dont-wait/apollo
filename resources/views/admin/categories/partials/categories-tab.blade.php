<div
    id="categoryManager"
    data-store-url="{{ route('admin.categories.store') }}"
    data-csrf-token="{{ csrf_token() }}"
>

    {{-- =====================================================
        CATEGORY STATISTICS
    ====================================================== --}}
    <section class="mb-6 grid gap-4 sm:grid-cols-2 xl:grid-cols-4">

        {{-- TOTAL --}}
        <article class="rounded-xl border border-[#252930] bg-[#191c21] p-5">
            <div class="flex items-start justify-between">

                <div>
                    <p class="text-[10px] uppercase tracking-widest text-[#87929a]">
                        Total Categories
                    </p>

                    <p class="mt-3 text-3xl font-semibold">
                        {{ $categories->count() }}
                    </p>
                </div>

                <span class="material-symbols-outlined text-[#80d4ff]">
                    category
                </span>

            </div>
        </article>


        {{-- ACTIVE --}}
        <article class="rounded-xl border border-[#252930] bg-[#191c21] p-5">
            <div class="flex items-start justify-between">

                <div>
                    <p class="text-[10px] uppercase tracking-widest text-[#87929a]">
                        Active
                    </p>

                    <p class="mt-3 text-3xl font-semibold text-[#56e5a9]">
                        {{ $categories->where('status', 'ACTIVE')->count() }}
                    </p>
                </div>

                <span class="material-symbols-outlined text-[#56e5a9]">
                    check_circle
                </span>

            </div>
        </article>


        {{-- ROOT --}}
        <article class="rounded-xl border border-[#252930] bg-[#191c21] p-5">
            <div class="flex items-start justify-between">

                <div>
                    <p class="text-[10px] uppercase tracking-widest text-[#87929a]">
                        Root Categories
                    </p>

                    <p class="mt-3 text-3xl font-semibold">
                        {{ $categories->whereNull('parent_id')->count() }}
                    </p>
                </div>

                <span class="material-symbols-outlined text-[#c5a7ff]">
                    account_tree
                </span>

            </div>
        </article>


        {{-- CHILD --}}
        <article class="rounded-xl border border-[#252930] bg-[#191c21] p-5">
            <div class="flex items-start justify-between">

                <div>
                    <p class="text-[10px] uppercase tracking-widest text-[#87929a]">
                        Child Categories
                    </p>

                    <p class="mt-3 text-3xl font-semibold">
                        {{ $categories->whereNotNull('parent_id')->count() }}
                    </p>
                </div>

                <span class="material-symbols-outlined text-[#ffcf70]">
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
            <div class="flex flex-col gap-3 rounded-xl border border-[#252930] bg-[#191c21] p-4 lg:flex-row">

                <div class="relative flex-1">

                    <span
                        class="material-symbols-outlined absolute left-3 top-1/2 -translate-y-1/2 text-[18px] text-[#87929a]"
                    >
                        search
                    </span>

                    <input
                        type="text"
                        id="categorySearch"
                        placeholder="Tìm category theo tên hoặc slug..."
                        class="w-full rounded-lg border border-[#32353b] bg-[#111319] py-2.5 pl-10 pr-4 text-sm text-[#e1e2ea] outline-none placeholder:text-[#626a72] focus:border-[#80d4ff]"
                    >

                </div>


                <select
                    id="statusFilter"
                    class="rounded-lg border border-[#32353b] bg-[#111319] px-4 py-2.5 text-sm text-[#bdc8d1] outline-none focus:border-[#80d4ff]"
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
            <div class="overflow-hidden rounded-xl border border-[#252930] bg-[#191c21]">

                @if ($categories->isEmpty())

                    {{-- EMPTY STATE --}}
                    <div class="p-12 text-center">

                        <span class="material-symbols-outlined text-4xl text-[#80d4ff]">
                            label
                        </span>

                        <h2 class="mt-3 font-semibold">
                            Chưa có category nào
                        </h2>

                        <p class="mt-1 text-sm text-[#87929a]">
                            Tạo category đầu tiên bằng form bên phải.
                        </p>

                    </div>

                @else

                    <div class="overflow-x-auto">

                        <table class="w-full min-w-[760px] text-left text-sm">

                            {{-- HEADER --}}
                            <thead class="border-b border-[#32353b] bg-[#0b0e13] text-[10px] uppercase tracking-wider text-[#87929a]">

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
                                class="divide-y divide-[#32353b]/70"
                            >

                                @foreach ($categories as $category)

                                    <tr
                                        class="category-row transition-colors hover:bg-[#1d2025]"
                                        data-name="{{ $category->name }}"
                                        data-slug="{{ $category->slug }}"
                                        data-status="{{ $category->status }}"
                                    >

                                        {{-- CATEGORY --}}
                                        <td class="px-5 py-4">

                                            <div class="flex items-center gap-3">

                                                <span class="material-symbols-outlined text-[19px] text-[#80d4ff]">
                                                    label
                                                </span>

                                                <div>

                                                    <p class="font-semibold">
                                                        {{ $category->name }}
                                                    </p>

                                                    <p class="mt-0.5 text-xs text-[#626a72]">
                                                        #{{ $category->id }}
                                                    </p>

                                                </div>

                                            </div>

                                        </td>


                                        {{-- SLUG --}}
                                        <td class="px-4 py-4 font-mono text-xs text-[#bdc8d1]">
                                            /categories/{{ $category->slug }}
                                        </td>


                                        {{-- PARENT --}}
                                        <td class="px-4 py-4 text-[#bdc8d1]">
                                            {{ $category->parent?->name ?? 'Root' }}
                                        </td>


                                        {{-- CHILDREN --}}
                                        <td class="px-4 py-4 text-center text-[#bdc8d1]">
                                            {{ $category->children_count }}
                                        </td>


                                        {{-- STATUS --}}
                                        <td class="px-4 py-4">

                                            <span @class([
                                                'inline-flex items-center gap-1.5 rounded px-2.5 py-1 text-[10px] font-medium',
                                                'bg-[#163a30] text-[#56e5a9]' => $category->status === 'ACTIVE',
                                                'bg-[#32353b] text-[#bdc8d1]' => $category->status !== 'ACTIVE',
                                            ])>

                                                <span @class([
                                                    'h-1.5 w-1.5 rounded-full',
                                                    'bg-[#56e5a9]' => $category->status === 'ACTIVE',
                                                    'bg-[#87929a]' => $category->status !== 'ACTIVE',
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
                                                    class="edit-category rounded-md p-2 text-[#80d4ff] transition hover:bg-[#252930]"
                                                    title="Edit category"

                                                    data-id="{{ $category->id }}"
                                                    data-name="{{ $category->name }}"
                                                    data-slug="{{ $category->slug }}"
                                                    data-parent-id="{{ $category->parent_id }}"
                                                    data-description="{{ $category->description }}"
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
                                                    class="toggle-category-status rounded-md p-2 text-[#87929a] transition hover:bg-[#252930]"
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

            <div class="sticky top-6 rounded-xl border border-[#252930] bg-[#191c21]">

                {{-- HEADER --}}
                <div class="border-b border-[#32353b] px-5 py-4">

                    <p class="text-[10px] uppercase tracking-widest text-[#80d4ff]">
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
                            class="mb-2 block text-xs font-medium text-[#bdc8d1]"
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
                            class="w-full rounded-lg border border-[#32353b] bg-[#111319] px-3 py-2.5 text-sm outline-none placeholder:text-[#626a72] focus:border-[#80d4ff]"
                        >

                    </div>


                    {{-- SLUG --}}
                    <div>

                        <label
                            for="categorySlug"
                            class="mb-2 block text-xs font-medium text-[#bdc8d1]"
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
                            class="w-full rounded-lg border border-[#32353b] bg-[#111319] px-3 py-2.5 font-mono text-sm outline-none placeholder:text-[#626a72] focus:border-[#80d4ff]"
                        >

                    </div>


                    {{-- PARENT --}}
                    <div>

                        <label
                            for="categoryParent"
                            class="mb-2 block text-xs font-medium text-[#bdc8d1]"
                        >
                            Parent Category
                        </label>

                        <select
                            id="categoryParent"
                            name="parent_id"
                            class="w-full rounded-lg border border-[#32353b] bg-[#111319] px-3 py-2.5 text-sm outline-none focus:border-[#80d4ff]"
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
                            class="mb-2 block text-xs font-medium text-[#bdc8d1]"
                        >
                            Description
                        </label>

                        <textarea
                            id="categoryDescription"
                            name="description"
                            rows="4"
                            maxlength="500"
                            placeholder="Mô tả ngắn cho category..."
                            class="w-full resize-none rounded-lg border border-[#32353b] bg-[#111319] px-3 py-2.5 text-sm outline-none placeholder:text-[#626a72] focus:border-[#80d4ff]"
                        ></textarea>

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
                        class="hidden rounded-lg border border-[#6f3030] bg-[#351b1b] px-3 py-2 text-xs text-[#ff9a9a]"
                    ></div>


                    {{-- SAVE --}}
                    <button
                        type="submit"
                        id="saveCategoryButton"
                        class="flex w-full items-center justify-center gap-2 rounded-lg bg-[#80d4ff] px-4 py-2.5 text-sm font-semibold text-[#071017] transition hover:bg-[#a2e0ff]"
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
                        class="hidden w-full rounded-lg border border-[#32353b] px-4 py-2.5 text-sm text-[#bdc8d1] transition hover:bg-[#252930]"
                    >
                        Cancel Edit
                    </button>

                </form>

            </div>

        </aside>

    </div>

</div>