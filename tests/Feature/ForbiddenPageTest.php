<?php

namespace Tests\Feature;

use App\Models\User;
use Tests\TestCase;

class ForbiddenPageTest extends TestCase
{
    public function test_non_admin_sees_forbidden_page_when_accessing_admin_articles(): void
    {
        $user = User::factory()->make(['id' => 1, 'role' => 'USER']);

        $this->actingAs($user)
            ->get(route('admin.articles.index'))
            ->assertForbidden()
            ->assertSeeText('Không có quyền truy cập')
            ->assertSeeText('Khu vực quản trị chỉ dành cho tài khoản có vai trò admin.')
            ->assertSee(route('home'))
            ->assertSeeText('Đăng xuất');
    }

    public function test_non_admin_json_request_receives_forbidden_response(): void
    {
        $user = User::factory()->make(['id' => 1, 'role' => 'USER']);

        $this->actingAs($user)
            ->getJson(route('admin.articles.index'))
            ->assertForbidden()
            ->assertJsonPath('message', 'Bạn không có quyền truy cập chức năng này.');
    }

    public function test_guest_is_redirected_to_login_when_accessing_admin_articles(): void
    {
        $this->get(route('admin.articles.index'))
            ->assertRedirect(route('login'));
    }
}
