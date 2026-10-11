<div
    id="tagManager"
    data-store-url="{{ route('admin.tags.store') }}"
    data-csrf-token="{{ csrf_token() }}"
>

    {{-- =====================================================
        TAG STATISTICS
    ====================================================== --}}
    <section class="mb-6 grid gap-4 sm:grid-cols-2 xl:grid-cols-4">

        {{-- TOTAL --}}
        <article class="rounded-xl border border-[#252930] bg-[#191c21] p-5">
            <div class="flex items-start justify-between">

                <div>
                    <p class="text-[10px] uppercase tracking-widest text-[#87929a]">
                        Total Tags
                    </p>

                    <p class="mt-3 text-3xl font-semibold">
                        {{ $tags->count() }}
                    </p>
                </div>

                <span class="material-symbols-outlined text-[#c5a7ff]">
                    sell
                </span>

            </div>
        </article>


        {{-- IN USE --}}
        <article class="rounded-xl border border-[#252930] bg-[#191c21] p-5">
            <div class="flex items-start justify-between">

                <div>
                    <p class="text-[10px] uppercase tracking-widest text-[#87929a]">
                        Tags In Use
                    </p>

                    <p class="mt-3 text-3xl font-semibold text-[#56e5a9]">
                        {{ $tags->where('posts_count', '>', 0)->count() }}
                    </p>
                </div>

                <span class="material-symbols-outlined text-[#56e5a9]">
                    link
                </span>

            </div>
        </article>


        {{-- UNUSED --}}
        <article class="rounded-xl border border-[#252930] bg-[#191c21] p-5">
            <div class="flex items-start justify-between">

                <div>
                    <p class="text-[10px] uppercase tracking-widest text-[#87929a]">
                        Unused Tags
                    </p>

                    <p class="mt-3 text-3xl font-semibold text-[#ffcf70]">
                        {{ $tags->where('posts_count', 0)->count() }}
                    </p>
                </div>

                <span class="material-symbols-outlined text-[#ffcf70]">
                    link_off
                </span>

            </div>
        </article>


        {{-- TOTAL REFERENCES --}}
        <article class="rounded-xl border border-[#252930] bg-[#191c21] p-5">
            <div class="flex items-start justify-between">

                <div>
                    <p class="text-[10px] uppercase tracking-widest text-[#87929a]">
                        Post References
                    </p>

                    <p class="mt-3 text-3xl font-semibold">
                        {{ $tags->sum('posts_count') }}
                    </p>
                </div>

                <span class="material-symbols-outlined text-[#80d4ff]">
                    article
                </span>

            </div>
        </article>

    </section>


    {{-- =====================================================
        TAG LIST + EDITOR
    ====================================================== --}}
    <div class="grid gap-6 lg:grid-cols-[minmax(0,1fr)_360px]">

        {{-- LEFT --}}
        <section class="min-w-0 space-y-4">

            {{-- SEARCH --}}
            <div class="rounded-xl border border-[#252930] bg-[#191c21] p-4">

                <div class="relative">

                    <span
                        class="material-symbols-outlined absolute left-3 top-1/2 -translate-y-1/2 text-[18px] text-[#87929a]"
                    >
                        search
                    </span>

                    <input
                        type="text"
                        id="tagSearch"
                        placeholder="Tìm tag theo tên hoặc slug..."
                        class="w-full rounded-lg border border-[#32353b] bg-[#111319] py-2.5 pl-10 pr-4 text-sm text-[#e1e2ea] outline-none placeholder:text-[#626a72] focus:border-[#80d4ff]"
                    >

                </div>

            </div>


            {{-- TAG TABLE --}}
            <div class="overflow-hidden rounded-xl border border-[#252930] bg-[#191c21]">

                @if ($tags->isEmpty())

                    {{-- EMPTY --}}
                    <div class="p-12 text-center">

                        <span class="material-symbols-outlined text-4xl text-[#c5a7ff]">
                            sell
                        </span>

                        <h2 class="mt-3 font-semibold">
                            Chưa có tag nào
                        </h2>

                        <p class="mt-1 text-sm text-[#87929a]">
                            Tạo tag đầu tiên bằng form bên phải.
                        </p>

                    </div>

                @else

                    <div class="overflow-x-auto">

                        <table class="w-full min-w-[620px] text-left text-sm">

                            {{-- HEADER --}}
                            <thead class="border-b border-[#32353b] bg-[#0b0e13] text-[10px] uppercase tracking-wider text-[#87929a]">

                                <tr>

                                    <th class="px-5 py-3 font-medium">
                                        Tag
                                    </th>

                                    <th class="px-4 py-3 font-medium">
                                        Slug
                                    </th>

                                    <th class="px-4 py-3 text-center font-medium">
                                        Posts
                                    </th>

                                    <th class="px-5 py-3 text-right font-medium">
                                        Actions
                                    </th>

                                </tr>

                            </thead>


                            {{-- BODY --}}
                            <tbody
                                id="tagTable"
                                class="divide-y divide-[#32353b]/70"
                            >

                                @foreach ($tags as $tag)

                                    <tr
                                        class="tag-row transition-colors hover:bg-[#1d2025]"
                                        data-name="{{ $tag->name }}"
                                        data-slug="{{ $tag->slug }}"
                                    >

                                        {{-- NAME --}}
                                        <td class="px-5 py-4">

                                            <div class="flex items-center gap-3">

                                                <span class="material-symbols-outlined text-[19px] text-[#c5a7ff]">
                                                    sell
                                                </span>

                                                <div>

                                                    <p class="font-semibold">
                                                        {{ $tag->name }}
                                                    </p>

                                                    <p class="mt-0.5 text-xs text-[#626a72]">
                                                        #{{ $tag->id }}
                                                    </p>

                                                </div>

                                            </div>

                                        </td>


                                        {{-- SLUG --}}
                                        <td class="px-4 py-4 font-mono text-xs text-[#bdc8d1]">
                                            #{{ $tag->slug }}
                                        </td>


                                        {{-- POST COUNT --}}
                                        <td class="px-4 py-4 text-center">

                                            @if ($tag->posts_count > 0)

                                                <span class="inline-flex items-center gap-1.5 rounded bg-[#163a30] px-2.5 py-1 text-[10px] font-medium text-[#56e5a9]">

                                                    <span class="h-1.5 w-1.5 rounded-full bg-[#56e5a9]"></span>

                                                    {{ $tag->posts_count }}
                                                    {{ $tag->posts_count === 1 ? 'post' : 'posts' }}

                                                </span>

                                            @else

                                                <span class="inline-flex items-center rounded bg-[#32353b] px-2.5 py-1 text-[10px] text-[#bdc8d1]">
                                                    unused
                                                </span>

                                            @endif

                                        </td>


                                        {{-- ACTIONS --}}
                                        <td class="px-5 py-4">

                                            <div class="flex justify-end gap-2">

                                                {{-- EDIT --}}
                                                <button
                                                    type="button"
                                                    class="edit-tag rounded-md p-2 text-[#80d4ff] transition hover:bg-[#252930]"
                                                    title="Edit tag"

                                                    data-name="{{ $tag->name }}"
                                                    data-slug="{{ $tag->slug }}"
                                                    data-update-url="{{ route('admin.tags.update', $tag) }}"
                                                >
                                                    <span class="material-symbols-outlined text-[18px]">
                                                        edit
                                                    </span>
                                                </button>


                                                {{-- DELETE --}}
                                                <button
                                                    type="button"
                                                    class="delete-tag rounded-md p-2 transition hover:bg-[#252930]
                                                        {{ $tag->posts_count > 0
                                                            ? 'text-[#626a72]'
                                                            : 'text-[#ff7d7d]' }}"
                                                    title="{{ $tag->posts_count > 0
                                                        ? 'Tag đang được sử dụng'
                                                        : 'Delete tag' }}"

                                                    data-name="{{ $tag->name }}"
                                                    data-posts-count="{{ $tag->posts_count }}"
                                                    data-delete-url="{{ route('admin.tags.destroy', $tag) }}"
                                                >
                                                    <span class="material-symbols-outlined text-[18px]">
                                                        delete
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
            TAG EDITOR
        ================================================== --}}
        <aside>

            <div class="sticky top-6 rounded-xl border border-[#252930] bg-[#191c21]">

                {{-- HEADER --}}
                <div class="border-b border-[#32353b] px-5 py-4">

                    <p class="text-[10px] uppercase tracking-widest text-[#c5a7ff]">
                        Tag Editor
                    </p>

                    <h2
                        id="tagEditorTitle"
                        class="mt-1 text-lg font-semibold"
                    >
                        New Tag
                    </h2>

                </div>


                {{-- FORM --}}
                <form
                    id="tagForm"
                    class="space-y-5 p-5"
                >

                    @csrf


                    {{-- NAME --}}
                    <div>

                        <label
                            for="tagName"
                            class="mb-2 block text-xs font-medium text-[#bdc8d1]"
                        >
                            Name
                        </label>

                        <input
                            id="tagName"
                            name="name"
                            type="text"
                            maxlength="60"
                            required
                            placeholder="VD: Laravel"
                            class="w-full rounded-lg border border-[#32353b] bg-[#111319] px-3 py-2.5 text-sm outline-none placeholder:text-[#626a72] focus:border-[#80d4ff]"
                        >

                    </div>


                    {{-- SLUG --}}
                    <div>

                        <label
                            for="tagSlug"
                            class="mb-2 block text-xs font-medium text-[#bdc8d1]"
                        >
                            Slug
                        </label>

                        <input
                            id="tagSlug"
                            name="slug"
                            type="text"
                            maxlength="80"
                            required
                            placeholder="laravel"
                            class="w-full rounded-lg border border-[#32353b] bg-[#111319] px-3 py-2.5 font-mono text-sm outline-none placeholder:text-[#626a72] focus:border-[#80d4ff]"
                        >

                    </div>


                    {{-- HELP --}}
                    <div class="rounded-lg border border-[#252930] bg-[#111319] p-3">

                        <div class="flex gap-2">

                            <span class="material-symbols-outlined text-[17px] text-[#80d4ff]">
                                info
                            </span>

                            <p class="text-xs leading-5 text-[#87929a]">
                                Nếu cả name và slug trùng với một tag đã tồn tại,
                                hệ thống sẽ sử dụng lại tag đó thay vì tạo bản ghi mới.
                            </p>

                        </div>

                    </div>


                    {{-- ERROR --}}
                    <div
                        id="tagFormError"
                        class="hidden rounded-lg border border-[#6f3030] bg-[#351b1b] px-3 py-2 text-xs text-[#ff9a9a]"
                    ></div>


                    {{-- INFO --}}
                    <div
                        id="tagFormInfo"
                        class="hidden rounded-lg border border-[#245441] bg-[#153126] px-3 py-2 text-xs text-[#56e5a9]"
                    ></div>


                    {{-- SAVE --}}
                    <button
                        type="submit"
                        id="saveTagButton"
                        class="flex w-full items-center justify-center gap-2 rounded-lg bg-[#80d4ff] px-4 py-2.5 text-sm font-semibold text-[#071017] transition hover:bg-[#a2e0ff]"
                    >

                        <span class="material-symbols-outlined text-[18px]">
                            save
                        </span>

                        <span id="saveTagButtonText">
                            Create Tag
                        </span>

                    </button>


                    {{-- CANCEL --}}
                    <button
                        type="button"
                        id="cancelTagEdit"
                        class="hidden w-full rounded-lg border border-[#32353b] px-4 py-2.5 text-sm text-[#bdc8d1] transition hover:bg-[#252930]"
                    >
                        Cancel Edit
                    </button>

                </form>

            </div>

        </aside>

    </div>

</div>