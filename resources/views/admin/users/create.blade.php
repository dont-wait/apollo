@extends('layouts.admin')

@section('title', 'Create User - Apollo Admin')

@section('content')
    <div class="-m-8 min-h-full space-y-6 bg-background p-4 text-on-surface sm:p-8">
        <div class="mx-auto w-full max-w-3xl space-y-6">
            <header class="flex flex-col justify-between gap-4 sm:flex-row sm:items-end">
                <div>
                    <p class="text-[11px] uppercase tracking-[0.16em] text-outline">Community // Workspace</p>
                    <h1 class="mt-2 text-3xl font-semibold tracking-tight">Tạo user</h1>
                    <p class="mt-2 text-sm text-on-surface-variant">Thêm tài khoản mới vào hệ thống.</p>
                </div>
                <a href="{{ route('admin.users.index') }}" class="rounded-lg bg-surface-high px-4 py-2 text-sm text-on-surface transition hover:bg-surface-highest">Quay lại danh sách</a>
            </header>

            @include('admin.users._form', [
                'user' => $user,
                'roleOptions' => $roleOptions,
                'statusOptions' => $statusOptions,
                'action' => route('admin.users.store'),
                'method' => 'POST',
            ])
        </div>
    </div>
@endsection
