<div class="grid gap-4 sm:grid-cols-2">
    <label class="grid gap-2 text-xs font-medium text-on-surface-variant sm:col-span-2">Tên nguồn
        <input name="name" value="{{ $source?->name }}" required maxlength="150" placeholder="Tên nhà xuất bản hoặc tổ chức" class="min-w-0 rounded border border-outline-variant bg-surface-container-lowest px-3 py-2.5 text-sm text-on-surface outline-none transition focus:border-primary">
    </label>
    <label class="grid gap-2 text-xs font-medium text-on-surface-variant">Loại nguồn
        <select name="source_type" class="rounded border border-outline-variant bg-surface-container-lowest px-3 py-2.5 text-sm text-on-surface outline-none focus:border-primary">
            @foreach (['RSS', 'API', 'WEBSITE'] as $type)
                <option value="{{ $type }}" @selected(($source?->source_type ?? 'RSS') === $type)>{{ $type }}</option>
            @endforeach
        </select>
    </label>
    <label class="grid gap-2 text-xs font-medium text-on-surface-variant">Trạng thái
        <select name="status" class="rounded border border-outline-variant bg-surface-container-lowest px-3 py-2.5 text-sm text-on-surface outline-none focus:border-primary">
            <option value="ACTIVE" @selected(($source?->status ?? 'ACTIVE') === 'ACTIVE')>Hoạt động</option>
            <option value="INACTIVE" @selected($source?->status === 'INACTIVE')>Tạm dừng</option>
        </select>
    </label>
    <label class="grid gap-2 text-xs font-medium text-on-surface-variant sm:col-span-2">Website
        <input name="site_url" value="{{ $source?->site_url }}" type="url" required maxlength="500" placeholder="https://example.com" class="min-w-0 rounded border border-outline-variant bg-surface-container-lowest px-3 py-2.5 font-mono text-xs text-on-surface outline-none focus:border-primary">
    </label>
    <label class="grid gap-2 text-xs font-medium text-on-surface-variant sm:col-span-2">Feed hoặc API URL
        <input name="feed_url" value="{{ $source?->feed_url }}" type="url" maxlength="1000" placeholder="https://example.com/feed.xml" class="min-w-0 rounded border border-outline-variant bg-surface-container-lowest px-3 py-2.5 font-mono text-xs text-on-surface outline-none focus:border-primary">
        <span class="text-xs font-normal text-outline">Bắt buộc với RSS và API.</span>
    </label>
    <label class="grid gap-2 text-xs font-medium text-on-surface-variant sm:col-span-2">Adapter API
        <select name="adapter_key" class="min-w-0 rounded border border-outline-variant bg-surface-container-lowest px-3 py-2.5 font-mono text-xs text-on-surface outline-none focus:border-primary">
            <option value="">Không sử dụng</option>
            @foreach (array_keys(config('ai_weekly.api_adapters', [])) as $adapter)
                <option value="{{ $adapter }}" @selected($source?->adapter_key === $adapter)>{{ $adapter }}</option>
            @endforeach
        </select>
        <span class="text-xs font-normal text-outline">Chọn adapter khi sử dụng nguồn API.</span>
    </label>
    <label class="grid gap-2 text-xs font-medium text-on-surface-variant">Độ tin cậy / 10
        <input name="trust_score" type="number" min="1" max="10" value="{{ $source?->trust_score ?? 5 }}" required class="min-w-0 rounded border border-outline-variant bg-surface-container-lowest px-3 py-2.5 text-sm text-on-surface outline-none focus:border-primary">
    </label>
    <label class="grid gap-2 text-xs font-medium text-on-surface-variant">Ưu tiên / 10
        <input name="priority" type="number" min="1" max="10" value="{{ $source?->priority ?? 5 }}" required class="min-w-0 rounded border border-outline-variant bg-surface-container-lowest px-3 py-2.5 text-sm text-on-surface outline-none focus:border-primary">
    </label>
</div>
