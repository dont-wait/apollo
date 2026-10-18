<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreUserRequest;
use App\Http\Requests\Admin\UpdateUserRequest;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class UserController extends Controller
{
    public function index(Request $request): View
    {
        $filters = $request->validate([
            'q' => ['nullable', 'string', 'max:255'],
            'role' => ['nullable', Rule::in(User::ROLES)],
            'status' => ['nullable', Rule::in(User::STATUSES)],
        ]);

        $users = User::query()
            ->when($filters['q'] ?? null, function (Builder $query, string $search): void {
                $query->where(function (Builder $query) use ($search): void {
                    $query->where('name', 'like', "%{$search}%")
                        ->orWhere('email', 'like', "%{$search}%");
                });
            })
            ->when($filters['role'] ?? null, function (Builder $query, string $role): void {
                $query->where('role', $role);
            })
            ->when($filters['status'] ?? null, function (Builder $query, string $status): void {
                $query->where('status', $status);
            })
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->paginate(10)
            ->withQueryString();

        return view('admin.users.index', [
            'users' => $users,
            'filters' => $filters,
            'roleOptions' => [
                User::ROLE_ADMIN => 'Admin',
                User::ROLE_USER => 'User',
            ],
            'statusOptions' => [
                User::STATUS_ACTIVE => 'Hoạt động',
                User::STATUS_INACTIVE => 'Không hoạt động',
            ],
        ]);
    }

    public function create(): View
    {
        return view('admin.users.create', [
            'user' => null,
            'roleOptions' => [
                User::ROLE_ADMIN => 'Admin',
                User::ROLE_USER => 'User',
            ],
            'statusOptions' => [
                User::STATUS_ACTIVE => 'Hoạt động',
                User::STATUS_INACTIVE => 'Không hoạt động',
            ],
        ]);
    }

    public function store(StoreUserRequest $request): RedirectResponse
    {
        User::create($request->validated());

        return redirect()
            ->route('admin.users.index')
            ->with('status', 'Tài khoản đã được tạo thành công.');
    }

    public function edit(Request $request, User $user): View
    {
        $roleOptions = [
            User::ROLE_ADMIN => 'Admin',
            User::ROLE_USER => 'User',
        ];

        if ($request->user()?->is($user)) {
            $roleOptions = [User::ROLE_ADMIN => 'Admin'];
        }

        return view('admin.users.edit', [
            'user' => $user,
            'roleOptions' => $roleOptions,
            'statusOptions' => [
                User::STATUS_ACTIVE => 'Hoạt động',
                User::STATUS_INACTIVE => 'Không hoạt động',
            ],
        ]);
    }

    public function update(UpdateUserRequest $request, User $user): RedirectResponse
    {
        $data = $request->validated();

        if ($request->user()?->is($user) && $data['role'] !== User::ROLE_ADMIN) {
            abort(403, 'Bạn không thể tự hạ quyền quản trị viên của mình.');
        }

        if (blank($data['password'] ?? null)) {
            unset($data['password']);
        }

        $user->update($data);

        return redirect()
            ->route('admin.users.index')
            ->with('status', 'Tài khoản đã được cập nhật.');
    }

    public function destroy(Request $request, User $user): RedirectResponse
    {
        abort_if(
            $request->user()?->is($user),
            403,
            'Bạn không thể xóa tài khoản quản trị viên đang đăng nhập.',
        );

        $user->delete();

        return redirect()
            ->route('admin.users.index')
            ->with('status', 'Tài khoản đã được chuyển vào thùng rác.');
    }
}
