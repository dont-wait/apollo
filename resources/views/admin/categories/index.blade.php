@extends('layouts.admin')

@section('title', 'Categories & Tags - Apollo Admin')

@section('content')
<div
    id="taxonomyManager"
    class="-m-8 min-h-full bg-background p-4 text-on-surface sm:p-8"
>
    {{-- HEADER --}}
    <header class="mb-6 flex flex-col justify-between gap-4 xl:flex-row xl:items-end">
        <div>
            <p class="text-[11px] uppercase tracking-[0.16em] text-outline">
                Content // Taxonomy
            </p>

            <h1 class="mt-2 text-3xl font-semibold tracking-tight">
                Categories & Tags Management
            </h1>

            <p class="mt-2 max-w-3xl text-sm text-on-surface-variant">
                Quản lý category phân cấp và taxonomy tags cho nội dung của Apollo Blog.
            </p>
        </div>
    </header>


    {{-- TABS --}}
    <div class="mb-6 flex border-b border-surface-highest">

        <button
            type="button"
            id="categoryTabButton"
            data-tab="categories"
            class="border-b-2 border-primary px-4 py-3 text-sm font-semibold text-primary"
        >
            Hierarchical Categories
        </button>

        <button
            type="button"
            id="tagTabButton"
            data-tab="tags"
            class="px-4 py-3 text-sm text-outline transition hover:text-on-surface"
        >
            Taxonomy Tags
        </button>

    </div>


    {{-- CATEGORY TAB --}}
    <div id="categoriesTab">
        @include('admin.categories.partials.categories-tab')
    </div>


    {{-- TAG TAB --}}
    <div id="tagsTab" class="hidden">
        @include('admin.categories.partials.tags-tab')
    </div>
</div>
<x-confirm-modal />
@endsection
