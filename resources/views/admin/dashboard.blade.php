@extends('layouts.admin')

@section('title', 'Admin Dashboard - Apollo')
@section('page-title', 'Operations Dashboard')

@section('content')
    <div class="-m-8 flex min-h-full flex-col gap-6 bg-[#111319] p-4 text-[#e1e2ea] sm:p-8">
        <section class="flex flex-col justify-between gap-6 xl:flex-row xl:items-end">
            <div class="flex flex-col gap-2">
                <div class="flex flex-wrap items-center gap-2 text-[11px] uppercase tracking-[0.16em] text-[#87929a]">
                    <span>Telemetry // Node Orchestrator</span>
                    <span class="inline-flex items-center gap-1.5 rounded bg-[#272a30] px-2 py-1 font-mono text-[10px] tracking-normal text-[#56e5a9]">
                        <span class="h-1.5 w-1.5 animate-pulse rounded-full bg-[#56e5a9]"></span>
                        Production Node · US-East-1
                    </span>
                </div>
                <h1 class="text-3xl font-semibold tracking-tight text-[#e1e2ea] sm:text-4xl">Operations Dashboard</h1>
                <p class="max-w-2xl text-sm leading-6 text-[#bdc8d1]">
                    Real-time metrics, autonomous research ingestion pipelines, and editorial state monitoring.
                </p>
            </div>

            <div class="flex flex-col gap-3 sm:flex-row sm:flex-wrap sm:items-center xl:justify-end">
                <div class="flex w-fit items-center rounded-lg bg-[#0b0e13] p-1 shadow-sm" aria-label="Date range">
                    <button class="rounded px-3 py-1.5 text-xs text-[#bdc8d1] transition-colors hover:text-white" type="button">Last 7 Days</button>
                    <button class="rounded bg-[#1d2025] px-3 py-1.5 text-xs font-semibold text-[#8ed5ff] shadow-sm" type="button" aria-pressed="true">Last 30 Days</button>
                    <button class="rounded px-3 py-1.5 text-xs text-[#bdc8d1] transition-colors hover:text-white" type="button">Quarter</button>
                    <button class="rounded px-3 py-1.5 text-xs text-[#bdc8d1] transition-colors hover:text-white" type="button">Custom</button>
                </div>
                <div class="flex flex-wrap items-center gap-2">
                    <button class="inline-flex items-center gap-2 rounded-lg bg-[#272a30] px-4 py-2 text-xs font-medium text-[#e1e2ea] shadow-sm transition-colors hover:bg-[#36393f]" type="button">
                        <i class="fa-solid fa-bolt text-[#56e5a9]" aria-hidden="true"></i>
                        <span>Trigger Weekly AI Run</span>
                    </button>
                    <button class="inline-flex items-center gap-2 rounded-lg bg-[#38bdf8] px-4 py-2 text-xs font-semibold text-[#00354a] shadow-sm transition-colors hover:bg-[#7bd0ff]" type="button">
                        <i class="fa-solid fa-circle-plus" aria-hidden="true"></i>
                        <span>New Article</span>
                    </button>
                </div>
            </div>
        </section>

        <section class="relative flex flex-col justify-between gap-4 overflow-hidden rounded-xl bg-[#191c21] p-4 shadow-md md:flex-row md:items-center">
            <div class="absolute inset-y-0 left-0 w-1.5 bg-gradient-to-b from-[#8ed5ff] to-[#56e5a9]"></div>
            <div class="flex items-start gap-4 pl-2 md:items-center">
                <div class="flex h-11 w-11 shrink-0 items-center justify-center rounded-lg bg-[#1d2025] text-[#8ed5ff] shadow-sm">
                    <i class="fa-solid fa-wand-magic-sparkles text-lg" aria-hidden="true"></i>
                </div>
                <div>
                    <div class="mb-1 flex flex-wrap items-center gap-2 text-[10px] uppercase tracking-wider">
                        <span class="font-semibold text-[#8ed5ff]">Autonomous Pipeline Alert</span>
                        <span class="h-1 w-1 rounded-full bg-[#87929a]"></span>
                        <span class="text-[#bdc8d1]">42m ago</span>
                    </div>
                    <p class="text-sm leading-6 text-[#e1e2ea]">
                        Bản nháp <span class="font-semibold text-[#8ed5ff]">AI Weekly #43</span> đã hoàn thành thu thập và tổng hợp — Sẵn sàng kiểm duyệt &amp; tinh chỉnh taxonomy.
                    </p>
                </div>
            </div>
            <button class="group inline-flex shrink-0 items-center justify-center gap-2 self-end rounded-lg bg-[#8ed5ff] px-4 py-2 text-xs font-semibold text-[#00354a] shadow-sm transition-colors hover:bg-[#c4e7ff] md:self-auto" type="button">
                <span>Review Draft Now</span>
                <i class="fa-solid fa-arrow-right transition-transform group-hover:translate-x-0.5" aria-hidden="true"></i>
            </button>
        </section>

        <section class="grid grid-cols-1 gap-4 sm:grid-cols-2 xl:grid-cols-4" aria-label="Key metrics">
            <article class="flex flex-col justify-between gap-4 rounded-xl bg-[#191c21] p-5 shadow-sm transition-shadow hover:shadow-md">
                <div class="flex items-center justify-between text-[#bdc8d1]">
                    <span class="text-[10px] uppercase tracking-wider">Total Readers</span>
                    <i class="fa-solid fa-users text-lg text-[#8ed5ff]" aria-hidden="true"></i>
                </div>
                <div>
                    <div class="flex flex-wrap items-baseline gap-2">
                        <span class="text-3xl font-semibold tracking-tight text-[#e1e2ea]">142,850</span>
                        <span class="inline-flex items-center gap-1 text-[11px] text-[#56e5a9]">
                            <i class="fa-solid fa-arrow-up text-[10px]" aria-hidden="true"></i> +14.2%
                        </span>
                    </div>
                    <span class="text-xs text-[#bdc8d1]">vs. previous 30-day window</span>
                </div>
                <div class="h-10 w-full overflow-hidden pt-1">
                    <svg class="h-full w-full text-[#8ed5ff]" fill="none" preserveAspectRatio="none" viewBox="0 0 200 40" aria-label="Readers trend increasing">
                        <path d="M0 32 Q 25 35, 50 24 T 100 18 T 150 12 T 200 4 L 200 40 L 0 40 Z" fill="currentColor" fill-opacity=".12"></path>
                        <path d="M0 32 Q 25 35, 50 24 T 100 18 T 150 12 T 200 4" stroke="currentColor" stroke-linecap="round" stroke-width="2"></path>
                    </svg>
                </div>
            </article>

            <article class="flex flex-col justify-between gap-4 rounded-xl bg-[#191c21] p-5 shadow-sm transition-shadow hover:shadow-md">
                <div class="flex items-center justify-between text-[#bdc8d1]">
                    <span class="text-[10px] uppercase tracking-wider">Engagement Rate</span>
                    <i class="fa-solid fa-stopwatch text-lg text-[#56e5a9]" aria-hidden="true"></i>
                </div>
                <div>
                    <div class="flex flex-wrap items-baseline gap-2">
                        <span class="text-3xl font-semibold tracking-tight text-[#e1e2ea]">68.4%</span>
                        <span class="inline-flex items-center gap-1 text-[11px] text-[#56e5a9]">
                            <i class="fa-solid fa-arrow-up text-[10px]" aria-hidden="true"></i> +3.8%
                        </span>
                    </div>
                    <span class="text-xs text-[#bdc8d1]">Avg read duration: <strong class="font-semibold text-[#e1e2ea]">6m 42s</strong></span>
                </div>
                <div class="h-1.5 w-full overflow-hidden rounded-full bg-[#32353b]">
                    <div class="h-full w-[68.4%] rounded-full bg-[#56e5a9] transition-all duration-500"></div>
                </div>
            </article>

            <article class="flex flex-col justify-between gap-4 rounded-xl bg-[#191c21] p-5 shadow-sm transition-shadow hover:shadow-md">
                <div class="flex items-center justify-between text-[#bdc8d1]">
                    <span class="text-[10px] uppercase tracking-wider">Community Comments</span>
                    <i class="fa-regular fa-comments text-lg text-[#c0c1ff]" aria-hidden="true"></i>
                </div>
                <div>
                    <div class="flex flex-wrap items-baseline gap-2">
                        <span class="text-3xl font-semibold tracking-tight text-[#e1e2ea]">1,248</span>
                        <span class="rounded bg-[#1d2025] px-2 py-0.5 text-[10px] text-[#c0c1ff]">12 pending review</span>
                    </div>
                    <span class="text-xs text-[#bdc8d1]">98.2% automated spam rejection</span>
                </div>
                <div class="flex items-center gap-2 text-[10px] text-[#bdc8d1]">
                    <span class="h-2 w-2 rounded-full bg-[#c0c1ff]"></span>
                    <span>Sync queue: 3 waiting in queue</span>
                </div>
            </article>

            <article class="flex flex-col justify-between gap-4 rounded-xl bg-[#191c21] p-5 shadow-sm transition-shadow hover:shadow-md">
                <div class="flex items-center justify-between text-[#bdc8d1]">
                    <span class="text-[10px] uppercase tracking-wider">AI Weekly Status</span>
                    <i class="fa-solid fa-diagram-project text-lg text-[#30c88f]" aria-hidden="true"></i>
                </div>
                <div>
                    <div class="flex items-center gap-2">
                        <span class="h-2.5 w-2.5 rounded-full bg-[#56e5a9]"></span>
                        <span class="text-lg font-semibold text-[#e1e2ea]">Pipeline Healthy</span>
                    </div>
                    <span class="mt-1 block text-xs text-[#bdc8d1]">Last executed 4h ago · 0 errors</span>
                </div>
                <div class="flex items-center justify-between rounded bg-[#1d2025] px-2.5 py-1.5 text-[10px] text-[#bdc8d1]">
                    <span>Sources Parsed</span>
                    <span class="text-xs font-semibold text-[#8ed5ff]">18 / 18</span>
                </div>
            </article>
        </section>

        <section class="grid grid-cols-1 items-start gap-6 xl:grid-cols-12">
            <article class="flex flex-col rounded-xl bg-[#191c21] p-5 shadow-sm xl:col-span-8">
                <div class="mb-5 flex flex-col justify-between gap-4 sm:flex-row sm:items-center">
                    <div>
                        <div class="flex items-center gap-2">
                            <i class="fa-solid fa-chart-line text-sm text-[#8ed5ff]" aria-hidden="true"></i>
                            <h2 class="text-lg font-semibold text-[#e1e2ea]">Readership &amp; API Traffic Velocity</h2>
                        </div>
                        <p class="mt-1 text-xs text-[#bdc8d1]">30-day aggregate comparison: Organic Web Direct vs RSS Feeds &amp; Subscriptions</p>
                    </div>
                    <div class="flex flex-wrap items-center gap-4 text-[10px]">
                        <span class="flex items-center gap-2"><span class="h-2.5 w-2.5 rounded bg-[#8ed5ff]"></span>Organic Readers</span>
                        <span class="flex items-center gap-2"><span class="h-2.5 w-2.5 rounded bg-[#56e5a9]"></span>RSS Subscribers</span>
                    </div>
                </div>

                <div class="relative flex h-72 flex-col justify-between overflow-hidden rounded-lg bg-[#0b0e13] p-4 shadow-inner sm:h-80">
                    <div class="pointer-events-none absolute inset-0 bg-gradient-to-t from-[#8ed5ff]/5 to-transparent"></div>
                    <div class="z-10 flex items-center justify-between border-b border-[#1d2025]/60 pb-1 font-mono text-[10px] text-[#bdc8d1]"><span>80k req/d</span><span class="text-[#32353b]">•••</span></div>
                    <div class="z-10 flex items-center justify-between border-b border-[#1d2025]/60 pb-1 font-mono text-[10px] text-[#bdc8d1]"><span>50k req/d</span><span class="text-[#32353b]">•••</span></div>
                    <div class="z-10 flex items-center justify-between border-b border-[#1d2025]/60 pb-1 font-mono text-[10px] text-[#bdc8d1]"><span>20k req/d</span><span class="text-[#32353b]">•••</span></div>
                    <div class="z-10 flex items-center justify-between font-mono text-[10px] text-[#bdc8d1]"><span>0</span><span class="text-[#32353b]">•••</span></div>
                    <div class="absolute inset-0 flex items-end px-4 pb-8 pt-8">
                        <svg class="h-full w-full overflow-visible" preserveAspectRatio="none" viewBox="0 0 600 200" role="img" aria-label="Thirty-day readership and RSS traffic trend">
                            <defs>
                                <linearGradient id="rssArea" x1="0" x2="0" y1="0" y2="1">
                                    <stop offset="0%" stop-color="#56e5a9" stop-opacity=".25"></stop>
                                    <stop offset="100%" stop-color="#56e5a9" stop-opacity="0"></stop>
                                </linearGradient>
                                <linearGradient id="readersArea" x1="0" x2="0" y1="0" y2="1">
                                    <stop offset="0%" stop-color="#8ed5ff" stop-opacity=".3"></stop>
                                    <stop offset="100%" stop-color="#8ed5ff" stop-opacity="0"></stop>
                                </linearGradient>
                            </defs>
                            <path d="M0,170 C60,160 120,130 180,140 C240,150 300,110 360,95 C420,80 480,120 540,85 C570,70 600,60 600,60 L600,200 L0,200 Z" fill="url(#rssArea)"></path>
                            <path d="M0,170 C60,160 120,130 180,140 C240,150 300,110 360,95 C420,80 480,120 540,85 C570,70 600,60 600,60" fill="none" stroke="#56e5a9" stroke-width="2.5"></path>
                            <path d="M0,130 C70,110 130,70 200,90 C270,110 330,40 400,30 C470,20 520,60 560,40 C580,30 600,15 600,15 L600,200 L0,200 Z" fill="url(#readersArea)"></path>
                            <path d="M0,130 C70,110 130,70 200,90 C270,110 330,40 400,30 C470,20 520,60 560,40 C580,30 600,15 600,15" fill="none" stroke="#8ed5ff" stroke-width="2.5"></path>
                            <circle cx="400" cy="30" r="8" fill="#8ed5ff" fill-opacity=".25"></circle>
                            <circle cx="400" cy="30" r="4" fill="#8ed5ff"></circle>
                        </svg>
                    </div>
                </div>
                <div class="mt-3 flex items-center justify-between px-1 font-mono text-[10px] text-[#bdc8d1]">
                    <span>Day 01 (Feb)</span><span>Day 10</span><span>Day 20</span><span class="font-semibold text-[#8ed5ff]">Today (Day 30)</span>
                </div>
            </article>

            <article class="flex flex-col gap-4 rounded-xl bg-[#191c21] p-5 shadow-sm xl:col-span-4">
                <div class="flex items-center justify-between">
                    <div>
                        <h2 class="text-lg font-semibold text-[#e1e2ea]">Pipeline Ingestion</h2>
                        <span class="text-xs text-[#bdc8d1]">Active data crawlers</span>
                    </div>
                    <i class="fa-solid fa-arrows-rotate text-lg text-[#8ed5ff]" aria-hidden="true"></i>
                </div>

                <div class="flex items-center justify-between gap-3 rounded-lg bg-[#1d2025] p-3 shadow-sm transition-colors hover:bg-[#36393f]">
                    <div class="flex min-w-0 items-center gap-3">
                        <span class="flex h-8 w-8 shrink-0 items-center justify-center rounded bg-[#272a30] font-mono text-xs font-semibold text-[#8ed5ff]">aX</span>
                        <div class="min-w-0"><span class="block text-sm font-semibold text-[#e1e2ea]">ArXiv cs.AI / cs.LG</span><span class="block text-[10px] text-[#bdc8d1]">142 papers scraped today</span></div>
                    </div>
                    <span class="inline-flex shrink-0 items-center gap-1.5 rounded bg-[#272a30] px-2 py-1 text-[9px] text-[#56e5a9]"><span class="h-1.5 w-1.5 rounded-full bg-[#56e5a9]"></span>ACTIVE</span>
                </div>

                <div class="flex items-center justify-between gap-3 rounded-lg bg-[#1d2025] p-3 shadow-sm transition-colors hover:bg-[#36393f]">
                    <div class="flex min-w-0 items-center gap-3">
                        <span class="flex h-8 w-8 shrink-0 items-center justify-center rounded bg-[#272a30] font-mono text-xs font-semibold text-[#8ed5ff]">HN</span>
                        <div class="min-w-0"><span class="block text-sm font-semibold text-[#e1e2ea]">HackerNews API</span><span class="block text-[10px] text-[#bdc8d1]">Top 30 AI threads parsed</span></div>
                    </div>
                    <span class="inline-flex shrink-0 items-center gap-1.5 rounded bg-[#272a30] px-2 py-1 text-[9px] text-[#56e5a9]"><span class="h-1.5 w-1.5 rounded-full bg-[#56e5a9]"></span>ACTIVE</span>
                </div>

                <div class="flex items-center justify-between gap-3 rounded-lg bg-[#1d2025] p-3 shadow-sm transition-colors hover:bg-[#36393f]">
                    <div class="flex min-w-0 items-center gap-3">
                        <span class="flex h-8 w-8 shrink-0 items-center justify-center rounded bg-[#272a30] font-mono text-xs font-semibold text-[#8ed5ff]">HF</span>
                        <div class="min-w-0"><span class="block text-sm font-semibold text-[#e1e2ea]">HuggingFace Daily</span><span class="block text-[10px] text-[#bdc8d1]">24 models evaluated</span></div>
                    </div>
                    <span class="inline-flex shrink-0 items-center gap-1.5 rounded bg-[#272a30] px-2 py-1 text-[9px] text-[#56e5a9]"><span class="h-1.5 w-1.5 rounded-full bg-[#56e5a9]"></span>ACTIVE</span>
                </div>

                <div class="flex items-center justify-between gap-3 rounded-lg bg-[#1d2025] p-3 shadow-sm transition-colors hover:bg-[#36393f]">
                    <div class="flex min-w-0 items-center gap-3">
                        <span class="flex h-8 w-8 shrink-0 items-center justify-center rounded bg-[#272a30] font-mono text-xs font-semibold text-[#8ed5ff]">GH</span>
                        <div class="min-w-0"><span class="block text-sm font-semibold text-[#e1e2ea]">GitHub Trending</span><span class="block text-[10px] text-[#bdc8d1]">Ingesting diff batch #882</span></div>
                    </div>
                    <span class="inline-flex shrink-0 items-center gap-1.5 rounded bg-[#272a30] px-2 py-1 text-[9px] text-[#8ed5ff]"><span class="h-1.5 w-1.5 rounded-full bg-[#8ed5ff]"></span>SYNCING</span>
                </div>
            </article>
        </section>

        <section class="grid grid-cols-1 gap-4 md:grid-cols-3" aria-label="Editorial highlights">
            <article class="group relative flex h-44 flex-col justify-between overflow-hidden rounded-xl bg-[#191c21] p-4 shadow-sm">
                <div class="absolute inset-0 bg-cover bg-center opacity-20 transition-all duration-500 group-hover:scale-105 group-hover:opacity-30" style="background-image: url('https://lh3.googleusercontent.com/aida-public/AB6AXuBYif8BZZwn7CKV2-ojfAX0IhDeDqOUGFf15BKcutSn-VZar0PD92g9Z8X8B-E0NfAQIGho5K4g3vMOR1I0z6zc984pWzam_kzpnM3Fwad_40FZrwvjzBmJrf66q_9rkLpjlUvP9bW6jC3_cYy8m1-T5QGaeChueV8xuK8xNbjxd2yGq-9hffx2NPyyTaz9VBiBTm-neCbkhh1iX_VmPg9oADl0ajrnrAAVg9l6DVb4R2yxZyVUpU-T')"></div>
                <div class="relative z-10 flex items-start justify-between">
                    <span class="rounded bg-[#32353b] px-2 py-0.5 text-[10px] text-[#8ed5ff]">Inference Labs</span>
                    <i class="fa-solid fa-microchip text-[#8ed5ff]" aria-hidden="true"></i>
                </div>
                <div class="relative z-10"><h3 class="font-semibold text-[#e1e2ea]">Quantization Benchmarks</h3><p class="text-xs text-[#bdc8d1]">vLLM vs TensorRT-LLM latency report</p></div>
            </article>
            <article class="group relative flex h-44 flex-col justify-between overflow-hidden rounded-xl bg-[#191c21] p-4 shadow-sm">
                <div class="absolute inset-0 bg-cover bg-center opacity-20 transition-all duration-500 group-hover:scale-105 group-hover:opacity-30" style="background-image: url('https://lh3.googleusercontent.com/aida-public/AB6AXuD1Jg6__tACkm18lpRaOmMd4s1Nec2dqiEPn3Ssv-yB_QqLb4JxBvB_aIxYiJN8vIOIfkGR5l5BTtJXwmcGMLgzNu5zQiljJ0zy0nDR9lQqqv7OTTJhktRfP3JSloMhl_Q8bL_5ZDclRpX7GP-8rFHpz9Bd1VExmMFXsN_JWUcf2-iqTk9fWhTakhX_N2bbOAsr1VAu6v1LHH589IaXiOVKTDq9aqoUTfb-PAdU7O4k6n4BErfvYPjH')"></div>
                <div class="relative z-10 flex items-start justify-between">
                    <span class="rounded bg-[#32353b] px-2 py-0.5 text-[10px] text-[#56e5a9]">Autonomous Core</span>
                    <i class="fa-solid fa-brain text-[#56e5a9]" aria-hidden="true"></i>
                </div>
                <div class="relative z-10"><h3 class="font-semibold text-[#e1e2ea]">Reasoning Distillation</h3><p class="text-xs text-[#bdc8d1]">DeepSeek-R1 architectural breakdown</p></div>
            </article>
            <article class="group relative flex h-44 flex-col justify-between overflow-hidden rounded-xl bg-[#191c21] p-4 shadow-sm">
                <div class="absolute inset-0 bg-cover bg-center opacity-20 transition-all duration-500 group-hover:scale-105 group-hover:opacity-30" style="background-image: url('https://lh3.googleusercontent.com/aida-public/AB6AXuDpjq_BpeLdwn065KBtpqOUZFnTLGEZWXVZQrAWuWJPePmWcD-eNsSfA_99UsijTP0u9mMFuHiaFe63QlKj_Ah8sdJDtx-G3LeEzCh-vJst5BmjlqkIzcM3yy5jTiCWafXrBEOD_daf-XYDPJlpl8QubTcd5Qoid2ZlizZBpX-hE-mc4FoHNtJcu2AqEcMiiQ1M7nOZz3i3GrOt140ikvNgZDSi9U_-7QnYQxuN9605N2i2I7i_kNHO')"></div>
                <div class="relative z-10 flex items-start justify-between">
                    <span class="rounded bg-[#32353b] px-2 py-0.5 text-[10px] text-[#c0c1ff]">Audio &amp; RTC</span>
                    <i class="fa-solid fa-wave-square text-[#c0c1ff]" aria-hidden="true"></i>
                </div>
                <div class="relative z-10"><h3 class="font-semibold text-[#e1e2ea]">Sub-100ms Voice Loops</h3><p class="text-xs text-[#bdc8d1]">WebRTC full-duplex client benchmarks</p></div>
            </article>
        </section>

        <section class="flex flex-col overflow-hidden rounded-xl bg-[#191c21] shadow-sm">
            <div class="flex flex-col justify-between gap-4 p-5 sm:flex-row sm:items-center">
                <div>
                    <h2 class="text-lg font-semibold text-[#e1e2ea]">Recent Articles &amp; Editorial Log</h2>
                    <p class="text-xs text-[#bdc8d1]">Manage publications, triage review drafts, and audit release cycles.</p>
                </div>
                <button class="w-fit rounded-lg bg-[#1d2025] px-3 py-1.5 font-mono text-[10px] text-[#e1e2ea] transition-colors hover:bg-[#36393f]" type="button">View All (48)</button>
            </div>

            <div class="w-full overflow-x-auto">
                <table class="w-full min-w-[760px] border-collapse text-left">
                    <thead>
                        <tr class="bg-[#0b0e13] text-[10px] uppercase tracking-wider text-[#bdc8d1]">
                            <th class="px-5 py-3">Title &amp; Subtitle</th>
                            <th class="px-4 py-3">Category</th>
                            <th class="px-4 py-3">Status</th>
                            <th class="px-4 py-3 text-right">Views</th>
                            <th class="px-4 py-3 text-right">Upvotes / Saves</th>
                            <th class="px-5 py-3 text-center">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-[#272a30] text-xs">
                        <tr class="transition-colors hover:bg-[#1d2025]/50">
                            <td class="max-w-md px-5 py-4">
                                <div class="flex flex-col">
                                    <a class="truncate font-semibold text-[#e1e2ea] transition-colors hover:text-[#8ed5ff]" href="#">Optimizing Inference on Consumer Hardware: From FP16 to Int4</a>
                                    <span class="mt-0.5 truncate text-[10px] text-[#bdc8d1]">Kernel level execution, memory bounds and AWQ evaluation matrix.</span>
                                </div>
                            </td>
                            <td class="whitespace-nowrap px-4 py-4"><span class="rounded bg-[#1d2025] px-2.5 py-1 text-[10px] text-[#e1e2ea]">Hardware · CUDA</span></td>
                            <td class="whitespace-nowrap px-4 py-4"><span class="inline-flex items-center gap-1.5 rounded bg-[#272a30] px-2.5 py-1 text-[10px] text-[#56e5a9]"><span class="h-1.5 w-1.5 rounded-full bg-[#56e5a9]"></span>PUBLISHED</span></td>
                            <td class="whitespace-nowrap px-4 py-4 text-right font-mono text-[10px] text-[#e1e2ea]">3,412</td>
                            <td class="whitespace-nowrap px-4 py-4 text-right font-mono text-[10px] text-[#bdc8d1]">284 / 96</td>
                            <td class="whitespace-nowrap px-5 py-4">
                                <div class="flex items-center justify-center gap-1">
                                    <button class="rounded p-1.5 text-[#bdc8d1] transition-colors hover:bg-[#1d2025] hover:text-[#8ed5ff]" title="Edit article" type="button"><i class="fa-solid fa-pen-to-square" aria-hidden="true"></i></button>
                                    <button class="rounded p-1.5 text-[#bdc8d1] transition-colors hover:bg-[#1d2025] hover:text-[#8ed5ff]" title="View analytics" type="button"><i class="fa-solid fa-chart-simple" aria-hidden="true"></i></button>
                                    <button class="rounded p-1.5 text-[#bdc8d1] transition-colors hover:bg-[#1d2025] hover:text-[#8ed5ff]" title="More options" type="button"><i class="fa-solid fa-ellipsis" aria-hidden="true"></i></button>
                                </div>
                            </td>
                        </tr>
                        <tr class="bg-[#1d2025]/20 transition-colors hover:bg-[#1d2025]/50">
                            <td class="max-w-md px-5 py-4">
                                <div class="flex flex-col">
                                    <a class="truncate font-semibold text-[#e1e2ea] transition-colors hover:text-[#8ed5ff]" href="#">AI Weekly #43: DeepSeek Reasoning Models &amp; Frontier Eval</a>
                                    <span class="mt-0.5 truncate text-[10px] text-[#bdc8d1]">Automated digest parsing 18 primary sources with synthesis pass.</span>
                                </div>
                            </td>
                            <td class="whitespace-nowrap px-4 py-4"><span class="rounded bg-[#1d2025] px-2.5 py-1 text-[10px] text-[#e1e2ea]">AI Weekly</span></td>
                            <td class="whitespace-nowrap px-4 py-4"><span class="inline-flex items-center gap-1.5 rounded bg-[#272a30] px-2.5 py-1 text-[10px] text-[#8ed5ff]"><span class="h-1.5 w-1.5 animate-ping rounded-full bg-[#8ed5ff]"></span>REVIEW</span></td>
                            <td class="whitespace-nowrap px-4 py-4 text-right font-mono text-[10px] text-[#bdc8d1]">—</td>
                            <td class="whitespace-nowrap px-4 py-4 text-right font-mono text-[10px] text-[#bdc8d1]">—</td>
                            <td class="whitespace-nowrap px-5 py-4">
                                <div class="flex items-center justify-center gap-1">
                                    <button class="rounded bg-[#8ed5ff] px-2 py-1 text-[10px] font-semibold text-[#00354a] shadow-sm hover:bg-[#c4e7ff]" type="button">Triage</button>
                                    <button class="rounded p-1.5 text-[#bdc8d1] transition-colors hover:bg-[#1d2025] hover:text-[#8ed5ff]" title="More options" type="button"><i class="fa-solid fa-ellipsis" aria-hidden="true"></i></button>
                                </div>
                            </td>
                        </tr>
                        <tr class="transition-colors hover:bg-[#1d2025]/50">
                            <td class="max-w-md px-5 py-4">
                                <div class="flex flex-col">
                                    <a class="truncate font-semibold text-[#e1e2ea] transition-colors hover:text-[#8ed5ff]" href="#">Building Low-latency Voice Agents with WebRTC</a>
                                    <span class="mt-0.5 truncate text-[10px] text-[#bdc8d1]">Full-duplex audio transmission pipelines and turn detection strategies.</span>
                                </div>
                            </td>
                            <td class="whitespace-nowrap px-4 py-4"><span class="rounded bg-[#1d2025] px-2.5 py-1 text-[10px] text-[#e1e2ea]">Systems · Audio</span></td>
                            <td class="whitespace-nowrap px-4 py-4"><span class="inline-flex items-center gap-1.5 rounded bg-[#272a30] px-2.5 py-1 text-[10px] text-[#87929a]"><span class="h-1.5 w-1.5 rounded-full bg-[#87929a]"></span>DRAFT</span></td>
                            <td class="whitespace-nowrap px-4 py-4 text-right font-mono text-[10px] text-[#bdc8d1]">—</td>
                            <td class="whitespace-nowrap px-4 py-4 text-right font-mono text-[10px] text-[#bdc8d1]">—</td>
                            <td class="whitespace-nowrap px-5 py-4">
                                <div class="flex items-center justify-center gap-1">
                                    <button class="rounded p-1.5 text-[#bdc8d1] transition-colors hover:bg-[#1d2025] hover:text-[#8ed5ff]" title="Edit article" type="button"><i class="fa-solid fa-pen-to-square" aria-hidden="true"></i></button>
                                    <button class="rounded p-1.5 text-[#bdc8d1] transition-colors hover:bg-[#1d2025] hover:text-[#8ed5ff]" title="More options" type="button"><i class="fa-solid fa-ellipsis" aria-hidden="true"></i></button>
                                </div>
                            </td>
                        </tr>
                        <tr class="transition-colors hover:bg-[#1d2025]/50">
                            <td class="max-w-md px-5 py-4">
                                <div class="flex flex-col">
                                    <a class="truncate font-semibold text-[#e1e2ea] transition-colors hover:text-[#8ed5ff]" href="#">State of Open Weights: Q1 2025 Retrospective</a>
                                    <span class="mt-0.5 truncate text-[10px] text-[#bdc8d1]">Comprehensive evaluation of release velocity and compute efficiency.</span>
                                </div>
                            </td>
                            <td class="whitespace-nowrap px-4 py-4"><span class="rounded bg-[#1d2025] px-2.5 py-1 text-[10px] text-[#e1e2ea]">Research · Macro</span></td>
                            <td class="whitespace-nowrap px-4 py-4"><span class="inline-flex items-center gap-1.5 rounded bg-[#272a30] px-2.5 py-1 text-[10px] text-[#c0c1ff]"><span class="h-1.5 w-1.5 rounded-full bg-[#c0c1ff]"></span>SCHEDULED</span></td>
                            <td class="whitespace-nowrap px-4 py-4 text-right font-mono text-[10px] text-[#bdc8d1]">Tomorrow 09:00</td>
                            <td class="whitespace-nowrap px-4 py-4 text-right font-mono text-[10px] text-[#bdc8d1]">—</td>
                            <td class="whitespace-nowrap px-5 py-4">
                                <div class="flex items-center justify-center gap-1">
                                    <button class="rounded p-1.5 text-[#bdc8d1] transition-colors hover:bg-[#1d2025] hover:text-[#8ed5ff]" title="Edit article" type="button"><i class="fa-solid fa-pen-to-square" aria-hidden="true"></i></button>
                                    <button class="rounded p-1.5 text-[#bdc8d1] transition-colors hover:bg-[#1d2025] hover:text-[#c0c1ff]" title="Reschedule" type="button"><i class="fa-regular fa-clock" aria-hidden="true"></i></button>
                                    <button class="rounded p-1.5 text-[#bdc8d1] transition-colors hover:bg-[#1d2025] hover:text-[#8ed5ff]" title="More options" type="button"><i class="fa-solid fa-ellipsis" aria-hidden="true"></i></button>
                                </div>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <div class="flex flex-col justify-between gap-2 bg-[#0b0e13] px-5 py-3 font-mono text-[10px] text-[#bdc8d1] sm:flex-row sm:items-center">
                <span>Showing 4 of 48 total recorded editorial entries</span>
                <span>Sort: By Last Updated (Descending)</span>
            </div>
        </section>
    </div>
@endsection
