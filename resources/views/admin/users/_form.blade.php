<form method="POST" action="{{ $action }}" class="mx-auto w-full max-w-3xl space-y-6">
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
            <div x-data="{ showPassword: false }">
                <label for="password" class="mb-2 block text-sm font-medium">
                    Mật khẩu
                    @if (! $user)<span class="text-red-300">*</span>@endif
                </label>
                <div class="relative">
                    <input id="password" name="password" type="password" :type="showPassword ? 'text' : 'password'" @required(! $user) minlength="8" autocomplete="new-password" class="w-full rounded-lg border border-[#32353b] bg-[#0b0e13] px-3 py-2.5 pr-11 text-sm text-[#e1e2ea] focus:border-sky-400 focus:outline-none">
                    <button type="button" class="absolute inset-y-0 right-2 flex items-center rounded px-2 text-[#87929a] transition hover:text-[#e1e2ea]" @click="showPassword = !showPassword" :aria-label="showPassword ? 'Ẩn mật khẩu' : 'Hiện mật khẩu'">
                        <svg class="size-5" x-show="!showPassword" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true">
                            <path d="M2.5 12s3.5-5 9.5-5 9.5 5 9.5 5-3.5 5-9.5 5-9.5-5-9.5-5Z"></path>
                            <circle cx="12" cy="12" r="2.5"></circle>
                        </svg>
                        <svg class="size-5" x-cloak x-show="showPassword" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true">
                            <path d="m3 3 18 18"></path>
                            <path d="M10.6 6.2A10.7 10.7 0 0 1 12 6c6 0 9.5 6 9.5 6a17.4 17.4 0 0 1-3.2 3.7M6.2 6.3C3.8 8 2.5 12 2.5 12s3.5 6 9.5 6c1.5 0 2.8-.4 4-.9"></path>
                        </svg>
                    </button>
                </div>
                @if ($user)
                    <p class="mt-1.5 text-xs text-[#87929a]">Để trống nếu không muốn đổi mật khẩu.</p>
                @endif
                @error('password') <p class="mt-1.5 text-xs text-red-300">{{ $message }}</p> @enderror
            </div>

            <div x-data="{ showPasswordConfirmation: false }">
                <label for="password_confirmation" class="mb-2 block text-sm font-medium">
                    Xác nhận mật khẩu
                    @if (! $user)<span class="text-red-300">*</span>@endif
                </label>
                <div class="relative">
                    <input id="password_confirmation" name="password_confirmation" type="password" :type="showPasswordConfirmation ? 'text' : 'password'" @required(! $user) minlength="8" autocomplete="new-password" class="w-full rounded-lg border border-[#32353b] bg-[#0b0e13] px-3 py-2.5 pr-11 text-sm text-[#e1e2ea] focus:border-sky-400 focus:outline-none">
                    <button type="button" class="absolute inset-y-0 right-2 flex items-center rounded px-2 text-[#87929a] transition hover:text-[#e1e2ea]" @click="showPasswordConfirmation = !showPasswordConfirmation" :aria-label="showPasswordConfirmation ? 'Ẩn mật khẩu' : 'Hiện mật khẩu'">
                        <svg class="size-5" x-show="!showPasswordConfirmation" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true">
                            <path d="M2.5 12s3.5-5 9.5-5 9.5 5 9.5 5-3.5 5-9.5 5-9.5-5-9.5-5Z"></path>
                            <circle cx="12" cy="12" r="2.5"></circle>
                        </svg>
                        <svg class="size-5" x-cloak x-show="showPasswordConfirmation" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true">
                            <path d="m3 3 18 18"></path>
                            <path d="M10.6 6.2A10.7 10.7 0 0 1 12 6c6 0 9.5 6 9.5 6a17.4 17.4 0 0 1-3.2 3.7M6.2 6.3C3.8 8 2.5 12 2.5 12s3.5 6 9.5 6c1.5 0 2.8-.4 4-.9"></path>
                        </svg>
                    </button>
                </div>
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
