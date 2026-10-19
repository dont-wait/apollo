@extends('layouts.admin')

@section('title', 'AI Sources - Apollo Admin')

@section('content')
<div class="-m-8 min-h-full space-y-6 bg-[#111319] p-4 text-[#e1e2ea] sm:p-8">
    @include('admin.ai-weekly.nav')
    <h1 class="text-3xl font-semibold">Nguồn AI</h1>
    <form data-ai-form data-reload="true" method="post" action="{{ route('admin.ai-sources.store') }}" class="grid gap-3 rounded-lg border border-[#32353b] bg-[#1b1e25] p-5 md:grid-cols-2">
        @csrf
        <label class="grid gap-1 text-sm">Tên nguồn <input name="name" required maxlength="150" class="rounded bg-[#111319] p-2"></label>
        <label class="grid gap-1 text-sm">Loại <select name="source_type" class="rounded bg-[#111319] p-2"><option>RSS</option><option>API</option><option>WEBSITE</option></select></label>
        <label class="grid gap-1 text-sm">Site URL <input name="site_url" type="url" required class="rounded bg-[#111319] p-2"></label>
        <label class="grid gap-1 text-sm">Feed URL <input name="feed_url" type="url" class="rounded bg-[#111319] p-2"></label>
        <label class="grid gap-1 text-sm">Adapter API <input name="adapter_key" placeholder="generic_json" class="rounded bg-[#111319] p-2"></label>
        <label class="grid gap-1 text-sm">Trust score <input name="trust_score" type="number" min="1" max="10" value="5" required class="rounded bg-[#111319] p-2"></label>
        <label class="grid gap-1 text-sm">Priority <input name="priority" type="number" min="1" max="10" value="5" required class="rounded bg-[#111319] p-2"></label>
        <label class="grid gap-1 text-sm">Trạng thái <select name="status" class="rounded bg-[#111319] p-2"><option>ACTIVE</option><option>INACTIVE</option></select></label>
        <div class="md:col-span-2"><button type="submit" class="rounded bg-sky-500 px-4 py-2 font-semibold text-[#111319]">Thêm nguồn</button><p data-ai-feedback role="status" class="mt-2 text-sm"></p></div>
    </form>
    <div class="grid gap-4">
        @foreach ($sources as $source)
            <form data-ai-form data-reload="true" data-method="PUT" method="post" action="{{ route('admin.ai-sources.update', $source) }}" class="grid gap-3 rounded-lg border border-[#32353b] bg-[#1b1e25] p-4 md:grid-cols-4">
                @csrf
                <label class="grid gap-1 text-sm">Tên <input name="name" value="{{ $source->name }}" required class="rounded bg-[#111319] p-2"></label>
                <label class="grid gap-1 text-sm">Loại <select name="source_type" class="rounded bg-[#111319] p-2">@foreach (['RSS', 'API', 'WEBSITE'] as $type)<option @selected($source->source_type === $type)>{{ $type }}</option>@endforeach</select></label>
                <label class="grid gap-1 text-sm">Site URL <input name="site_url" value="{{ $source->site_url }}" type="url" required class="rounded bg-[#111319] p-2"></label>
                <label class="grid gap-1 text-sm">Feed URL <input name="feed_url" value="{{ $source->feed_url }}" type="url" class="rounded bg-[#111319] p-2"></label>
                <label class="grid gap-1 text-sm">Adapter <input name="adapter_key" value="{{ $source->adapter_key }}" class="rounded bg-[#111319] p-2"></label>
                <label class="grid gap-1 text-sm">Trust <input name="trust_score" type="number" min="1" max="10" value="{{ $source->trust_score }}" required class="rounded bg-[#111319] p-2"></label>
                <label class="grid gap-1 text-sm">Priority <input name="priority" type="number" min="1" max="10" value="{{ $source->priority }}" required class="rounded bg-[#111319] p-2"></label>
                <label class="grid gap-1 text-sm">Trạng thái <select name="status" class="rounded bg-[#111319] p-2">@foreach (['ACTIVE', 'INACTIVE'] as $status)<option @selected($source->status === $status)>{{ $status }}</option>@endforeach</select></label>
                <div class="flex items-center gap-3 md:col-span-4"><button type="submit" class="rounded bg-sky-500 px-4 py-2 font-semibold text-[#111319]">Lưu</button><span class="text-sm text-[#bdc8d1]">{{ $source->articles_count }} bài · Fetch: {{ $source->last_fetched_at?->format('d/m/Y H:i') ?? 'Chưa chạy' }}</span><span data-ai-feedback role="status"></span></div>
            </form>
        @endforeach
    </div>
    {{ $sources->links() }}
</div>
@endsection
