@extends('layouts.app')

@section('title', 'Apollo Blog')

@section('content')
    <div class="flex min-h-[60vh] items-center justify-center">
        <section class="w-full max-w-2xl rounded-2xl border border-outline-variant bg-surface-low px-6 py-12 text-center shadow-2xl shadow-black/10 sm:px-10">
            <div class="mx-auto flex max-w-lg flex-col items-center gap-5">
                <div class="flex items-center gap-2 font-mono text-xs uppercase tracking-[0.18em] text-primary">
                    <span class="size-1.5 rounded-full bg-tertiary"></span>
                    <span>Apollo Blog</span>
                </div>
                <h1 class="font-display text-3xl font-semibold tracking-tight text-on-surface sm:text-4xl">Welcome to Apollo Blog</h1>
                <p class="text-sm leading-6 text-on-surface-variant">The blog homepage is ready. Articles, comments, and likes will be added here later.</p>
            </div>
        </section>
    </div>
@endsection
