<?php

namespace Tests\Feature;

use App\Models\Project;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Tests\TestCase;

class WebhookDebugLogTest extends TestCase
{
    use RefreshDatabase;

    private Project $project;

    protected function setUp(): void
    {
        parent::setUp();
        $this->project = Project::factory()->create(['token' => 'test-token-123']);
    }

    /** @test */
    public function webhook_logs_when_debug_logging_is_enabled()
    {
        // Enable webhook debug logging
        Config::set('app.webhook_debug_log', true);
        
        // Clear any existing log content
        $logPath = storage_path('logs/webhook_debug.log');
        if (file_exists($logPath)) {
            file_put_contents($logPath, '');
        }

        $payload = [
            'Type' => 'Notification',
            'MessageId' => 'test-debug-log-123',
            'Message' => json_encode([
                'eventType' => 'Send',
                'mail' => [
                    'messageId' => 'test-message-123',
                    'source' => 'test@example.com',
                    'destination' => ['recipient@example.com'],
                    'timestamp' => '2025-01-01T10:00:00.000Z',
                    'commonHeaders' => [
                        'subject' => 'Test Email Subject'
                    ]
                ]
            ])
        ];

        $this->postJson('/webhook/test-token-123', $payload);

        // Check that log file was created and contains the payload
        $this->assertFileExists($logPath);
        $logContent = file_get_contents($logPath);
        $this->assertStringContainsString('Incoming webhook payload:', $logContent);
        $this->assertStringContainsString('test-debug-log-123', $logContent);
    }

    /** @test */
    public function webhook_does_not_log_when_debug_logging_is_disabled()
    {
        // Disable webhook debug logging
        Config::set('app.webhook_debug_log', false);
        
        // Clear any existing log content
        $logPath = storage_path('logs/webhook_debug.log');
        if (file_exists($logPath)) {
            file_put_contents($logPath, '');
        }

        $payload = [
            'Type' => 'Notification',
            'MessageId' => 'test-no-debug-log-123',
            'Message' => json_encode([
                'eventType' => 'Send',
                'mail' => [
                    'messageId' => 'test-message-456',
                    'source' => 'test@example.com',
                    'destination' => ['recipient@example.com'],
                    'timestamp' => '2025-01-01T10:00:00.000Z',
                    'commonHeaders' => [
                        'subject' => 'Test Email Subject'
                    ]
                ]
            ])
        ];

        $this->postJson('/webhook/test-token-123', $payload);

        // Check that no logging occurred
        if (file_exists($logPath)) {
            $logContent = file_get_contents($logPath);
            $this->assertStringNotContainsString('test-no-debug-log-123', $logContent);
        }
    }
}