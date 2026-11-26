<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\File;
use Tests\TestCase;

class LogViewerAccessTest extends TestCase
{
    private string $storage;
    private string $log;

    protected function setUp(): void
    {
        parent::setUp();

        // A throwaway storage folder, so the real log is never read or cleared
        $this->storage = sys_get_temp_dir() . '/log-viewer-test-' . uniqid();
        File::makeDirectory($this->storage . '/logs', 0755, true);
        $this->app->useStoragePath($this->storage);
        $this->log = $this->storage . '/logs/laravel.log';
        File::put($this->log, 'secret-line');

        config(['logviewer.user' => 'admin', 'logviewer.password' => 'test-only-password']);
    }

    protected function tearDown(): void
    {
        File::deleteDirectory($this->storage);

        parent::tearDown();
    }

    private function login(string $password = 'test-only-password'): array
    {
        return ['Authorization' => 'Basic ' . base64_encode('admin:' . $password)];
    }

    public function test_page_is_disabled_without_a_configured_password(): void
    {
        config(['logviewer.password' => null]);

        $this->get('/logs', $this->login(''))->assertNotFound();
        $this->post('/logs/clear', [], $this->login(''))->assertNotFound();
        $this->assertSame('secret-line', File::get($this->log));
    }

    public function test_missing_or_wrong_login_is_rejected(): void
    {
        $this->get('/logs')->assertUnauthorized()->assertHeader('WWW-Authenticate')->assertDontSee('secret-line');
        $this->get('/logs', $this->login('wrong'))->assertUnauthorized();
        $this->post('/logs/clear')->assertUnauthorized();
        $this->assertSame('secret-line', File::get($this->log));
    }

    public function test_correct_login_can_read_and_a_link_can_no_longer_clear(): void
    {
        $this->get('/logs?clear=true', $this->login())->assertOk()->assertSee('secret-line');

        $this->assertSame('secret-line', File::get($this->log));
    }

    public function test_correct_login_can_clear_with_a_post(): void
    {
        $this->post('/logs/clear', [], $this->login())->assertRedirect('/logs');

        $this->assertSame('', File::get($this->log));
    }
}
