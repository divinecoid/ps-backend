<?php

namespace Tests\Feature;

use App\Http\Middleware\RoleMiddleware;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Tests\TestCase;

class PublicRouteProtectionTest extends TestCase
{
    private const ALIASES = ['shopee', 'shopee_sandbox', 'tiktok-shop', 'tiktok_shop', 'lazada'];

    protected function setUp(): void
    {
        parent::setUp();
        Http::preventStrayRequests();
    }

    private function createStore(string $alias): string
    {
        Schema::create('mdx_marketplaces', function ($t) {
            $t->uuid('id')->primary();
            $t->string('name');
            $t->string('alias')->nullable();
            $t->string('base_api_url')->nullable();
            $t->softDeletes();
        });
        Schema::create('mdx_online_stores', function ($t) {
            $t->uuid('id')->primary();
            $t->uuid('marketplace_id');
            foreach (['store_name', 'api_key', 'client_id', 'client_secret', 'redirect_uri'] as $column) {
                $t->string($column)->nullable();
            }
            $t->softDeletes();
        });

        $marketplaceId = (string) Str::uuid();
        $storeId = (string) Str::uuid();
        \DB::table('mdx_marketplaces')->insert(['id' => $marketplaceId, 'name' => $alias, 'alias' => $alias, 'base_api_url' => 'https://partner.example.test']);
        \DB::table('mdx_online_stores')->insert(['id' => $storeId, 'marketplace_id' => $marketplaceId, 'store_name' => 'A', 'client_id' => '123', 'client_secret' => 'test-only', 'redirect_uri' => 'https://app.example.test/callback']);

        return $storeId;
    }

    public function test_login_links_need_a_signature(): void
    {
        foreach (self::ALIASES as $alias) {
            $this->get("/{$alias}/login/" . Str::uuid())->assertForbidden();
        }
    }

    public function test_connect_url_requires_a_logged_in_admin(): void
    {
        $this->postJson('/api/marketplace-auth/connect-url', ['online_store_id' => (string) Str::uuid()])
            ->assertUnauthorized();
    }

    public function test_signed_link_from_the_api_opens_the_login_until_it_expires(): void
    {
        $storeId = $this->createStore('Shopee');
        $this->withoutMiddleware(RoleMiddleware::class);

        $path = $this->postJson('/api/marketplace-auth/connect-url', ['online_store_id' => $storeId])
            ->assertOk()
            ->json('data.path');

        $this->assertStringStartsWith("/shopee/login/{$storeId}?", $path);
        $this->assertStringStartsWith('https://partner.example.test/api/v2/shop/auth_partner', $this->get($path)->assertRedirect()->headers->get('Location'));
        // The signature covers only the path, so a different public host or scheme is fine
        $this->get('https://other-host.example.test' . $path)->assertRedirect();
        $this->get(str_replace($storeId, (string) Str::uuid(), $path))->assertForbidden();

        $this->travel(6)->minutes();
        $this->get($path)->assertForbidden();
    }

    public function test_unsupported_marketplace_gets_a_clear_error(): void
    {
        $storeId = $this->createStore('tokopedia');

        $this->withoutMiddleware(RoleMiddleware::class)
            ->postJson('/api/marketplace-auth/connect-url', ['online_store_id' => $storeId])
            ->assertStatus(400);
    }

    public function test_public_token_refresh_routes_are_gone(): void
    {
        $refreshRoutes = collect(Route::getRoutes()->getRoutes())
            ->map->uri()
            ->filter(fn (string $uri) => str_contains($uri, '/refresh/'));

        $this->assertSame([], $refreshRoutes->values()->all());
    }

    public function test_manual_shopee_fetch_requires_login(): void
    {
        $this->getJson('/api/shopee/fetch-orders')->assertUnauthorized();
    }

    public function test_product_seeder_needs_the_operator_login(): void
    {
        config(['logviewer.password' => null]);
        $this->get('/seed-product')->assertNotFound();
        $this->post('/seed-product')->assertNotFound();

        config(['logviewer.password' => 'test-only-password']);
        $this->get('/seed-product')->assertUnauthorized();
        $this->post('/seed-product')->assertUnauthorized();
    }

    public function test_marketplace_callbacks_stay_public(): void
    {
        $this->get('/shopee/callback')->assertStatus(400);
        $this->getJson('/api/shopee/callback')->assertStatus(400);
    }
}
