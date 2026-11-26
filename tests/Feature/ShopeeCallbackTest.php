<?php

namespace Tests\Feature;

use App\Models\MasterData\OnlineStore;
use App\Services\ShopeeService;
use Illuminate\Http\Client\Request;
use Illuminate\Log\Events\MessageLogged;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Tests\TestCase;

class ShopeeCallbackTest extends TestCase
{
    private OnlineStore $store;
    private OnlineStore $other;

    protected function setUp(): void
    {
        parent::setUp();

        Schema::create('mdx_marketplaces', function ($t) {
            $t->uuid('id')->primary();
            $t->string('base_api_url');
            $t->softDeletes();
        });
        Schema::create('mdx_online_stores', function ($t) {
            $t->uuid('id')->primary();
            $t->uuid('marketplace_id');
            foreach (['store_name', 'shop_id', 'client_id', 'client_secret', 'redirect_uri', 'auth_code', 'access_token', 'refresh_token'] as $column) {
                $t->string($column)->nullable();
            }
            $t->timestamp('access_token_expires_at')->nullable();
            $t->timestamp('refresh_token_expires_at')->nullable();
            $t->timestamps();
            $t->softDeletes();
        });

        $marketplaceId = (string) Str::uuid();
        \DB::table('mdx_marketplaces')->insert(['id' => $marketplaceId, 'base_api_url' => 'https://partner.example.test']);

        $attributes = [
            'marketplace_id' => $marketplaceId,
            'client_id' => '123',
            'client_secret' => 'test-only',
            'redirect_uri' => 'https://app.example.test/shopee/callback',
            'access_token' => 'access-old',
        ];
        // Two stores of one app share the Partner ID
        $this->other = OnlineStore::create($attributes + ['store_name' => 'Other', 'shop_id' => '111']);
        $this->store = OnlineStore::create($attributes + ['store_name' => 'Mine', 'shop_id' => '222']);

        Http::preventStrayRequests();
    }

    private function stateFromAuthUrl(): string
    {
        $service = app(ShopeeService::class);
        $service->setStore($this->store);

        parse_str(parse_url($service->generateAuthUrl(), PHP_URL_QUERY), $query);
        parse_str(parse_url($query['redirect'], PHP_URL_QUERY), $redirect);

        return $redirect['state'];
    }

    private function hitCallback(string $state, string $path = '/shopee/callback')
    {
        return $this->get($path . '?' . http_build_query(['state' => $state, 'code' => 'the-code', 'shop_id' => '999']));
    }

    private function assertNothingChanged(): void
    {
        foreach ([[$this->store, '222'], [$this->other, '111']] as [$store, $shopId]) {
            $this->assertSame($shopId, $store->fresh()->shop_id);
            $this->assertNull($store->fresh()->auth_code);
            $this->assertSame('access-old', $store->fresh()->access_token);
        }
    }

    private function fakeAcceptedCode(): void
    {
        Http::fake(['partner.example.test/api/v2/auth/token/get*' => Http::response([
            'error' => '', 'access_token' => 'access-new', 'refresh_token' => 'refresh-new', 'expire_in' => 14400,
        ])]);
    }

    public function test_valid_authorization_updates_only_the_store_that_started_it(): void
    {
        $this->fakeAcceptedCode();

        $this->hitCallback($this->stateFromAuthUrl())->assertOk()->assertSee('Shopee Auth Success');

        $this->assertSame('999', $this->store->fresh()->shop_id);
        $this->assertSame('the-code', $this->store->fresh()->auth_code);
        $this->assertSame('access-new', $this->store->fresh()->access_token);
        $this->assertSame('111', $this->other->fresh()->shop_id);
        $this->assertSame('access-old', $this->other->fresh()->access_token);
        Http::assertSent(fn (Request $r) => $r['shop_id'] === 999 && $r['code'] === 'the-code');
    }

    public function test_token_exchange_never_hands_tokens_to_the_logger(): void
    {
        $this->fakeAcceptedCode();
        $logged = [];
        Event::listen(MessageLogged::class, function (MessageLogged $event) use (&$logged) {
            $logged[] = $event->message . json_encode($event->context);
        });

        $this->hitCallback($this->stateFromAuthUrl())->assertOk();

        $text = implode("\n", $logged);
        $this->assertNotEmpty($logged);
        // Checked before any masking: the values must not be passed to the logger at all
        foreach (['access-new', 'refresh-new', 'access-old', 'the-code', 'state='] as $secret) {
            $this->assertStringNotContainsString($secret, $text);
        }
    }

    public function test_api_callback_route_works_too(): void
    {
        $this->fakeAcceptedCode();

        $this->hitCallback($this->stateFromAuthUrl(), '/api/shopee/callback')->assertOk();
    }

    public function test_partner_id_or_forged_state_is_rejected_without_calling_shopee(): void
    {
        $valid = $this->stateFromAuthUrl();
        $forOtherStore = str_replace($this->store->id, $this->other->id, $valid);

        foreach (['123', $forOtherStore, $valid . 'x', 'a.b.c'] as $state) {
            $this->hitCallback($state)->assertForbidden();
        }

        Http::assertNothingSent();
        $this->assertNothingChanged();
    }

    public function test_expired_state_is_rejected(): void
    {
        $state = $this->stateFromAuthUrl();
        $this->travel(61)->minutes();

        $this->hitCallback($state)->assertForbidden();

        $this->assertNothingChanged();
    }

    public function test_code_refused_by_shopee_saves_nothing(): void
    {
        Http::fake(['*' => Http::response(['error' => 'invalid_code', 'message' => 'invalid'], 403)]);

        $this->hitCallback($this->stateFromAuthUrl())->assertStatus(500);

        $this->assertNothingChanged();
    }

    public function test_reply_without_a_token_saves_nothing(): void
    {
        Http::fake(['*' => Http::response(['error' => ''])]);

        $this->hitCallback($this->stateFromAuthUrl())->assertStatus(500);

        $this->assertNothingChanged();
    }
}
