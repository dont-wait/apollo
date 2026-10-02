@php
    $posts = [
        [
            'id' => 1,
            'category' => 'Systems & CUDA',
            'label' => 'vLLM',
            'title' => 'Optimizing Inference on Consumer Hardware: From FP16 to Int4 with vLLM',
            'excerpt' => 'A practical breakdown of KV-cache memory, quantized weights, and the small kernel changes that make local inference feel production-ready.',
            'author' => 'Minh Tran',
            'date' => '2 days ago',
            'readMinutes' => 12,
            'views' => '3.4k',
            'bookmarks' => 482,
            'publishedOrder' => 4,
            'tags' => ['vLLM', 'CUDA'],
            'image' => 'https://lh3.googleusercontent.com/aida-public/AB6AXuBKB9VNn8hkhWOfow4c5Ox9IIKNCohc_XLLHhpYJZGXfk_ngQB57yC1WAfse9Psr0zAM6GIoLDZoJ8mBj2fFdKhf5jpmuK0146gHbHEcQEKFbpLAl3dZPxra8m4aEulUPxiRE3DDHTbKLcqDeMZuO1CdctQgOBC6CaYzmdWjG3bOjgVt0i0UpfU0dWRnuVN4NdpQnJvLsRKXVU4BwPuI3VREXp-W7A8z2BFIoQSRrOPl2qVVn2C1ue_',
        ],
        [
            'id' => 2,
            'category' => 'LLM Architecture',
            'label' => 'RAG',
            'title' => 'Building Deterministic RAG Pipelines with Hybrid Vector-BM25 Search',
            'excerpt' => 'Why pure dense retrieval fails in real codebases, and how reciprocal rank fusion makes retrieval easier to debug and trust.',
            'author' => 'Minh Tran',
            'date' => 'Oct 24, 2025',
            'readMinutes' => 8,
            'views' => '5.1k',
            'bookmarks' => 891,
            'publishedOrder' => 3,
            'tags' => ['RAG', 'Search'],
            'image' => 'https://lh3.googleusercontent.com/aida-public/AB6AXuAC5_26NETqOYiBlkzkLRmgFJd0xi5bVFi_YGELPAoyt-3Eb5P5hMuw7xuZ5Br8lP8k_Mfh9YxaTza18Wbo9pvKqWuHFC9k7mlDcLVNqieCk0e9nKAirb4tEJTpaFnWjc9ggbbCOdGl85GRqhlZ4NnU57WWc5asVBCk6ccfsT0bSw-RKotWgcAXszoyS23SN2iASkKebuGs8S2Xi7aSQ5iNtAj7IzhlyHSvJB55vKrCznDFgkJ0tvrE',
        ],
        [
            'id' => 3,
            'category' => 'Autonomous Agents',
            'label' => 'Evals',
            'title' => 'Self-hosted LLM Evaluation: Metrics that Actually Matter in Production',
            'excerpt' => 'Moving past synthetic benchmarks with deterministic assertion suites, task drift estimators, and semantic safety checks.',
            'author' => 'Minh Tran',
            'date' => 'Oct 19, 2025',
            'readMinutes' => 15,
            'views' => '2.8k',
            'bookmarks' => 610,
            'publishedOrder' => 2,
            'tags' => ['Evals', 'Agents'],
            'image' => 'https://lh3.googleusercontent.com/aida-public/AB6AXuCaVY8Bmi8YMKIbGXedvHCIfbjc-zQFjWyGMudGyuJA1Zyb5fxvf9iPxRf1YuCevkKMe_leQN1SMaALMiSimcuQtRphDYTzngme77YZ_82t3gVr7KEcSGKHkavLNxDvA5O3EIpEZr4ox-hE7MS1zaiVvRDceo1SexK8Sb9316SQFMtwgrmIVNI3vhWynKrRJMN_JQBcTckMUTTpTxCYR9MVCvRXLgK8vRw_fFjt20AJcvIZHldCB0jQ',
        ],
        [
            'id' => 4,
            'category' => 'Tutorials',
            'label' => 'RoPE Math',
            'title' => 'Understanding Rotary Position Embeddings Through Math and Python',
            'excerpt' => 'A visual explanation of rotary position embeddings, frequency scaling, and the long-context tricks behind modern LLMs.',
            'author' => 'Minh Tran',
            'date' => 'Oct 14, 2025',
            'readMinutes' => 10,
            'views' => '6.9k',
            'bookmarks' => 1400,
            'publishedOrder' => 1,
            'tags' => ['Transformers', 'Python'],
            'image' => 'https://lh3.googleusercontent.com/aida-public/AB6AXuBQNUlcsxSRg5dRrhYXC_g3ePOR79PC9ClKbkDBqgnukCNhLjxSz3VDRXAgr6wHJzWC9HJelYvoY6Cn_0-v-LQHw5NCzZvxEcmqaN8pOenLlsk4WBihFJpvtHycnEhgBaIpwginlD6yT4Csq5fBSdoEmy19SnOMSgxa39JiQVHytoLUuJsVD8PUNxlGGietoHFAw5f1JMlVqCc4wZ0_5b5UORrpragv0KKQWZGNfSjRmonlZGBg-lj_',
        ],
    ];

    $categories = ['All Posts', 'LLM Architecture', 'Systems & CUDA', 'Autonomous Agents', 'Tutorials'];
@endphp

@extends('layouts.app')

@section('title', 'NeuralLog · AI & Systems Engineering')

@section('content')
    <div
        class="relative -mx-4 -my-10 overflow-hidden sm:-mx-6 sm:-my-14 lg:-mx-8"
        x-data="homeFeed(@js($posts), @js(auth()->check()), @js(route('login')) )"
        @keydown.window="if (($event.metaKey || $event.ctrlKey) && $event.key.toLowerCase() === 'k') { $event.preventDefault(); focusSearch(); }"
    >
        <div class="pointer-events-none absolute left-1/2 top-0 h-80 w-[42rem] -translate-x-1/2 rounded-full bg-primary-container/10 blur-[120px]" aria-hidden="true"></div>

        <section class="relative mx-auto max-w-7xl px-4 pb-10 pt-16 sm:px-6 sm:pb-14 sm:pt-20 lg:px-8">
            <div class="max-w-4xl">
                <div class="inline-flex items-center gap-2 rounded bg-surface-high px-2.5 py-1 font-mono text-[10px] uppercase tracking-[0.16em] text-primary">
                    <span class="size-1.5 animate-pulse rounded-full bg-primary"></span>
                    <span>Technical notes</span>
                    <span class="text-outline-variant">/</span>
                    <span class="text-on-surface-variant">Human-reviewed AI research</span>
                </div>

                <h1 class="mt-5 max-w-4xl font-display text-4xl font-bold leading-[1.12] tracking-[-0.035em] text-on-surface sm:text-6xl">
                    Engineering notes for the systems behind AI.
                </h1>

                <p class="mt-5 max-w-2xl text-base leading-7 text-on-surface-variant sm:text-lg">
                    Practical deep dives into models, distributed systems, hardware-aware inference, and the tools that make intelligent software reliable.
                </p>

                <div class="mt-7 flex w-full max-w-2xl items-center gap-3 rounded-lg border border-outline-variant/50 bg-surface-low p-1.5 shadow-lg shadow-black/10">
                    <label class="sr-only" for="home-search">Search articles</label>
                    <span class="material-symbols-outlined pl-2 text-[20px] text-primary" aria-hidden="true">search</span>
                    <input
                        id="home-search"
                        x-ref="search"
                        x-model="search"
                        class="min-w-0 flex-1 bg-transparent px-1 py-2 text-sm text-on-surface outline-none placeholder:text-outline"
                        placeholder="Search kernels, papers, or system notes..."
                        type="search"
                    >
                    <kbd class="hidden rounded bg-surface-high px-2 py-1 font-mono text-[10px] text-outline sm:block">⌘ K</kbd>
                </div>

                <div class="mt-4 flex flex-wrap items-center gap-2">
                    <span class="font-mono text-[10px] uppercase tracking-[0.14em] text-outline">Active topics:</span>
                    <template x-for="topic in ['LLM Architecture', 'Systems & CUDA', 'Autonomous Agents', 'Tutorials']" :key="topic">
                        <button
                            class="rounded bg-surface-low px-2.5 py-1 font-mono text-[10px] text-on-surface-variant transition-colors hover:bg-surface-high hover:text-primary"
                            type="button"
                            @click="activeCategory = topic; document.getElementById('articles').scrollIntoView({ behavior: 'smooth' })"
                            x-text="`#${topic.replaceAll(' ', '')}`"
                        ></button>
                    </template>
                </div>
            </div>
        </section>

        <section id="weekly" class="relative mx-auto max-w-7xl px-4 pb-8 sm:px-6 lg:px-8">
            <div class="relative overflow-hidden rounded-xl border border-outline-variant/40 bg-surface-low p-5 shadow-xl shadow-black/15 sm:p-7">
                <div class="pointer-events-none absolute -right-24 -top-24 size-80 rounded-full bg-tertiary-container/10 blur-[90px]" aria-hidden="true"></div>
                <div class="relative z-10 grid items-center gap-8 lg:grid-cols-12">
                    <div class="lg:col-span-8">
                        <div class="flex flex-wrap items-center gap-2 font-mono text-[10px] uppercase tracking-[0.13em]">
                            <span class="inline-flex items-center gap-1.5 rounded bg-tertiary/10 px-2.5 py-1 text-tertiary">
                                <span class="size-1.5 rounded-full bg-tertiary"></span>
                                AI-assisted · human reviewed
                            </span>
                            <span class="text-outline">Dispatch #01</span>
                        </div>

                        <h2 class="mt-4 max-w-3xl font-display text-2xl font-semibold leading-tight tracking-[-0.025em] text-on-surface sm:text-3xl">
                            AI Weekly: What changed in models, agents, and inference this week
                        </h2>

                        <div class="mt-5 grid gap-3 text-sm leading-6 text-on-surface-variant sm:grid-cols-3">
                            <p><span class="text-primary">01</span> Model releases translated into engineering trade-offs.</p>
                            <p><span class="text-tertiary">02</span> Production patterns that survived contact with real systems.</p>
                            <p><span class="text-secondary">03</span> Sources and experiments worth following next.</p>
                        </div>

                        <div class="mt-6 flex flex-wrap items-center gap-3">
                            <a class="inline-flex items-center gap-2 rounded bg-primary px-4 py-2.5 font-display text-sm font-semibold text-on-primary transition-colors hover:bg-surface-tint" href="#articles">
                                Read the dispatch
                                <span class="material-symbols-outlined text-[18px]" aria-hidden="true">arrow_forward</span>
                            </a>
                            <span class="font-mono text-[10px] uppercase tracking-[0.12em] text-outline">12 sources · 7 min read</span>
                        </div>
                    </div>

                    <div class="rounded-lg border border-outline-variant/40 bg-surface p-4 lg:col-span-4">
                        <div class="flex items-center justify-between font-mono text-[10px] uppercase tracking-[0.12em]">
                            <span class="text-primary">Signal map</span>
                            <span class="text-tertiary">Human verified</span>
                        </div>
                        <svg class="mt-5 h-28 w-full" fill="none" viewBox="0 0 240 100" xmlns="http://www.w3.org/2000/svg" aria-label="Editorial signal map">
                            <path d="M10 78H230M10 50H230M10 22H230" stroke="#32353b" stroke-dasharray="2 2"></path>
                            <path d="M12 76C44 28 67 68 101 53C131 40 140 12 163 28C181 40 201 10 228 22V88H12V76Z" fill="url(#signal-fill)"></path>
                            <path d="M12 76C44 28 67 68 101 53C131 40 140 12 163 28C181 40 201 10 228 22" stroke="#38bdf8" stroke-width="2"></path>
                            <path d="M12 83C55 74 86 78 120 66C158 52 183 62 228 36" stroke="#30c88f" stroke-dasharray="3 3" stroke-width="1.5"></path>
                            <circle cx="163" cy="28" r="3" fill="#8ed5ff"></circle>
                            <circle cx="228" cy="22" r="3" fill="#56e5a9"></circle>
                            <defs>
                                <linearGradient id="signal-fill" x1="0" x2="0" y1="0" y2="1">
                                    <stop offset="0" stop-color="#38bdf8" stop-opacity=".3"></stop>
                                    <stop offset="1" stop-color="#38bdf8" stop-opacity="0"></stop>
                                </linearGradient>
                            </defs>
                        </svg>
                        <div class="mt-3 grid grid-cols-2 gap-2 font-mono text-[10px] uppercase tracking-[0.08em]">
                            <div class="rounded bg-surface-high p-2">
                                <span class="block text-outline">Sources read</span>
                                <strong class="mt-1 block text-base normal-case tracking-normal text-on-surface">12</strong>
                            </div>
                            <div class="rounded bg-surface-high p-2">
                                <span class="block text-outline">Review status</span>
                                <strong class="mt-1 block text-base normal-case tracking-normal text-tertiary">Ready</strong>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </section>

        <section id="categories" class="mx-auto max-w-7xl px-4 pb-3 pt-4 sm:px-6 lg:px-8">
            <div class="flex flex-col gap-3 rounded-lg border border-outline-variant/25 bg-surface-lowest p-2 lg:flex-row lg:items-center lg:justify-between">
                <div class="flex items-center gap-1.5 overflow-x-auto py-1" role="tablist" aria-label="Article categories">
                    @foreach ($categories as $category)
                        <button
                            class="shrink-0 rounded px-3.5 py-1.5 font-mono text-[10px] uppercase tracking-[0.08em] transition-colors"
                            :class="activeCategory === @js($category) ? 'bg-surface-high text-primary' : 'text-on-surface-variant hover:bg-surface hover:text-on-surface'"
                            type="button"
                            role="tab"
                            :aria-selected="activeCategory === @js($category)"
                            @click="activeCategory = @js($category)"
                        >
                            {{ $category }}
                        </button>
                    @endforeach
                </div>

                <div class="flex shrink-0 items-center gap-2">
                    <label class="sr-only" for="sort-posts">Sort articles</label>
                    <div class="flex items-center gap-1.5 rounded bg-surface px-2.5 py-1.5">
                        <span class="material-symbols-outlined text-[16px] text-outline" aria-hidden="true">sort</span>
                        <select id="sort-posts" x-model="sort" class="bg-transparent font-mono text-[10px] uppercase tracking-[0.08em] text-on-surface outline-none">
                            <option value="latest">Latest</option>
                            <option value="popular">Most bookmarked</option>
                            <option value="reading">Longest reads</option>
                        </select>
                    </div>
                    <div class="flex items-center rounded bg-surface p-0.5" aria-label="View mode">
                        <button class="rounded p-1.5 transition-colors" :class="view === 'list' ? 'bg-surface-high text-primary' : 'text-outline hover:text-on-surface'" type="button" aria-label="List view" :aria-pressed="view === 'list'" @click="view = 'list'">
                            <span class="material-symbols-outlined text-[18px]" aria-hidden="true">view_agenda</span>
                        </button>
                        <button class="rounded p-1.5 transition-colors" :class="view === 'grid' ? 'bg-surface-high text-primary' : 'text-outline hover:text-on-surface'" type="button" aria-label="Grid view" :aria-pressed="view === 'grid'" @click="view = 'grid'">
                            <span class="material-symbols-outlined text-[18px]" aria-hidden="true">grid_view</span>
                        </button>
                    </div>
                </div>
            </div>
        </section>

        <section id="articles" class="mx-auto max-w-7xl px-4 py-5 sm:px-6 sm:py-7 lg:px-8">
            <div class="grid items-start gap-5 lg:grid-cols-12 lg:gap-7">
                <div class="min-w-0 lg:col-span-8">
                    <div class="mb-4 flex items-end justify-between gap-3">
                        <div>
                            <p class="font-mono text-[10px] uppercase tracking-[0.16em] text-primary">The archive</p>
                            <h2 class="mt-1 font-display text-2xl font-semibold tracking-tight text-on-surface">Recent dispatches</h2>
                        </div>
                        <span class="hidden font-mono text-[10px] uppercase tracking-[0.12em] text-outline sm:inline">{{ count($posts) }} sample posts</span>
                    </div>

                    <div class="grid gap-4" :class="view === 'grid' ? 'md:grid-cols-2' : ''">
                        <template x-for="post in visiblePosts()" :key="post.id">
                            <article
                                class="group min-w-0 rounded-lg border border-outline-variant/30 bg-surface-low p-3.5 shadow-md transition-all hover:-translate-y-px hover:border-primary/30 hover:bg-surface"
                                :class="view === 'grid' ? 'flex flex-col' : 'flex flex-col sm:flex-row'"
                            >
                                <div class="relative shrink-0 overflow-hidden rounded bg-surface-lowest" :class="view === 'grid' ? 'h-44 w-full' : 'h-40 w-full sm:h-36 sm:w-48'">
                                    <img class="h-full w-full object-cover transition-transform duration-300 group-hover:scale-105" :src="post.image" :alt="post.title" loading="lazy">
                                    <span class="absolute left-2 top-2 rounded bg-surface-lowest/90 px-2 py-0.5 font-mono text-[10px] uppercase tracking-[0.08em] text-primary backdrop-blur" x-text="post.label"></span>
                                </div>

                                <div class="flex min-w-0 flex-1 flex-col justify-between gap-4 pt-1 sm:pl-4 sm:pt-0">
                                    <div>
                                        <div class="flex flex-wrap items-center gap-2 font-mono text-[10px] uppercase tracking-[0.1em]">
                                            <span class="inline-flex items-center gap-1.5 rounded bg-tertiary/10 px-2 py-0.5 text-tertiary">
                                                <span class="size-1.5 rounded-full bg-tertiary"></span>
                                                Published
                                            </span>
                                            <span class="text-outline-variant" x-text="post.category"></span>
                                            <span class="text-outline-variant">·</span>
                                            <span class="text-outline" x-text="`${post.readMinutes} min read`"></span>
                                        </div>
                                        <h3 class="mt-2 font-display text-lg font-semibold leading-tight tracking-tight text-on-surface transition-colors group-hover:text-primary">
                                            <a href="#articles" x-text="post.title"></a>
                                        </h3>
                                        <p class="mt-2 line-clamp-2 text-sm leading-6 text-on-surface-variant" x-text="post.excerpt"></p>
                                    </div>

                                    <div class="flex flex-wrap items-center justify-between gap-3 border-t border-outline-variant/25 pt-3">
                                        <div class="flex min-w-0 items-center gap-2 font-mono text-[10px] text-on-surface-variant">
                                            <span class="size-5 rounded-full bg-primary/15 text-center leading-5 text-primary" x-text="post.author.charAt(0)"></span>
                                            <span x-text="post.author"></span>
                                            <span class="text-outline" x-text="post.date"></span>
                                        </div>
                                        <div class="flex items-center gap-3 font-mono text-[10px] text-outline">
                                            <span class="inline-flex items-center gap-1">
                                                <span class="material-symbols-outlined text-[15px]" aria-hidden="true">visibility</span>
                                                <span x-text="post.views"></span>
                                            </span>
                                            <button
                                                class="inline-flex items-center gap-1 rounded px-1 py-0.5 transition-colors hover:text-primary"
                                                :class="isBookmarked(post.id) ? 'text-primary' : 'text-outline'"
                                                type="button"
                                                :aria-label="isBookmarked(post.id) ? 'Remove bookmark' : 'Save article'"
                                                :aria-pressed="isBookmarked(post.id)"
                                                @click="toggleBookmark(post.id)"
                                            >
                                                <span class="material-symbols-outlined text-[16px]" aria-hidden="true">bookmark</span>
                                                <span x-text="bookmarkCount(post)"></span>
                                            </button>
                                        </div>
                                    </div>
                                </div>
                            </article>
                        </template>
                    </div>

                    <div x-cloak x-show="visiblePosts().length === 0" class="rounded-lg border border-dashed border-outline-variant/50 bg-surface-low p-10 text-center">
                        <span class="material-symbols-outlined text-3xl text-outline" aria-hidden="true">search_off</span>
                        <h3 class="mt-3 font-display text-lg font-semibold text-on-surface">No dispatches found</h3>
                        <p class="mt-1 text-sm text-on-surface-variant">Try another search term or reset the category filter.</p>
                        <button class="mt-4 rounded bg-primary px-3 py-2 font-mono text-xs uppercase tracking-[0.1em] text-on-primary" type="button" @click="search = ''; activeCategory = 'All Posts'">Reset filters</button>
                    </div>

                    <div class="mt-5 flex items-center justify-between border-t border-outline-variant/25 pt-5">
                        <button class="inline-flex items-center gap-1.5 rounded bg-surface-low px-3.5 py-2 font-mono text-[10px] uppercase tracking-[0.1em] text-on-surface-variant transition-colors hover:bg-surface-high hover:text-on-surface" type="button" disabled>
                            <span class="material-symbols-outlined text-[16px]" aria-hidden="true">arrow_back</span>
                            Previous
                        </button>
                        <div class="hidden items-center gap-1 sm:flex" aria-label="Pagination">
                            <span class="flex size-8 items-center justify-center rounded bg-primary font-mono text-xs font-semibold text-on-primary">1</span>
                            <button class="flex size-8 items-center justify-center rounded bg-surface-low font-mono text-xs text-on-surface-variant transition-colors hover:bg-surface-high" type="button">2</button>
                            <button class="flex size-8 items-center justify-center rounded bg-surface-low font-mono text-xs text-on-surface-variant transition-colors hover:bg-surface-high" type="button">3</button>
                            <span class="px-1 font-mono text-xs text-outline">...</span>
                            <button class="flex size-8 items-center justify-center rounded bg-surface-low font-mono text-xs text-on-surface-variant transition-colors hover:bg-surface-high" type="button">18</button>
                        </div>
                        <button class="inline-flex items-center gap-1.5 rounded bg-surface-low px-3.5 py-2 font-mono text-[10px] uppercase tracking-[0.1em] text-on-surface-variant transition-colors hover:bg-surface-high hover:text-on-surface" type="button">
                            Next
                            <span class="material-symbols-outlined text-[16px]" aria-hidden="true">arrow_forward</span>
                        </button>
                    </div>
                </div>

                <aside class="flex flex-col gap-5 lg:col-span-4">
                    <section id="about" class="rounded-lg border border-outline-variant/30 bg-surface-low p-5 shadow-md">
                        <div class="flex items-start justify-between gap-4">
                            <div>
                                <p class="font-mono text-[10px] uppercase tracking-[0.15em] text-primary">About the author</p>
                                <h2 class="mt-2 font-display text-xl font-semibold text-on-surface">A small lab for big systems.</h2>
                            </div>
                            <span class="flex size-9 items-center justify-center rounded bg-primary/10 font-display font-bold text-primary">A</span>
                        </div>
                        <p class="mt-3 text-sm leading-6 text-on-surface-variant">Notes from building with AI systems, reading research, and turning complicated infrastructure into understandable engineering decisions.</p>
                        <div class="mt-4 flex flex-wrap gap-2">
                            <span class="rounded bg-surface-high px-2 py-1 font-mono text-[10px] text-on-surface-variant">AI systems</span>
                            <span class="rounded bg-surface-high px-2 py-1 font-mono text-[10px] text-on-surface-variant">Distributed infra</span>
                            <span class="rounded bg-surface-high px-2 py-1 font-mono text-[10px] text-on-surface-variant">Open source</span>
                        </div>
                    </section>

                    <section class="relative overflow-hidden rounded-lg border border-outline-variant/30 bg-surface-low p-5 shadow-md">
                        <div class="pointer-events-none absolute -right-16 -top-16 size-40 rounded-full bg-primary/10 blur-3xl" aria-hidden="true"></div>
                        <span class="material-symbols-outlined text-2xl text-primary" aria-hidden="true">mark_email_unread</span>
                        <h2 class="mt-3 font-display text-xl font-semibold text-on-surface">The Monday AI Wire</h2>
                        <p class="mt-2 text-sm leading-6 text-on-surface-variant">One thoughtful dispatch each week: new systems, useful papers, and the engineering details worth remembering.</p>
                        <form class="mt-4 flex flex-col gap-2" @submit.prevent="newsletterSubmitted = true">
                            <label class="sr-only" for="newsletter-email">Email address</label>
                            <input id="newsletter-email" class="h-10 rounded border border-outline-variant/50 bg-surface px-3 text-sm text-on-surface outline-none placeholder:text-outline focus:border-primary focus:ring-1 focus:ring-primary" placeholder="you@example.com" type="email" required>
                            <button class="h-10 rounded bg-primary font-display text-sm font-semibold text-on-primary transition-colors hover:bg-surface-tint" type="submit">Join the dispatch</button>
                        </form>
                        <p x-cloak x-show="newsletterSubmitted" class="mt-3 font-mono text-[10px] uppercase tracking-[0.08em] text-tertiary" role="status">You are on the preview list.</p>
                        <p class="mt-3 flex items-center gap-1.5 font-mono text-[10px] text-outline"><span class="material-symbols-outlined text-[14px] text-tertiary" aria-hidden="true">lock</span> No spam. One click unsubscribe.</p>
                    </section>

                    <section class="rounded-lg border border-outline-variant/30 bg-surface-low p-5 shadow-md">
                        <div class="flex items-center justify-between">
                            <span class="font-mono text-[10px] uppercase tracking-[0.15em] text-on-surface">Systems index</span>
                            <span class="font-mono text-[10px] text-outline">04 domains</span>
                        </div>
                        <div class="mt-3 flex flex-col gap-1.5">
                            @foreach (['LLM Architecture' => 34, 'Systems & CUDA' => 28, 'Autonomous Agents' => 19, 'Tutorials' => 15] as $topic => $count)
                                <button class="flex items-center justify-between rounded bg-surface/50 p-2.5 text-left text-sm text-on-surface-variant transition-colors hover:bg-surface-high hover:text-primary" type="button" @click="activeCategory = @js($topic); document.getElementById('articles').scrollIntoView({ behavior: 'smooth' })">
                                    <span>{{ $topic }}</span>
                                    <span class="rounded bg-surface-high px-2 py-0.5 font-mono text-[10px] text-outline">{{ $count }}</span>
                                </button>
                            @endforeach
                        </div>
                    </section>
                </aside>
            </div>
        </section>

        <footer class="border-t border-outline-variant/30 bg-surface-lowest">
            <div class="mx-auto grid max-w-7xl gap-8 px-4 py-10 sm:px-6 md:grid-cols-12 lg:px-8">
                <div class="md:col-span-6">
                    <a class="flex items-center gap-2.5 font-display font-semibold text-on-surface" href="{{ route('home') }}">
                        <span class="flex size-7 items-center justify-center rounded border border-primary/30 bg-primary/10 text-sm text-primary">A</span>
                        NeuralLog
                    </a>
                    <p class="mt-3 max-w-md text-sm leading-6 text-on-surface-variant">Rigorous notes on AI systems, distributed infrastructure, and the practical work behind reliable software.</p>
                    <p class="mt-4 flex items-center gap-2 font-mono text-[10px] uppercase tracking-[0.1em] text-tertiary"><span class="size-1.5 animate-pulse rounded-full bg-tertiary"></span> Human-in-the-loop research</p>
                </div>
                <div class="md:col-span-3">
                    <span class="font-mono text-[10px] uppercase tracking-[0.15em] text-on-surface">Explore</span>
                    <div class="mt-3 flex flex-col gap-2 text-sm text-on-surface-variant">
                        <a class="transition-colors hover:text-primary" href="#articles">Articles</a>
                        <a class="transition-colors hover:text-primary" href="#weekly">AI Weekly</a>
                        <a class="transition-colors hover:text-primary" href="#categories">Categories</a>
                    </div>
                </div>
                <div class="md:col-span-3">
                    <span class="font-mono text-[10px] uppercase tracking-[0.15em] text-on-surface">Account</span>
                    <div class="mt-3 flex flex-col gap-2 text-sm text-on-surface-variant">
                        <a class="transition-colors hover:text-primary" href="{{ route('login') }}">Sign in</a>
                        <a class="transition-colors hover:text-primary" href="{{ route('register') }}">Create account</a>
                        <a class="transition-colors hover:text-primary" href="#about">About the author</a>
                    </div>
                </div>
            </div>
            <div class="mx-auto max-w-7xl border-t border-outline-variant/20 px-4 py-4 font-mono text-[10px] text-outline sm:px-6 lg:px-8">© {{ date('Y') }} NeuralLog. Notes from the edge of the stack.</div>
        </footer>

        <div x-cloak x-show="bookmarkNotice" x-transition class="fixed inset-x-4 bottom-4 z-50 mx-auto flex max-w-md items-center justify-between gap-4 rounded-lg border border-primary/30 bg-surface-high px-4 py-3 shadow-2xl shadow-black/30" role="status">
            <p class="text-sm text-on-surface">Sign in to save articles to your bookmarks.</p>
            <div class="flex shrink-0 items-center gap-2">
                <a class="rounded bg-primary px-3 py-1.5 font-mono text-[10px] uppercase tracking-[0.08em] text-on-primary" :href="loginUrl">Sign in</a>
                <button class="rounded p-1 text-outline transition-colors hover:text-on-surface" type="button" aria-label="Dismiss" @click="bookmarkNotice = false">
                    <span class="material-symbols-outlined text-[18px]" aria-hidden="true">close</span>
                </button>
            </div>
        </div>
    </div>
@endsection
