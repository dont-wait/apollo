@extends('layouts.admin')

@section('title', 'Categories - Apollo Admin')

@section('content')
    <div class="-m-8 min-h-full space-y-6 bg-[#111319] p-4 text-[#e1e2ea] sm:p-8">
        <header class="flex flex-col justify-between gap-4 sm:flex-row sm:items-end">
            <div>
                <p class="text-[11px] uppercase tracking-[0.16em] text-[#87929a]">Content // Taxonomy</p>
                <h1 class="mt-2 text-3xl font-semibold tracking-tight">Categories</h1>
                <p class="mt-2 text-sm text-[#bdc8d1]">Quản lý danh mục và cấu trúc category cha con.</p>
            </div>
            <span class="rounded-lg bg-[#191c21] px-4 py-2 text-sm text-[#bdc8d1]">{{ $categories->count() }} categories</span>
        </header>

        @if ($categories->isEmpty())
            <section class="rounded-xl bg-[#191c21] p-8 text-center">
                <span class="material-symbols-outlined text-3xl text-[#8ed5ff]">label</span>
                <h2 class="mt-3 font-semibold">Chưa có category nào</h2>
                <p class="mt-1 text-sm text-[#bdc8d1]">Danh sách category hiện đang trống.</p>
            </section>
        @else
            <section class="overflow-hidden rounded-xl bg-[#191c21] shadow-sm">
                <div class="overflow-x-auto">
                    <table class="w-full min-w-[680px] text-left text-sm">
                        <thead class="border-b border-[#32353b] bg-[#0b0e13] text-[10px] uppercase tracking-wider text-[#87929a]">
                            <tr>
                                <th class="px-5 py-3 font-medium">Name</th>
                                <th class="px-4 py-3 font-medium">Slug</th>
                                <th class="px-4 py-3 font-medium">Parent</th>
                                <th class="px-4 py-3 text-center font-medium">Children</th>
                                <th class="px-5 py-3 text-right font-medium">Status</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-[#32353b]/70">
                            @foreach ($categories as $category)
                                <tr class="transition-colors hover:bg-[#1d2025]">
                                    <td class="px-5 py-4 font-semibold">{{ $category->name }}</td>
                                    <td class="px-4 py-4 font-mono text-xs text-[#bdc8d1]">{{ $category->slug }}</td>
                                    <td class="px-4 py-4 text-[#bdc8d1]">{{ $category->parent?->name ?? '—' }}</td>
                                    <td class="px-4 py-4 text-center text-[#bdc8d1]">{{ $category->children_count }}</td>
                                    <td class="px-5 py-4 text-right">
                                        <span @class([
                                            'inline-flex items-center gap-1.5 rounded px-2.5 py-1 text-[10px]',
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
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </section>
        @endif
    </div>
@endsection
