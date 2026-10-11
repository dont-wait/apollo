@extends('layouts.app')

@section('title', '403 - Không có quyền truy cập')

@section('content')
    <section class="mx-auto flex max-w-xl flex-col items-center gap-6 rounded-lg border border-outline-variant/50 bg-surface-low px-6 py-12 text-center sm:px-10">
        <span class="font-mono text-6xl font-semibold text-primary">403</span>
        <div class="flex flex-col gap-3">
            <h1 class="font-display text-2xl font-semibold text-on-surface">Không có quyền truy cập</h1>
            <p class="text-sm leading-6 text-on-surface-variant">Bạn không có quyền truy cập chức năng này.</p>
        </div>
        <div class="flex flex-wrap justify-center gap-3">
            <a href="{{ route('home') }}" class="rounded-md bg-primary px-4 py-2 text-sm font-semibold text-on-primary">Về trang chủ</a>
            @auth
                <form method="POST" action="{{ route('logout') }}">
                    @csrf
                    <button type="submit" class="rounded-md border border-outline-variant px-4 py-2 text-sm text-on-surface-variant">Đăng xuất</button>
                </form>
            @endauth
        </div>
    </section>
@endsection
