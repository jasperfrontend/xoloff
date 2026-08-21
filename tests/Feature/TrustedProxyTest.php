<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\URL;
use Tests\TestCase;

/**
 * kamal-proxy terminates TLS and forwards to the container over plain HTTP.
 *
 * Without trusted proxies Laravel sees an http request and builds http:// URLs
 * into an https page, so the browser blocks every stylesheet and script as
 * mixed content. The failure is nasty because the route still answers 200:
 * the server looks healthy and the application is blank.
 */
class TrustedProxyTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_believes_the_proxy_about_https()
    {
        $this->get('/login', ['X-Forwarded-Proto' => 'https'])->assertOk();

        $this->assertSame('https', request()->getScheme());
        $this->assertTrue(request()->isSecure());
    }

    /**
     * The asset and route helpers are what actually reach the page, so they
     * are what this has to pin rather than the request object alone.
     */
    public function test_generated_urls_keep_the_scheme_the_page_was_served_over()
    {
        $this->get('/login', ['X-Forwarded-Proto' => 'https'])->assertOk();

        $this->assertStringStartsWith('https://', URL::to('/'));
        $this->assertStringStartsWith('https://', route('login'));
        $this->assertStringStartsWith('https://', asset('build/app.css'));
    }

    /**
     * Locally there is no proxy and no TLS, so nothing should be pretending
     * otherwise.
     */
    public function test_it_does_not_invent_https_when_nothing_claims_it()
    {
        $this->get('/login')->assertOk();

        $this->assertFalse(request()->isSecure());
    }
}
