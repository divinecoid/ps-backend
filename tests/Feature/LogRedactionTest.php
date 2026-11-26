<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;
use RuntimeException;
use Tests\TestCase;

class LogRedactionTest extends TestCase
{
    private string $path;

    protected function setUp(): void
    {
        parent::setUp();

        $this->path = sys_get_temp_dir() . '/redaction-test-' . uniqid() . '.log';

        // Same wiring as production: a stack on top of the file channel
        config([
            'logging.default' => 'stack',
            'logging.channels.stack.channels' => ['single'],
            'logging.channels.single.path' => $this->path,
            'logging.channels.single.level' => 'debug',
        ]);
        Log::forgetChannel('stack');
        Log::forgetChannel('single');
    }

    protected function tearDown(): void
    {
        File::delete($this->path);

        parent::tearDown();
    }

    public function test_secrets_are_masked_in_messages_context_and_exceptions(): void
    {
        Log::error('cURL error 28 for https://partner.example.test/api?partner_id=123&access_token=SECRET1&sign=SECRET2&shop_id=9');
        Log::info('reply', [
            'body' => '{"access_token":"SECRET3","refresh_token": "SECRET4","expire_in":14400}',
            'json' => ['refresh_token' => 'SECRET5', 'nested' => ['client_secret' => 'SECRET6']],
        ]);
        Log::error('wrapped: ' . json_encode(['body' => json_encode(['access_token' => 'SECRET7'])]));
        Log::error('failed', ['exception' => new RuntimeException('GET /token?app_secret=SECRET8&refresh_token=SECRET9')]);

        $log = File::get($this->path);

        $this->assertStringNotContainsString('SECRET', $log);
        // Everything useful for debugging stays readable
        $this->assertStringContainsString('partner_id=123', $log);
        $this->assertStringContainsString('shop_id=9', $log);
        $this->assertStringContainsString('14400', $log);
        $this->assertStringContainsString('RuntimeException', $log);
    }
}
