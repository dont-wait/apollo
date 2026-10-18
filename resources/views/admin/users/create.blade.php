@extends('layouts.admin')

@section('title', 'Create User - Apollo Admin')

@section('content')
    <div class="-m-8 min-h-full space-y-6 bg-[#111319] p-4 text-[#e1e2ea] sm:p-8">
        <header class="flex flex-col justify-between gap-4 sm:flex-row sm:items-end">
            <div>
                <p class="text-[11px] uppercase tracking-[0.16em] text-[#87929a]">Community // Workspace</p>
                <h1 class="mt-2 text-3xl font-semibold tracking-tight">Tạo user</h1>
                <p class="mt-2 text-sm text-[#bdc8d1]">Thêm tài khoản mới vào hệ thống.</p>
            </div>
            <a href="{{ route('admin.users.index') }}" class="rounded-lg bg-[#272a30] px-4 py-2 text-sm text-[#e1e2ea] transition hover:bg-[#32353b]">Quay lại danh sách</a>
        </header>

        @include('admin.users._form', [
            'user' => $user,
            'roleOptions' => $roleOptions,
            'statusOptions' => $statusOptions,
            'action' => route('admin.users.store'),
            'method' => 'POST',
        ])
    </div>
@endsection
