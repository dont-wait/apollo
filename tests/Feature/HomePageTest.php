<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Pagination\LengthAwarePaginator;
use Tests\TestCase;

class HomePageTest extends TestCase
{
    use DatabaseTransactions;

    public function test_home_page_renders_users_from_the_database(): void
    {
        $user = User::factory()->create([
            'name' => 'Ada Lovelace',
            'email' => 'ada@example.com',
        ]);

        $response = $this->get(route('home'));

        $response->assertOk()
            ->assertViewIs('home.index')
            ->assertViewHas('users', function (LengthAwarePaginator $users) use ($user): bool {
                return $users->contains($user);
            })
            ->assertSeeText('Ada Lovelace')
            ->assertSeeText('ada@example.com');
    }
}
