@extends('layouts.admin')

@section('title', 'Media Library - Apollo Admin')

@section('content')
    @php
        $totalAssets = $media->count();
        $totalBytes = $media->sum('size_bytes');

        $storageLabel = strtoupper((string) config('filesystems.media_disk', 'local'));

        $formatBytes = function ($bytes) {
            return match (true) {
                $bytes >= 1073741824 => number_format($bytes / 1073741824, 2) . ' GB',
                $bytes >= 1048576 => number_format($bytes / 1048576, 2) . ' MB',
                $bytes >= 1024 => number_format($bytes / 1024, 2) . ' KB',
                default => $bytes . ' B',
            };
        };
    @endphp

    <div
        id="mediaLibrary"
        class="-m-8"
        data-store-url="{{ route('admin.media.store') }}"
        data-csrf="{{ csrf_token() }}"
    >
        {{-- SUB HEADER --}}
        <section
            class="w-full px-space-lg py-4 bg-surface-container-lowest
                   flex flex-col md:flex-row md:items-center
                   justify-between gap-4"
        >
            <div class="flex flex-col gap-1 min-w-0">
                <div
                    class="flex items-center gap-2
                           font-label-sm text-label-sm text-outline"
                >
                    <span>Telemetry</span>
                    <span class="text-outline-variant">/</span>

                    <span class="text-on-surface-variant">
                        Content Assets
                    </span>

                    <span class="text-outline-variant">/</span>

                    <span class="text-primary flex items-center gap-1">
                        <span
                            class="w-1.5 h-1.5 rounded-full
                                   bg-primary animate-pulse"
                        ></span>

                        Media Storage
                    </span>
                </div>

                <div class="flex items-baseline gap-3 flex-wrap">
                    <h1
                        class="font-headline-lg text-headline-lg
                               text-on-surface tracking-tight"
                    >
                        Media Library &amp; CDN Assets
                    </h1>

                    <span
                        class="font-label-sm text-label-sm
                               text-tertiary
                               bg-tertiary-container/10
                               px-2 py-0.5 rounded"
                    >
                        {{ $storageLabel }} STORAGE ACTIVE
                    </span>
                </div>

                <p
                    class="font-body-sm text-body-sm
                           text-on-surface-variant max-w-3xl"
                >
                    Lưu trữ, quản lý và tái sử dụng hình ảnh hoặc
                    tài liệu kỹ thuật cho bài viết.
                </p>
            </div>

            <div
                class="flex items-center gap-2 flex-wrap
                       self-start md:self-center shrink-0"
            >
                <button
                    type="button"
                    disabled
                    title="Chưa triển khai CDN Sync"
                    class="flex items-center gap-1.5 px-3 py-2
                           bg-surface-container-high
                           text-outline text-label-md
                           font-label-md rounded
                           opacity-50 cursor-not-allowed"
                >
                    <span
                        class="material-symbols-outlined
                               text-[18px]"
                    >
                        sync
                    </span>

                    <span>CDN Sync All</span>
                </button>

                <button
                    type="button"
                    disabled
                    title="Chưa triển khai Clean Orphaned"
                    class="flex items-center gap-1.5 px-3 py-2
                           bg-surface-container-high
                           text-outline text-label-md
                           font-label-md rounded
                           opacity-50 cursor-not-allowed"
                >
                    <span
                        class="material-symbols-outlined
                               text-[18px]"
                    >
                        cleaning_services
                    </span>

                    <span>Clean Orphaned</span>
                </button>

                <button
                    id="openUploadBtn"
                    type="button"
                    class="flex items-center gap-1.5 px-4 py-2
                           bg-primary-container hover:bg-primary
                           text-on-primary font-label-md
                           text-label-md font-semibold rounded
                           shadow-md transition-all"
                >
                    <span
                        class="material-symbols-outlined
                               text-[18px]"
                    >
                        cloud_upload
                    </span>

                    <span>+ Upload New Media</span>
                </button>
            </div>
        </section>

        {{-- STORAGE BAR --}}
        <div
            class="w-full px-space-lg py-2.5
                   bg-surface-container-low
                   flex flex-col md:flex-row
                   items-center justify-between gap-3"
        >
            <div class="flex items-center gap-6 w-full">
                <div
                    class="flex items-center gap-2
                           font-label-sm text-label-sm
                           text-on-surface-variant"
                >
                    <span
                        class="material-symbols-outlined
                               text-[16px] text-primary"
                    >
                        pie_chart
                    </span>

                    <span>Storage Usage:</span>

                    <span class="text-on-surface font-semibold">
                        {{ $formatBytes($totalBytes) }}
                    </span>
                </div>

                <div
                    class="hidden md:flex items-center gap-2
                           font-label-sm text-label-sm text-outline"
                >
                    <span>Storage Driver:</span>

                    <span class="text-on-surface">
                        {{ config('filesystems.media_disk', 'local') }}
                    </span>
                </div>

                <div
                    class="hidden lg:flex items-center gap-2
                           font-label-sm text-label-sm text-outline"
                >
                    <span>Total Assets:</span>

                    <span class="text-on-surface">
                        {{ $totalAssets }}
                    </span>
                </div>
            </div>
        </div>

        {{-- DROPZONE --}}
        <div class="p-space-lg w-full">
            <input
                id="mediaFileInput"
                type="file"
                accept=".jpg,.jpeg,.png,.webp,.gif,.pdf"
                class="hidden"
            >

            <input
                id="replaceMediaFileInput"
                type="file"
                accept=".jpg,.jpeg,.png,.webp,.gif,.pdf"
                class="hidden"
            >

            <div
                id="mediaDropzone"
                class="relative group cursor-pointer
                       bg-surface-container-low
                       hover:bg-surface-container
                       transition-all duration-300
                       rounded-xl p-6 shadow-sm overflow-hidden"
            >
                <div
                    class="absolute -inset-1
                           bg-gradient-to-r
                           from-primary-container/20
                           via-tertiary/10
                           to-transparent
                           blur-md opacity-25
                           group-hover:opacity-60
                           transition duration-500
                           pointer-events-none"
                ></div>

                <div
                    class="relative flex flex-col md:flex-row
                           items-center justify-between
                           gap-6 z-10"
                >
                    <div class="flex items-center gap-4">
                        <div
                            class="w-14 h-14 rounded-lg
                                   bg-surface-container-high
                                   flex items-center justify-center
                                   text-primary
                                   group-hover:scale-105
                                   transition-transform
                                   shrink-0 shadow-inner"
                        >
                            <span
                                class="material-symbols-outlined
                                       text-[32px]"
                            >
                                file_upload
                            </span>
                        </div>

                        <div>
                            <div
                                class="flex items-center
                                       gap-2 flex-wrap"
                            >
                                <span
                                    class="font-headline-sm
                                           text-headline-sm
                                           text-on-surface"
                                >
                                    Kéo thả hình ảnh hoặc tài liệu
                                    kỹ thuật vào đây
                                </span>

                                <span
                                    class="font-label-sm text-label-sm
                                           text-primary
                                           bg-surface-container-high
                                           px-2 py-0.5 rounded"
                                >
                                </span>
                            </div>
                        </div>
                    </div>

                    <div
                        id="uploadStatus"
                        class="hidden w-full md:w-auto
                               bg-surface-container-lowest
                               px-4 py-2.5 rounded-lg
                               items-center gap-3 shrink-0
                               shadow-sm"
                    >
                        <div
                            class="w-8 h-8 rounded
                                   bg-surface-container-high
                                   flex items-center justify-center
                                   text-tertiary shrink-0"
                        >
                            <span
                                class="material-symbols-outlined
                                       text-[18px]"
                            >
                                sync
                            </span>
                        </div>

                        <div class="flex flex-col">
                            <span
                                id="uploadFileName"
                                class="font-label-sm text-label-sm
                                       text-on-surface"
                            ></span>

                            <span
                                id="uploadMessage"
                                class="font-label-sm text-label-sm
                                       text-tertiary"
                            >
                                Uploading...
                            </span>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        {{-- FILTER TOOLBAR --}}
        <div class="px-space-lg pb-4">
            <div
                class="bg-surface-container-low p-3 rounded-lg
                       flex flex-col xl:flex-row
                       items-center justify-between gap-3
                       shadow-sm"
            >
                <div
                    class="flex items-center gap-2
                           bg-surface-container-highest
                           px-3 py-2 rounded
                           w-full xl:w-96
                           text-on-surface-variant"
                >
                    <span
                        class="material-symbols-outlined
                               text-[18px] text-outline"
                    >
                        search
                    </span>

                    <input
                        id="mediaSearch"
                        class="bg-transparent
                               font-body-sm text-body-sm
                               text-on-surface
                               focus:outline-none w-full
                               placeholder:text-outline"
                        placeholder="Tìm kiếm file theo tên..."
                        type="text"
                    >
                </div>

                <div
                    class="flex items-center gap-2
                           w-full xl:w-auto
                           overflow-x-auto"
                >
                    <div
                        class="flex items-center
                               bg-surface-container-highest
                               p-1 rounded
                               font-label-sm text-label-sm"
                    >
                        <button
                            type="button"
                            data-media-filter="all"
                            class="media-filter
                                   px-2.5 py-1 rounded
                                   bg-surface-container
                                   text-primary font-semibold
                                   shadow-sm"
                        >
                            All
                        </button>

                        <button
                            type="button"
                            data-media-filter="image"
                            class="media-filter
                                   px-2.5 py-1 rounded
                                   text-on-surface-variant
                                   hover:text-on-surface"
                        >
                            Images
                        </button>

                        <button
                            type="button"
                            data-media-filter="pdf"
                            class="media-filter
                                   px-2.5 py-1 rounded
                                   text-on-surface-variant
                                   hover:text-on-surface"
                        >
                            PDFs
                        </button>
                    </div>

                    <div
                        class="flex items-center
                               bg-surface-container-highest
                               p-1 rounded shrink-0"
                    >
                        <button
                            type="button"
                            class="p-1 rounded
                                   bg-surface-container
                                   text-primary shadow-sm"
                            title="Grid View"
                        >
                            <span
                                class="material-symbols-outlined
                                       text-[18px]"
                            >
                                grid_view
                            </span>
                        </button>

                        <button
                            type="button"
                            disabled
                            class="p-1 rounded text-outline
                                   opacity-50 cursor-not-allowed"
                            title="List View chưa triển khai"
                        >
                            <span
                                class="material-symbols-outlined
                                       text-[18px]"
                            >
                                view_list
                            </span>
                        </button>
                    </div>
                </div>
            </div>
        </div>

        {{-- MAIN --}}
        <div
            class="px-space-lg pb-12
                   flex flex-col lg:flex-row
                   gap-6 items-start"
        >
            {{-- GRID --}}
            <div
                class="w-full lg:w-[68%] xl:w-[72%]
                       flex flex-col gap-4"
            >
                <div
                    class="flex items-center justify-between
                           text-on-surface-variant
                           font-label-sm text-label-sm"
                >
                    <span>
                        Hiển thị
                        <span id="visibleMediaCount">
                            {{ $totalAssets }}
                        </span>
                        trong tổng số
                        {{ $totalAssets }}
                        tài nguyên
                    </span>

                    <span class="text-outline hidden md:block">
                        Nhấp vào thẻ để mở bảng Inspector chi tiết
                    </span>
                </div>

                @if ($media->isEmpty())
                    <div
                        class="min-h-[320px]
                               border border-dashed
                               border-outline-variant/40
                               rounded-xl
                               bg-surface-container-low
                               flex flex-col items-center
                               justify-center text-center p-8"
                    >
                        <span
                            class="material-symbols-outlined
                                   text-[48px] text-outline mb-3"
                        >
                            perm_media
                        </span>

                        <h3
                            class="font-headline-sm
                                   text-headline-sm
                                   text-on-surface"
                        >
                            Chưa có media
                        </h3>

                        <p
                            class="font-body-sm text-body-sm
                                   text-on-surface-variant mt-1"
                        >
                            Upload hình ảnh hoặc tài liệu đầu tiên
                            để bắt đầu Media Library.
                        </p>
                    </div>
                @else
                    <div
                        id="mediaGrid"
                        class="grid grid-cols-1
                               sm:grid-cols-2
                               xl:grid-cols-3
                               gap-4"
                    >
                        @foreach ($media as $asset)
                            @php
                                $isImage = str_starts_with(
                                    $asset->mime_type,
                                    'image/'
                                );

                                $isPdf = $asset->mime_type ===
                                    'application/pdf';

                                $type = $isImage
                                    ? 'image'
                                    : ($isPdf ? 'pdf' : 'other');

                                $size = $formatBytes(
                                    $asset->size_bytes
                                );

                                $extension = strtoupper(
                                    pathinfo(
                                        $asset->file_name,
                                        PATHINFO_EXTENSION
                                    )
                                );
                            @endphp

                            <div
                                class="media-card group relative
                                       bg-surface-container-low
                                       hover:bg-surface-container
                                       rounded-xl overflow-hidden
                                       shadow-sm transition-all
                                       duration-200 cursor-pointer"
                                data-id="{{ $asset->id }}"
                                data-name="{{ $asset->file_name }}"
                                data-url="{{ $asset->url }}"
                                data-mime="{{ $asset->mime_type }}"
                                data-size="{{ $size }}"
                                data-width="{{ $asset->width }}"
                                data-height="{{ $asset->height }}"
                                data-type="{{ $type }}"
                                data-uploader="{{ $asset->uploader?->name ?? 'Admin' }}"
                                data-created="{{ optional($asset->created_at)->format('d/m/Y H:i') }}"
                            >
                                <div
                                    class="relative aspect-video
                                           w-full
                                           bg-surface-container-lowest
                                           overflow-hidden"
                                >
                                    @if ($isImage)
                                        <img
                                            src="{{ $asset->url }}"
                                            alt="{{ $asset->file_name }}"
                                            class="w-full h-full object-cover
                                                   group-hover:scale-105
                                                   transition-transform
                                                   duration-300"
                                        >
                                    @elseif ($isPdf)
                                        <div
                                            class="w-full h-full
                                                   flex flex-col
                                                   items-center justify-center
                                                   gap-2 text-error"
                                        >
                                            <span
                                                class="material-symbols-outlined
                                                       text-[48px]"
                                            >
                                                picture_as_pdf
                                            </span>

                                            <span
                                                class="font-label-sm
                                                       text-label-sm"
                                            >
                                                PDF DOCUMENT
                                            </span>
                                        </div>
                                    @else
                                        <span
                                            class="material-symbols-outlined
                                                   text-[48px]
                                                   text-outline"
                                        >
                                            draft
                                        </span>
                                    @endif

                                    <div
                                        class="absolute top-2 left-2
                                               flex items-center gap-1.5"
                                    >
                                        <span
                                            class="font-label-sm
                                                   text-label-sm
                                                   font-semibold
                                                   bg-surface-container-lowest/90
                                                   backdrop-blur-sm
                                                   text-primary
                                                   px-2 py-0.5 rounded"
                                        >
                                            {{ $extension }}
                                        </span>
                                    </div>

                                    <div
                                        class="absolute inset-0
                                               bg-surface-container-lowest/70
                                               opacity-0
                                               group-hover:opacity-100
                                               transition-opacity
                                               flex items-center
                                               justify-center gap-2"
                                    >
                                        <button
                                            type="button"
                                            class="copy-markdown-btn
                                                   p-2
                                                   bg-surface-container-highest
                                                   hover:bg-primary-container
                                                   hover:text-on-primary
                                                   text-on-surface rounded"
                                            title="Copy Markdown Snippet"
                                        >
                                            <span
                                                class="material-symbols-outlined
                                                       text-[18px]"
                                            >
                                                code
                                            </span>
                                        </button>

                                        <button
                                            type="button"
                                            class="copy-url-btn
                                                   p-2
                                                   bg-surface-container-highest
                                                   hover:bg-primary-container
                                                   hover:text-on-primary
                                                   text-on-surface rounded"
                                            title="Copy URL"
                                        >
                                            <span
                                                class="material-symbols-outlined
                                                       text-[18px]"
                                            >
                                                link
                                            </span>
                                        </button>

                                        <button
                                            type="button"
                                            class="replace-media-btn
                                                   p-2
                                                   bg-surface-container-highest
                                                   hover:bg-primary-container
                                                   hover:text-on-primary
                                                   text-on-surface rounded"
                                            title="Replace"
                                            data-replace-url="{{ route('admin.media.replace', $asset) }}"
                                            data-file-name="{{ $asset->file_name }}"
                                        >
                                            <span
                                                class="material-symbols-outlined
                                                       text-[18px]"
                                            >
                                                upload_file
                                            </span>
                                        </button>

                                        <button
                                            type="button"
                                            class="rename-media-btn
                                                   p-2
                                                   bg-surface-container-highest
                                                   hover:bg-primary-container
                                                   hover:text-on-primary
                                                   text-on-surface rounded"
                                            title="Rename"
                                            data-rename-url="{{ route('admin.media.rename', $asset) }}"
                                            data-file-name="{{ $asset->file_name }}"
                                        >
                                            <span
                                                class="material-symbols-outlined
                                                       text-[18px]"
                                            >
                                                edit
                                            </span>
                                        </button>

                                        <button
                                            type="button"
                                            class="delete-media-btn
                                                   p-2
                                                   bg-surface-container-highest
                                                   hover:bg-error-container
                                                   hover:text-error
                                                   text-on-surface rounded"
                                            title="Delete"
                                            data-delete-url="{{ route('admin.media.destroy', $asset) }}"
                                            data-file-name="{{ $asset->file_name }}"
                                        >
                                            <span
                                                class="material-symbols-outlined
                                                       text-[18px]"
                                            >
                                                delete
                                            </span>
                                        </button>
                                    </div>
                                </div>

                                <div
                                    class="p-3.5 flex
                                           flex-col gap-1.5"
                                >
                                    <span
                                        class="font-label-md
                                               text-label-md
                                               text-on-surface
                                               font-semibold truncate"
                                    >
                                        {{ $asset->file_name }}
                                    </span>

                                    <div
                                        class="flex items-center
                                               justify-between
                                               font-label-sm
                                               text-label-sm
                                               text-outline"
                                    >
                                        <span>
                                            @if (
                                                $asset->width &&
                                                $asset->height
                                            )
                                                {{ $asset->width }}
                                                ×
                                                {{ $asset->height }}
                                            @else
                                                {{ $extension }}
                                            @endif
                                        </span>

                                        <span>{{ $size }}</span>
                                    </div>

                                    <div
                                        class="pt-2 mt-1
                                               bg-surface-container-highest/20
                                               rounded px-2 py-1
                                               flex items-center gap-1.5"
                                    >
                                        <span
                                            class="material-symbols-outlined
                                                   text-[14px]
                                                   text-primary"
                                        >
                                            upload
                                        </span>

                                        <span
                                            class="font-body-sm
                                                   text-body-sm
                                                   text-on-surface-variant
                                                   truncate"
                                        >
                                            Uploaded by:
                                            {{ $asset->uploader?->name ?? 'Admin' }}
                                        </span>
                                    </div>
                                </div>
                            </div>
                        @endforeach
                    </div>
                @endif
            </div>

            {{-- INSPECTOR --}}
            <aside
                class="w-full lg:w-[32%] xl:w-[28%]
                       bg-surface-container-low
                       rounded-xl p-5 shadow-lg
                       flex flex-col gap-5
                       sticky top-20"
            >
                <div
                    class="flex items-center justify-between
                           border-b
                           border-surface-container-highest/60
                           pb-3"
                >
                    <div class="flex items-center gap-2">
                        <span
                            class="material-symbols-outlined
                                   text-[20px] text-primary"
                        >
                            troubleshoot
                        </span>

                        <h2
                            class="font-headline-sm
                                   text-headline-sm
                                   text-on-surface"
                        >
                            Asset Inspector
                        </h2>
                    </div>

                    <span
                        class="font-label-sm text-label-sm
                               text-tertiary
                               bg-tertiary-container/10
                               px-2 py-0.5 rounded"
                    >
                        {{ $storageLabel }}
                    </span>
                </div>

                <div
                    id="inspectorEmpty"
                    class="py-12 text-center
                           text-on-surface-variant
                           font-body-sm text-body-sm"
                >
                    Chọn một media để xem chi tiết.
                </div>

                <div
                    id="inspectorContent"
                    class="hidden flex-col gap-5"
                >
                    <div
                        id="inspectorPreview"
                        class="relative w-full aspect-video
                               rounded-lg overflow-hidden
                               bg-surface-container-lowest
                               shadow-inner
                               flex items-center justify-center"
                    ></div>

                    <div
                        class="flex flex-col gap-3
                               bg-surface-container-highest/30
                               p-3.5 rounded-lg"
                    >
                        <div
                            class="flex items-center justify-between"
                        >
                            <span
                                class="font-label-sm text-label-sm
                                       text-primary uppercase
                                       tracking-wider font-semibold"
                            >
                                1-Click Insertion Assistant
                            </span>

                            <span
                                class="font-label-sm text-label-sm
                                       text-outline"
                            >
                                Markdown Ready
                            </span>
                        </div>

                        <div class="flex flex-col gap-1">
                            <label
                                class="font-label-sm text-label-sm
                                       text-on-surface-variant"
                            >
                                Embed Markdown Snippet
                            </label>

                            <div
                                class="relative flex items-center"
                            >
                                <input
                                    id="inspectorMarkdown"
                                    readonly
                                    type="text"
                                    class="w-full
                                           bg-surface-container-lowest
                                           font-code-inline
                                           text-primary-fixed-dim
                                           px-2.5 py-1.5 pr-9
                                           rounded focus:outline-none"
                                >

                                <button
                                    id="copyInspectorMarkdown"
                                    type="button"
                                    class="absolute right-1.5 p-1
                                           text-on-surface-variant
                                           hover:text-primary"
                                    title="Copy Markdown"
                                >
                                    <span
                                        class="material-symbols-outlined
                                               text-[16px]"
                                    >
                                        content_copy
                                    </span>
                                </button>
                            </div>
                        </div>

                        <div class="flex flex-col gap-1">
                            <label
                                class="font-label-sm text-label-sm
                                       text-on-surface-variant"
                            >
                                Direct URL
                            </label>

                            <div
                                class="relative flex items-center"
                            >
                                <input
                                    id="inspectorUrl"
                                    readonly
                                    type="text"
                                    class="w-full
                                           bg-surface-container-lowest
                                           font-code-inline
                                           text-on-surface
                                           px-2.5 py-1.5 pr-9
                                           rounded focus:outline-none"
                                >

                                <button
                                    id="copyInspectorUrl"
                                    type="button"
                                    class="absolute right-1.5 p-1
                                           text-on-surface-variant
                                           hover:text-primary"
                                    title="Copy URL"
                                >
                                    <span
                                        class="material-symbols-outlined
                                               text-[16px]"
                                    >
                                        content_copy
                                    </span>
                                </button>
                            </div>
                        </div>

                        <button
                            type="button"
                            disabled
                            class="w-full flex items-center
                                   justify-center gap-1.5
                                   py-2 px-3
                                   bg-primary-container/10
                                   text-outline
                                   font-label-md text-label-md
                                   rounded opacity-60
                                   cursor-not-allowed"
                            title="Sẽ kết nối với UC01/UC02"
                        >
                            <span
                                class="material-symbols-outlined
                                       text-[18px]"
                            >
                                wallpaper
                            </span>

                            <span>Set as Cover Image</span>
                        </button>
                    </div>

                    <div class="flex flex-col gap-3">
                        <span
                            class="font-label-sm text-label-sm
                                   text-outline uppercase
                                   tracking-wider"
                        >
                            Metadata
                        </span>

                        <div>
                            <label
                                class="font-label-sm text-label-sm
                                       text-on-surface-variant"
                            >
                                Tên file
                            </label>

                            <input
                                id="inspectorName"
                                readonly
                                type="text"
                                class="w-full mt-1
                                       bg-surface-container
                                       font-label-sm text-label-sm
                                       text-on-surface
                                       px-3 py-1.5 rounded"
                            >
                        </div>

                        <div
                            class="flex items-center justify-between
                                   font-label-sm text-label-sm"
                        >
                            <span class="text-outline">
                                Dimensions:
                            </span>

                            <span
                                id="inspectorDimensions"
                                class="text-on-surface"
                            ></span>
                        </div>

                        <div
                            class="flex items-center justify-between
                                   font-label-sm text-label-sm"
                        >
                            <span class="text-outline">
                                Size:
                            </span>

                            <span
                                id="inspectorSize"
                                class="text-on-surface"
                            ></span>
                        </div>

                        <div
                            class="flex items-center justify-between
                                   font-label-sm text-label-sm"
                        >
                            <span class="text-outline">
                                MIME:
                            </span>

                            <span
                                id="inspectorMime"
                                class="text-on-surface"
                            ></span>
                        </div>

                        <div
                            class="flex items-center justify-between
                                   font-label-sm text-label-sm"
                        >
                            <span class="text-outline">
                                Uploader:
                            </span>

                            <span
                                id="inspectorUploader"
                                class="text-primary"
                            ></span>
                        </div>

                        <div
                            class="flex items-center justify-between
                                   font-label-sm text-label-sm"
                        >
                            <span class="text-outline">
                                Uploaded:
                            </span>

                            <span
                                id="inspectorCreated"
                                class="text-on-surface"
                            ></span>
                        </div>
                    </div>
                </div>
            </aside>
        </div>
    </div>

    <x-confirm-modal />
    <x-rename-media-modal />

    <script>
        document.addEventListener('DOMContentLoaded', () => {
            const library = document.getElementById('mediaLibrary');

            if (!library) {
                return;
            }

            const storeUrl = library.dataset.storeUrl;
            const csrf = library.dataset.csrf;

            const fileInput =
                document.getElementById('mediaFileInput');

            const replaceMediaFileInput =
                document.getElementById('replaceMediaFileInput');

            let pendingReplaceUrl = null;
            let pendingReplaceFileName = null;

            const renameMediaModal =
                document.getElementById('renameMediaModal');

            const renameMediaInput =
                document.getElementById('renameMediaInput');

            const renameMediaError =
                document.getElementById('renameMediaError');

            const renameMediaCancel =
                document.getElementById('renameMediaCancel');

            const renameMediaSave =
                document.getElementById('renameMediaSave');

            const dropzone =
                document.getElementById('mediaDropzone');

            const openUploadBtn =
                document.getElementById('openUploadBtn');

            const uploadStatus =
                document.getElementById('uploadStatus');

            const uploadFileName =
                document.getElementById('uploadFileName');

            const uploadMessage =
                document.getElementById('uploadMessage');

            const searchInput =
                document.getElementById('mediaSearch');

            const cards = [
                ...document.querySelectorAll('.media-card'),
            ];

            const filterButtons = [
                ...document.querySelectorAll('.media-filter'),
            ];

            const visibleCount =
                document.getElementById('visibleMediaCount');

            let activeFilter = 'all';

            function showRenameMediaModal({
                currentName,
                renameUrl,
            }) {
                return new Promise((resolve) => {
                    if (
                        !renameMediaModal ||
                        !renameMediaInput ||
                        !renameMediaError ||
                        !renameMediaCancel ||
                        !renameMediaSave
                    ) {
                        resolve(null);
                        return;
                    }

                    const extensionIndex =
                        currentName.lastIndexOf('.');

                    const nameWithoutExtension =
                        extensionIndex > 0
                            ? currentName.slice(0, extensionIndex)
                            : currentName;

                    renameMediaInput.value =
                        nameWithoutExtension;

                    renameMediaError.textContent = '';
                    renameMediaError.classList.add('hidden');

                    renameMediaModal.classList.remove('hidden');
                    renameMediaModal.classList.add('flex');
                    renameMediaModal.setAttribute(
                        'aria-hidden',
                        'false'
                    );

                    renameMediaInput.focus();
                    renameMediaInput.select();

                    const closeModal = (result) => {
                        renameMediaModal.classList.add('hidden');
                        renameMediaModal.classList.remove('flex');
                        renameMediaModal.setAttribute(
                            'aria-hidden',
                            'true'
                        );

                        renameMediaCancel.removeEventListener(
                            'click',
                            handleCancel
                        );

                        renameMediaSave.removeEventListener(
                            'click',
                            handleSave
                        );

                        renameMediaModal.removeEventListener(
                            'click',
                            handleBackdrop
                        );

                        document.removeEventListener(
                            'keydown',
                            handleEscape
                        );

                        resolve(result);
                    };

                    const handleCancel = () => {
                        closeModal(null);
                    };

                    const handleSave = () => {
                        const name =
                            renameMediaInput.value.trim();

                        if (!name) {
                            renameMediaError.textContent =
                                'Tên media không được để trống.';

                            renameMediaError.classList.remove(
                                'hidden'
                            );

                            return;
                        }

                        closeModal({
                            name,
                            renameUrl,
                        });
                    };

                    const handleBackdrop = (event) => {
                        if (event.target === renameMediaModal) {
                            closeModal(null);
                        }
                    };

                    const handleEscape = (event) => {
                        if (event.key === 'Escape') {
                            closeModal(null);
                        }
                    };

                    renameMediaCancel.addEventListener(
                        'click',
                        handleCancel
                    );

                    renameMediaSave.addEventListener(
                        'click',
                        handleSave
                    );

                    renameMediaModal.addEventListener(
                        'click',
                        handleBackdrop
                    );

                    document.addEventListener(
                        'keydown',
                        handleEscape
                    );
                });
            }

            function openFilePicker() {
                fileInput?.click();
            }

            openUploadBtn?.addEventListener(
                'click',
                openFilePicker
            );

            dropzone?.addEventListener(
                'click',
                openFilePicker
            );

            dropzone?.addEventListener(
                'dragover',
                (event) => {
                    event.preventDefault();

                    dropzone.classList.add(
                        'ring-2',
                        'ring-primary'
                    );
                }
            );

            dropzone?.addEventListener(
                'dragleave',
                () => {
                    dropzone.classList.remove(
                        'ring-2',
                        'ring-primary'
                    );
                }
            );

            dropzone?.addEventListener(
                'drop',
                (event) => {
                    event.preventDefault();

                    dropzone.classList.remove(
                        'ring-2',
                        'ring-primary'
                    );

                    const file =
                        event.dataTransfer.files?.[0];

                    if (file) {
                        uploadFile(file);
                    }
                }
            );

            fileInput.addEventListener(
                'change',
                () => {
                    const file = fileInput.files?.[0];

                    // Reset input để có thể chọn lại chính file này nếu upload thất bại.
                    fileInput.value = '';

                    if (file) {
                        uploadFile(file);
                    }
                }
            );

            replaceMediaFileInput?.addEventListener(
                'change',
                async () => {
                    const file =
                        replaceMediaFileInput.files?.[0];

                    const replaceUrl =
                        pendingReplaceUrl;

                    const currentFileName =
                        pendingReplaceFileName;

                    replaceMediaFileInput.value = '';

                    pendingReplaceUrl = null;
                    pendingReplaceFileName = null;

                    if (!file || !replaceUrl) {
                        return;
                    }

                    const confirmed =
                        await window.showConfirmModal({
                            title: 'Thay thế media',
                            message:
                                `Bạn có chắc muốn thay "${currentFileName}" bằng "${file.name}" không?`,
                            confirmText: 'Thay thế',
                        });

                    if (!confirmed) {
                        return;
                    }

                    const formData = new FormData();

                    formData.append('file', file);

                    try {
                        const response = await fetch(
                            replaceUrl,
                            {
                                method: 'POST',
                                headers: {
                                    'X-CSRF-TOKEN': csrf,
                                    'Accept': 'application/json',
                                },
                                body: formData,
                            }
                        );

                        const result =
                            await response.json();

                        if (!response.ok) {
                            const validationMessage =
                                result.errors?.file?.[0];

                            await window.showConfirmModal({
                                title:
                                    'Không thể thay thế media',
                                message:
                                    validationMessage ??
                                    result.message ??
                                    'Không thể thay thế media.',
                                confirmText: 'Đóng',
                                showCancel: false,
                            });

                            return;
                        }

                        window.location.reload();
                    } catch (error) {
                        await window.showConfirmModal({
                            title: 'Lỗi',
                            message:
                                'Có lỗi xảy ra khi thay thế media.',
                            confirmText: 'Đóng',
                            showCancel: false,
                        });
                    }
                }
            );

            async function uploadFile(file) {
                const formData = new FormData();

                formData.append('file', file);

                uploadStatus?.classList.remove('hidden');
                uploadStatus?.classList.add('flex');

                if (uploadFileName) {
                    uploadFileName.textContent = file.name;
                }

                if (uploadMessage) {
                    uploadMessage.textContent =
                        'Uploading...';

                    uploadMessage.className =
                        'font-label-sm text-label-sm text-tertiary';
                }

                try {
                    const response = await fetch(
                        storeUrl,
                        {
                            method: 'POST',
                            headers: {
                                'X-CSRF-TOKEN': csrf,
                                'Accept': 'application/json',
                            },
                            body: formData,
                        }
                    );

                    const result =
                        await response.json();

                    if (!response.ok) {
                        const error =
                            result.errors?.file?.[0]
                            ?? result.message
                            ?? 'Upload thất bại.';

                        throw new Error(error);
                    }

                    if (uploadMessage) {
                        uploadMessage.textContent =
                            'Upload thành công.';

                        uploadMessage.className =
                            'font-label-sm text-label-sm text-tertiary';
                    }

                    setTimeout(() => {
                        window.location.reload();
                    }, 600);
                } catch (error) {
                    if (uploadMessage) {
                        uploadMessage.textContent =
                            error.message;

                        uploadMessage.className =
                            'font-label-sm text-label-sm text-error';
                    }
                }
            }

            function applyFilters() {
                const keyword =
                    searchInput?.value
                        .toLocaleLowerCase('vi-VN')
                        .trim() ?? '';

                let count = 0;

                cards.forEach((card) => {
                    const name =
                        card.dataset.name
                            ?.toLocaleLowerCase('vi-VN')
                        ?? '';

                    const type =
                        card.dataset.type ?? '';

                    const matchesSearch =
                        name.includes(keyword);

                    const matchesType =
                        activeFilter === 'all'
                        || activeFilter === type;

                    const visible =
                        matchesSearch && matchesType;

                    card.classList.toggle(
                        'hidden',
                        !visible
                    );

                    if (visible) {
                        count++;
                    }
                });

                if (visibleCount) {
                    visibleCount.textContent = count;
                }
            }

            searchInput?.addEventListener(
                'input',
                applyFilters
            );

            filterButtons.forEach((button) => {
                button.addEventListener(
                    'click',
                    () => {
                        activeFilter =
                            button.dataset.mediaFilter;

                        filterButtons.forEach(
                            (item) => {
                                item.classList.remove(
                                    'bg-surface-container',
                                    'text-primary',
                                    'font-semibold',
                                    'shadow-sm'
                                );

                                item.classList.add(
                                    'text-on-surface-variant'
                                );
                            }
                        );

                        button.classList.remove(
                            'text-on-surface-variant'
                        );

                        button.classList.add(
                            'bg-surface-container',
                            'text-primary',
                            'font-semibold',
                            'shadow-sm'
                        );

                        applyFilters();
                    }
                );
            });

            const inspectorEmpty =
                document.getElementById(
                    'inspectorEmpty'
                );

            const inspectorContent =
                document.getElementById(
                    'inspectorContent'
                );

            const inspectorPreview =
                document.getElementById(
                    'inspectorPreview'
                );

            const inspectorName =
                document.getElementById(
                    'inspectorName'
                );

            const inspectorMime =
                document.getElementById(
                    'inspectorMime'
                );

            const inspectorSize =
                document.getElementById(
                    'inspectorSize'
                );

            const inspectorDimensions =
                document.getElementById(
                    'inspectorDimensions'
                );

            const inspectorUploader =
                document.getElementById(
                    'inspectorUploader'
                );

            const inspectorCreated =
                document.getElementById(
                    'inspectorCreated'
                );

            const inspectorUrl =
                document.getElementById(
                    'inspectorUrl'
                );

            const inspectorMarkdown =
                document.getElementById(
                    'inspectorMarkdown'
                );

            function selectCard(card) {
                cards.forEach((item) => {
                    item.classList.remove(
                        'ring-2',
                        'ring-primary-container'
                    );
                });

                card.classList.add(
                    'ring-2',
                    'ring-primary-container'
                );

                inspectorEmpty?.classList.add(
                    'hidden'
                );

                inspectorContent?.classList.remove(
                    'hidden'
                );

                inspectorContent?.classList.add(
                    'flex'
                );

                const name = card.dataset.name ?? '';
                const url = card.dataset.url ?? '';
                const mime = card.dataset.mime ?? '';
                const size = card.dataset.size ?? '';
                const width =
                    card.dataset.width ?? '';
                const height =
                    card.dataset.height ?? '';

                const uploader =
                    card.dataset.uploader ?? 'Admin';

                const created =
                    card.dataset.created ?? '';

                if (inspectorName) {
                    inspectorName.value = name;
                }

                if (inspectorMime) {
                    inspectorMime.textContent = mime;
                }

                if (inspectorSize) {
                    inspectorSize.textContent = size;
                }

                if (inspectorUploader) {
                    inspectorUploader.textContent =
                        uploader;
                }

                if (inspectorCreated) {
                    inspectorCreated.textContent =
                        created;
                }

                if (inspectorDimensions) {
                    inspectorDimensions.textContent =
                        width && height
                            ? `${width} × ${height}`
                            : '—';
                }

                if (inspectorUrl) {
                    inspectorUrl.value = url;
                }

                if (inspectorMarkdown) {
                    inspectorMarkdown.value =
                        `![${name}](${url})`;
                }

                if (inspectorPreview) {
                    inspectorPreview.innerHTML = '';

                    if (
                        mime.startsWith('image/')
                    ) {
                        const image =
                            document.createElement('img');

                        image.src = url;
                        image.alt = name;

                        image.className =
                            'w-full h-full object-contain';

                        inspectorPreview.appendChild(
                            image
                        );
                    } else {
                        inspectorPreview.innerHTML =
                            '<span class="material-symbols-outlined text-[48px] text-error">picture_as_pdf</span>';
                    }
                }
            }

            cards.forEach((card) => {
                card.addEventListener(
                    'click',
                    () => selectCard(card)
                );

                card.querySelector(
                    '.copy-url-btn'
                )?.addEventListener(
                    'click',
                    async (event) => {
                        event.stopPropagation();

                        await navigator.clipboard.writeText(
                            card.dataset.url ?? ''
                        );
                    }
                );

                card.querySelector(
                    '.copy-markdown-btn'
                )?.addEventListener(
                    'click',
                    async (event) => {
                        event.stopPropagation();

                        const markdown =
                            `![${card.dataset.name ?? ''}](${card.dataset.url ?? ''})`;

                        await navigator.clipboard.writeText(
                            markdown
                        );
                    }
                );

                card.querySelector(
                    '.replace-media-btn'
                )?.addEventListener(
                    'click',
                    (event) => {
                        event.stopPropagation();

                        const button =
                            event.currentTarget;

                        pendingReplaceUrl =
                            button.dataset.replaceUrl ?? null;

                        pendingReplaceFileName =
                            button.dataset.fileName ?? null;

                        if (!pendingReplaceUrl) {
                            return;
                        }

                        replaceMediaFileInput.value = '';
                        replaceMediaFileInput.click();
                    }
                );

                card.querySelector(
                    '.rename-media-btn'
                )?.addEventListener(
                    'click',
                    async (event) => {
                        event.stopPropagation();

                        const button =
                            event.currentTarget;

                        const renameUrl =
                            button.dataset.renameUrl;

                        const currentName =
                            button.dataset.fileName ?? '';

                        if (!renameUrl) {
                            return;
                        }

                        const renameData =
                            await showRenameMediaModal({
                                currentName,
                                renameUrl,
                            });

                        if (!renameData) {
                            return;
                        }

                        button.disabled = true;

                        try {
                            const response = await fetch(
                                renameData.renameUrl,
                                {
                                    method: 'PATCH',
                                    headers: {
                                        'X-CSRF-TOKEN': csrf,
                                        'Accept': 'application/json',
                                        'Content-Type':
                                            'application/json',
                                    },
                                    body: JSON.stringify({
                                        name: renameData.name,
                                    }),
                                }
                            );

                            const result =
                                await response.json();

                            if (!response.ok) {
                                const validationMessage =
                                    result.errors?.name?.[0];

                                await window.showConfirmModal({
                                    title:
                                        'Không thể đổi tên',
                                    message:
                                        validationMessage ??
                                        result.message ??
                                        'Không thể đổi tên media.',
                                    confirmText: 'Đóng',
                                    showCancel: false,
                                });

                                return;
                            }

                            window.location.reload();
                        } catch (error) {
                            await window.showConfirmModal({
                                title: 'Lỗi',
                                message:
                                    'Có lỗi xảy ra khi đổi tên media.',
                                confirmText: 'Đóng',
                                showCancel: false,
                            });
                        } finally {
                            button.disabled = false;
                        }
                    }
                );

                card.querySelector(
                    '.delete-media-btn'
                )?.addEventListener(
                    'click',
                    async (event) => {
                        event.stopPropagation();

                        const button =
                            event.currentTarget;

                        const deleteUrl =
                            button.dataset.deleteUrl;

                        const fileName =
                            button.dataset.fileName ?? '';

                        if (!deleteUrl) {
                            return;
                        }

                        const confirmed =
                            await window.showConfirmModal({
                                title: 'Xóa media',
                                message:
                                    `Bạn có chắc muốn xóa "${fileName}" không?`,
                                confirmText: 'Xóa',
                            });

                        if (!confirmed) {
                            return;
                        }

                        button.disabled = true;

                        try {
                            const response = await fetch(
                                deleteUrl,
                                {
                                    method: 'DELETE',
                                    headers: {
                                        'X-CSRF-TOKEN': csrf,
                                        'Accept': 'application/json',
                                    },
                                }
                            );

                            const result =
                                await response.json();

                            if (!response.ok) {
                                await window.showConfirmModal({
                                    title:
                                        'Không thể xóa media',
                                    message:
                                        result.message ??
                                        'Không thể xóa media.',
                                    confirmText: 'Đóng',
                                    showCancel: false,
                                });

                                return;
                            }

                            window.location.reload();
                        } catch (error) {
                            await window.showConfirmModal({
                                title: 'Lỗi',
                                message:
                                    'Có lỗi xảy ra khi xóa media.',
                                confirmText: 'Đóng',
                                showCancel: false,
                            });
                        } finally {
                            button.disabled = false;
                        }
                    }
                );
            });

            document.getElementById(
                'copyInspectorUrl'
            )?.addEventListener(
                'click',
                async () => {
                    await navigator.clipboard.writeText(
                        inspectorUrl?.value ?? ''
                    );
                }
            );

            document.getElementById(
                'copyInspectorMarkdown'
            )?.addEventListener(
                'click',
                async () => {
                    await navigator.clipboard.writeText(
                        inspectorMarkdown?.value ?? ''
                    );
                }
            );
        });
    </script>
@endsection