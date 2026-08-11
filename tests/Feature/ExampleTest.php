<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class ExampleTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_root_url_sends_visitors_into_the_app()
    {
        $this->get(route('home'))->assertRedirect(route('dashboard'));
    }

    public function test_a_guest_at_the_root_url_ends_up_at_the_login_screen()
    {
        $this->followingRedirects()
            ->get(route('home'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->component('auth/Login'));
    }
}
