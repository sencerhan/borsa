<?php

namespace Tests\Feature\Exchange;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Concerns\ExchangeTestHelpers;
use Tests\TestCase;

class LocalizationTest extends TestCase
{
    use ExchangeTestHelpers, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
    }

    public function test_locale_follows_browser_language_english(): void
    {
        $this->makeMarket();
        $user = User::factory()->create();

        $this->actingAs($user)
            ->withHeader('Accept-Language', 'en-US,en;q=0.9')
            ->get('/dashboard')
            ->assertInertia(fn (Assert $page) => $page->where('locale', 'en'));
    }

    public function test_locale_follows_browser_language_turkish(): void
    {
        $this->makeMarket();
        $user = User::factory()->create();

        $this->actingAs($user)
            ->withHeader('Accept-Language', 'tr-TR,tr;q=0.9,en;q=0.8')
            ->get('/dashboard')
            ->assertInertia(fn (Assert $page) => $page->where('locale', 'tr'));
    }

    public function test_session_locale_overrides_browser(): void
    {
        $this->makeMarket();
        $user = User::factory()->create();

        $this->actingAs($user)->post('/locale', ['locale' => 'en']);

        $this->actingAs($user)
            ->withHeader('Accept-Language', 'tr-TR,tr;q=0.9')
            ->get('/dashboard')
            ->assertInertia(fn (Assert $page) => $page->where('locale', 'en'));
    }

    public function test_translations_are_shared(): void
    {
        $this->makeMarket();
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get('/dashboard')
            ->assertInertia(fn (Assert $page) => $page->has('translations.exchange'));
    }
}
