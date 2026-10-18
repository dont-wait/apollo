<form method="POST" action="{{ $action }}" class="space-y-6">
    @csrf
    @if ($method !== 'POST')
        @method($method)
    @endif

    @if ($errors->any())
        <div role="alert" class="rounded-lg border border-red-500/30 bg-red-500/10 px-4 py-3 text-sm text-red-200">
            Vui lòng kiểm tra lại các trường được đánh dấu bên dưới.
        </div>
    @endif

    <section class="space-y-5 rounded-xl bg-[#191c21] p-5 shadow-sm sm:p-6">
        <div>
            <label for="name" class="mb-2 block text-sm font-medium">Tên <span class="text-red-300">*</span></label>
            <input id="name" name="name" type="text" value="{{ old('name', $user?->name) }}" required maxlength="255" autocomplete="name" class="w-full rounded-lg border border-[#32353b] bg-[#0b0e13] px-3 py-2.5 text-sm text-[#e1e2ea] placeholder:text-[#87929a] focus:border-sky-400 focus:outline-none">
            @error('name') <p class="mt-1.5 text-xs text-red-300">{{ $message }}</p> @enderror
        </div>

        <div>
            <label for="email" class="mb-2 block text-sm font-medium">Email <span class="text-red-300">*</span></label>
            <input id="email" name="email" type="email" value="{{ old('email', $user?->email) }}" required maxlength="255" autocomplete="email" class="w-full rounded-lg border border-[#32353b] bg-[#0b0e13] px-3 py-2.5 text-sm text-[#e1e2ea] placeholder:text-[#87929a] focus:border-sky-400 focus:outline-none">
            @error('email') <p class="mt-1.5 text-xs text-red-300">{{ $message }}</p> @enderror
        </div>

        <div class="grid gap-5 md:grid-cols-2">
            <div>
                <label for="password" class="mb-2 block text-sm font-medium">
                    Mật khẩu
                    @if (! $user)<span class="text-red-300">*</span>@endif
                </label>
                <input id="password" name="password" type="password" @required(! $user) minlength="8" autocomplete="new-password" class="w-full rounded-lg border border-[#32353b] bg-[#0b0e13] px-3 py-2.5 text-sm text-[#e1e2ea] focus:border-sky-400 focus:outline-none">
                @if ($user)
                    <p class="mt-1.5 text-xs text-[#87929a]">Để trống nếu không muốn đổi mật khẩu.</p>
                @endif
                @error('password') <p class="mt-1.5 text-xs text-red-300">{{ $message }}</p> @enderror
            </div>

            <div>
                <label for="password_confirmation" class="mb-2 block text-sm font-medium">
                    Xác nhận mật khẩu
                    @if (! $user)<span class="text-red-300">*</span>@endif
                </label>
                <input id="password_confirmation" name="password_confirmation" type="password" @required(! $user) minlength="8" autocomplete="new-password" class="w-full rounded-lg border border-[#32353b] bg-[#0b0e13] px-3 py-2.5 text-sm text-[#e1e2ea] focus:border-sky-400 focus:outline-none">
            </div>
        </div>

        <div class="grid gap-5 md:grid-cols-2">
            <div>
                <label for="role" class="mb-2 block text-sm font-medium">Role <span class="text-red-300">*</span></label>
                <select id="role" name="role" required class="w-full rounded-lg border border-[#32353b] bg-[#0b0e13] px-3 py-2.5 text-sm text-[#e1e2ea] focus:border-sky-400 focus:outline-none">
                    @foreach ($roleOptions as $value => $label)
                        <option value="{{ $value }}" @selected(old('role', $user?->role ?? \App\Models\User::ROLE_USER) === $value)>{{ $label }}</option>
                    @endforeach
                </select>
                @error('role') <p class="mt-1.5 text-xs text-red-300">{{ $message }}</p> @enderror
            </div>

            <div>
                <label for="status" class="mb-2 block text-sm font-medium">Trạng thái <span class="text-red-300">*</span></label>
                <select id="status" name="status" required class="w-full rounded-lg border border-[#32353b] bg-[#0b0e13] px-3 py-2.5 text-sm text-[#e1e2ea] focus:border-sky-400 focus:outline-none">
                    @foreach ($statusOptions as $value => $label)
                        <option value="{{ $value }}" @selected(old('status', $user?->status ?? \App\Models\User::STATUS_ACTIVE) === $value)>{{ $label }}</option>
                    @endforeach
                </select>
                @error('status') <p class="mt-1.5 text-xs text-red-300">{{ $message }}</p> @enderror
            </div>
        </div>
    </section>

    <div class="flex flex-col-reverse justify-end gap-3 sm:flex-row">
        <a href="{{ route('admin.users.index') }}" class="rounded-lg px-5 py-2.5 text-center text-sm text-[#bdc8d1] transition hover:bg-[#272a30]">Hủy</a>
        <button type="submit" class="inline-flex items-center justify-center gap-2 rounded-lg bg-sky-500 px-5 py-2.5 text-sm font-semibold text-slate-950 transition hover:bg-sky-400">
            <span class="material-symbols-outlined text-lg">save</span>
            {{ $user ? 'Lưu thay đổi' : 'Tạo user' }}
        </button>
    </div>
</form>
