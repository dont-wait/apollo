<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

class HomePageTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_home_page_renders_without_exposing_users(): void
    {
        $user = User::factory()->create([
            'name' => 'Ada Lovelace',
            'email' => 'ada@example.com',
        ]);

        $response = $this->get(route('home'));

        $response->assertOk()
            ->assertViewIs('home.index')
            ->assertSeeText('Engineering notes for the systems behind AI.')
            ->assertSeeText('AI Weekly: What changed in models, agents, and inference this week')
            ->assertDontSeeText($user->name)
            ->assertDontSeeText($user->email);
    }
}
