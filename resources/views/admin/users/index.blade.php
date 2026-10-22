@extends('layouts.admin')

@section('title', 'Users - Apollo Admin')

@section('content')
    <div
        class="-m-8 min-h-full space-y-6 bg-[#111319] p-4 text-[#e1e2ea] sm:p-8"
        x-data="{
            deleteForm: null,
            showDeleteModal: false,
            openDeleteModal(form) {
                this.deleteForm = form;
                this.showDeleteModal = true;
            },
            closeDeleteModal() {
                this.showDeleteModal = false;
                this.deleteForm = null;
            },
            confirmDelete() {
                const form = this.deleteForm;
                this.closeDeleteModal();
                form?.requestSubmit();
            },
        }"
    >
        <header class="flex flex-col justify-between gap-4 sm:flex-row sm:items-end">
            <div>
                <p class="text-[11px] uppercase tracking-[0.16em] text-[#87929a]">Community // Workspace</p>
                <h1 class="mt-2 text-3xl font-semibold tracking-tight">Quản lý User</h1>
                <p class="mt-2 text-sm text-[#bdc8d1]">Quản lý tài khoản, quyền hạn và trạng thái người dùng.</p>
            </div>
            <a href="{{ route('admin.users.create') }}" class="inline-flex items-center justify-center gap-2 rounded-lg bg-sky-500 px-4 py-2 text-sm font-semibold text-slate-950 transition hover:bg-sky-400">
                <span class="material-symbols-outlined text-lg">person_add</span>
                Thêm user
            </a>
        </header>

        @if (session('status'))
            <div role="status" class="rounded-lg border border-emerald-500/30 bg-emerald-500/10 px-4 py-3 text-sm text-emerald-300">
                {{ session('status') }}
            </div>
        @endif

        <section class="rounded-xl bg-[#191c21] p-4 shadow-sm">
            <form method="GET" action="{{ route('admin.users.index') }}" class="grid gap-3 sm:grid-cols-2 lg:grid-cols-[minmax(12rem,1fr)_minmax(10rem,0.7fr)_minmax(10rem,0.7fr)_auto_auto]">
                <label class="sr-only" for="user-search">Tìm user</label>
                <input id="user-search" name="q" type="search" value="{{ $filters['q'] ?? '' }}" placeholder="Tìm theo tên hoặc email..." class="min-w-0 rounded-lg border border-[#32353b] bg-[#0b0e13] px-3 py-2 text-sm text-[#e1e2ea] placeholder:text-[#87929a] focus:border-sky-400 focus:outline-none">
                <label class="sr-only" for="role-filter">Lọc theo role</label>
                <select id="role-filter" name="role" class="rounded-lg border border-[#32353b] bg-[#0b0e13] px-3 py-2 text-sm text-[#e1e2ea] focus:border-sky-400 focus:outline-none">
                    <option value="">Tất cả role</option>
                    @foreach ($roleOptions as $value => $label)
                        <option value="{{ $value }}" @selected(($filters['role'] ?? '') === $value)>{{ $label }}</option>
                    @endforeach
                </select>
                <label class="sr-only" for="status-filter">Lọc theo trạng thái</label>
                <select id="status-filter" name="status" class="rounded-lg border border-[#32353b] bg-[#0b0e13] px-3 py-2 text-sm text-[#e1e2ea] focus:border-sky-400 focus:outline-none">
                    <option value="">Tất cả trạng thái</option>
                    @foreach ($statusOptions as $value => $label)
                        <option value="{{ $value }}" @selected(($filters['status'] ?? '') === $value)>{{ $label }}</option>
                    @endforeach
                </select>
                <button type="submit" class="rounded-lg bg-[#272a30] px-4 py-2 text-sm font-medium text-[#e1e2ea] transition hover:bg-[#32353b]">Lọc</button>
                <a href="{{ route('admin.users.index') }}" class="rounded-lg px-4 py-2 text-center text-sm text-[#bdc8d1] transition hover:bg-[#272a30]">Xóa lọc</a>
            </form>
        </section>

        <section class="overflow-hidden rounded-xl bg-[#191c21] shadow-sm">
            @if ($users->isEmpty())
                <div class="px-6 py-14 text-center">
                    <span class="material-symbols-outlined text-4xl text-sky-300">group</span>
                    <h2 class="mt-3 font-semibold">Không tìm thấy user phù hợp</h2>
                    <p class="mt-1 text-sm text-[#bdc8d1]">Tạo user mới hoặc thay đổi bộ lọc.</p>
                </div>
            @else
                <div class="overflow-x-auto">
                    <table class="w-full min-w-[720px] text-left text-sm">
                        <thead class="border-b border-[#32353b] bg-[#0b0e13] text-[10px] uppercase tracking-wider text-[#87929a]">
                            <tr>
                                <th class="px-5 py-3 font-medium">User</th>
                                <th class="px-4 py-3 font-medium">Role</th>
                                <th class="px-4 py-3 font-medium">Trạng thái</th>
                                <th class="px-4 py-3 font-medium">Ngày tạo</th>
                                <th class="px-5 py-3 text-right font-medium">Thao tác</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-[#32353b]/70">
                            @foreach ($users as $user)
                                <tr class="transition-colors hover:bg-[#1d2025]">
                                    <td class="px-5 py-4">
                                        <p class="font-semibold text-[#e1e2ea]">{{ $user->name }}</p>
                                        <p class="mt-1 text-xs text-[#87929a]">{{ $user->email }}</p>
                                    </td>
                                    <td class="px-4 py-4">
                                        <span @class([
                                            'inline-flex rounded-full px-2.5 py-1 text-xs font-medium',
                                            'bg-violet-500/10 text-violet-300' => $user->role === \App\Models\User::ROLE_ADMIN,
                                            'bg-sky-500/10 text-sky-300' => $user->role === \App\Models\User::ROLE_USER,
                                        ])>{{ $roleOptions[$user->role] ?? $user->role }}</span>
                                    </td>
                                    <td class="px-4 py-4">
                                        <span @class([
                                            'inline-flex rounded-full px-2.5 py-1 text-xs font-medium',
                                            'bg-emerald-500/10 text-emerald-300' => $user->status === \App\Models\User::STATUS_ACTIVE,
                                            'bg-amber-500/10 text-amber-300' => $user->status === \App\Models\User::STATUS_INACTIVE,
                                        ])>{{ $statusOptions[$user->status] ?? $user->status }}</span>
                                    </td>
                                    <td class="whitespace-nowrap px-4 py-4 text-xs text-[#bdc8d1]">{{ $user->created_at->format('d/m/Y H:i') }}</td>
                                    <td class="px-5 py-4">
                                        <div class="flex justify-end gap-2">
                                            <a href="{{ route('admin.users.edit', $user) }}" class="rounded-md p-2 text-[#bdc8d1] transition hover:bg-[#272a30] hover:text-sky-300" aria-label="Sửa {{ $user->name }}" title="Sửa">
                                                <span class="material-symbols-outlined text-lg">edit</span>
                                            </a>
                                            @unless (auth()->user()->is($user))
                                                <form method="POST" action="{{ route('admin.users.destroy', $user) }}">
                                                    @csrf
                                                    @method('DELETE')
                                                    <button type="button" class="rounded-md p-2 text-[#bdc8d1] transition hover:bg-red-500/10 hover:text-red-300" aria-label="Xóa {{ $user->name }}" title="Xóa" x-on:click="openDeleteModal($event.currentTarget.closest('form'))">
                                                        <span class="material-symbols-outlined text-lg">delete</span>
                                                    </button>
                                                </form>
                                            @endunless
                                        </div>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
                <div class="border-t border-[#32353b] px-5 py-4">
                    {{ $users->links() }}
                </div>
            @endif
        </section>

        <x-confirm-modal
            :initially-hidden="false"
            title="Xác nhận xóa user"
            message="Bạn có chắc chắn muốn xóa user này không?"
            cancel-text="Hủy"
            confirm-text="Xóa user"
            x-cloak
            x-show="showDeleteModal"
            x-bind:aria-hidden="showDeleteModal ? 'false' : 'true'"
            x-on:click.self="closeDeleteModal()"
            x-on:keydown.escape.window="closeDeleteModal()"
            x-on:confirm-modal-cancel="closeDeleteModal()"
            x-on:confirm-modal-confirm="confirmDelete()"
        />
    </div>
@endsection
