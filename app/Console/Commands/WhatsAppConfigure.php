<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;

class WhatsAppConfigure extends Command
{
    protected $signature = 'whatsapp:configure
        {--flows : Upload and publish WhatsApp Flows only}
        {--templates : Create message templates only}
        {--publish : Also publish flows (otherwise left as DRAFT for review)}
        {--endpoint= : Flow endpoint URI (required for flows with data exchange)}
        {--dry-run : Show what would be sent without making API calls}';

    protected $description = 'Submit WhatsApp Flows and message templates to the WhatsApp Business API';

    protected string $apiUrl;
    protected string $token;
    protected string $wabaId;
    protected string $phoneNumberId;

    /**
     * Flow definitions: key => [name, json_path, category, template_body, template_cta]
     */
    protected function flowDefinitions(): array
    {
        return [
            'buy_zesa' => [
                'name' => 'buy_zesa',
                'json_path' => resource_path('flows/buy_zesa.json'),
                'categories' => ['OTHER'],
                'template_name' => config('whatsapp.flow_templates.buy_zesa', 'buy_zesa_flow'),
                'template_body' => 'Purchase ZESA electricity tokens instantly.',
                'template_cta' => 'Buy ZESA',
                'template_category' => 'MARKETING',
            ],
            'settings' => [
                'name' => 'settings',
                'json_path' => resource_path('flows/settings.json'),
                'categories' => ['OTHER'],
                'template_name' => config('whatsapp.flow_templates.settings', 'settings_flow'),
                'template_body' => 'Update your account preferences.',
                'template_cta' => 'Settings',
                'template_category' => 'UTILITY',
            ],
            'customer' => [
                'name' => 'customer',
                'json_path' => resource_path('flows/customer.json'),
                'categories' => ['OTHER'],
                'template_name' => config('whatsapp.flow_templates.customer', 'customer_flow'),
                'template_body' => 'Buy ZESA, airtime, and data bundles.',
                'template_cta' => 'Shop Now',
                'template_category' => 'MARKETING',
            ],
        ];
    }

    public function handle(): int
    {
        $this->apiUrl = config('whatsapp.api_url', 'https://graph.facebook.com/v25.0');
        $this->token = config('whatsapp.token');
        $this->wabaId = config('whatsapp.business_account_id');
        $this->phoneNumberId = config('whatsapp.phone_number_id');

        if (! $this->token || ! $this->wabaId) {
            $this->error('Missing WHATSAPP_TOKEN or WHATSAPP_BUSINESS_ACCOUNT_ID in .env');
            return self::FAILURE;
        }

        $doFlows = $this->option('flows') || ! $this->option('templates');
        $doTemplates = $this->option('templates') || ! $this->option('flows');

        $flowIds = [];

        if ($doFlows) {
            $this->info('');
            $this->line('━━━ <fg=cyan>Uploading WhatsApp Flows</> ━━━');
            $flowIds = $this->uploadFlows();
        }

        if ($doTemplates) {
            $this->info('');
            $this->line('━━━ <fg=cyan>Creating Message Templates</> ━━━');
            $this->createTemplates($flowIds);
        }

        $this->info('');
        $this->info('✅ Configuration complete!');

        return self::SUCCESS;
    }

    // ── Flow Upload ─────────────────────────────────

    protected function uploadFlows(): array
    {
        $flowIds = [];
        $endpoint = $this->option('endpoint');
        $publish = $this->option('publish');

        foreach ($this->flowDefinitions() as $key => $def) {
            $this->newLine();
            $this->line("  <fg=yellow>⚡</> Flow: <fg=white;options=bold>{$def['name']}</>");

            if (! File::exists($def['json_path'])) {
                $this->warn("    ⚠ JSON file not found: {$def['json_path']}");
                continue;
            }

            $flowJson = File::get($def['json_path']);

            // Check if we already have a flow ID configured
            $existingId = config("whatsapp.flows.{$key}");
            if ($existingId) {
                $this->line("    📋 Existing flow ID: <fg=cyan>{$existingId}</>");

                if ($this->confirm("    Update existing flow with new JSON?", false)) {
                    $this->updateFlowAsset($existingId, $def['json_path']);
                    $flowIds[$key] = $existingId;
                } else {
                    $flowIds[$key] = $existingId;
                    $this->line('    ↳ Skipped (keeping existing)');
                }
                continue;
            }

            // Create new flow
            $payload = [
                'name' => $def['name'],
                'categories' => $def['categories'],
            ];

            if ($publish) {
                $payload['flow_json'] = $flowJson;
                $payload['publish'] = true;
            }

            if ($endpoint) {
                $payload['endpoint_uri'] = $endpoint;
            }

            if ($this->option('dry-run')) {
                $this->line('    <fg=gray>[DRY RUN] Would POST to:');
                $this->line("    {$this->apiUrl}/{$this->wabaId}/flows</>");
                $this->line("    <fg=gray>Payload: name={$def['name']}, publish={$publish}</>");
                continue;
            }

            $this->line("    Creating flow...");
            $response = Http::withToken($this->token)
                ->post("{$this->apiUrl}/{$this->wabaId}/flows", $payload);

            $result = $response->json();

            if ($response->failed()) {
                $this->error("    ✗ Failed: " . ($result['error']['error_description'] ?? json_encode($result)));
                continue;
            }

            $flowId = $result['id'] ?? null;
            $flowIds[$key] = $flowId;

            $this->line("    ✓ Created: <fg=green>{$flowId}</>");

            // Show validation errors if any
            if (! empty($result['validation_errors'])) {
                $this->warn('    ⚠ Validation errors:');
                foreach ($result['validation_errors'] as $err) {
                    $this->line("      • {$err['message']}");
                }
            }

            // If not published inline, upload the JSON asset separately and optionally publish
            if (! $publish && $flowId) {
                $this->updateFlowAsset($flowId, $def['json_path']);

                if ($this->confirm("    Publish flow {$flowId}?", false)) {
                    $this->publishFlow($flowId);
                }
            }

            // Update .env suggestion
            $envKey = 'WHATSAPP_' . strtoupper($key) . '_FLOW_ID';
            $this->line("    📝 Add to .env: <fg=yellow>{$envKey}={$flowId}</>");
        }

        // Offer to update .env automatically
        if (! $this->option('dry-run') && ! empty($flowIds)) {
            $this->newLine();
            if ($this->confirm('Update .env with new flow IDs?', true)) {
                $this->updateEnvFile($flowIds);
            }
        }

        return $flowIds;
    }

    protected function updateFlowAsset(string $flowId, string $jsonPath): void
    {
        $this->line("    Uploading JSON asset...");

        $response = Http::withToken($this->token)
            ->attach('file', File::get($jsonPath), 'flow.json')
            ->post("{$this->apiUrl}/{$flowId}/assets", [
                'name' => 'flow.json',
                'asset_type' => 'FLOW_JSON',
            ]);

        $result = $response->json();

        if ($response->successful() && ($result['success'] ?? false)) {
            $this->line("    ✓ JSON uploaded");

            if (! empty($result['validation_errors'])) {
                $this->warn('    ⚠ Validation errors:');
                foreach ($result['validation_errors'] as $err) {
                    $this->line("      • {$err['message']}");
                }
            }
        } else {
            $this->error("    ✗ Upload failed: " . ($result['error']['message'] ?? json_encode($result)));
        }
    }

    protected function publishFlow(string $flowId): void
    {
        $this->line("    Publishing flow...");

        $response = Http::withToken($this->token)
            ->post("{$this->apiUrl}/{$flowId}/publish");

        $result = $response->json();

        if ($response->successful() && ($result['success'] ?? false)) {
            $this->line("    ✓ <fg=green>Published</>");
        } else {
            $this->error("    ✗ Publish failed: " . ($result['error']['message'] ?? json_encode($result)));
        }
    }

    // ── Template Creation ───────────────────────────

    protected function createTemplates(array $flowIds): void
    {
        $language = config('whatsapp.template_language', 'en');

        foreach ($this->flowDefinitions() as $key => $def) {
            $this->newLine();
            $this->line("  <fg=yellow>📄</> Template: <fg=white;options=bold>{$def['template_name']}</>");

            $flowId = $flowIds[$key] ?? config("whatsapp.flows.{$key}");

            if (! $flowId) {
                $this->warn("    ⚠ No flow ID for '{$key}'. Upload flows first (--flows) or set WHATSAPP_" . strtoupper($key) . "_FLOW_ID in .env");
                continue;
            }

            $payload = [
                'name' => $def['template_name'],
                'language' => $language,
                'category' => $def['template_category'],
                'components' => [
                    [
                        'type' => 'body',
                        'text' => $def['template_body'],
                        'example' => [
                            'body_text' => [['Joe']],
                        ],
                    ],
                    [
                        'type' => 'BUTTONS',
                        'buttons' => [
                            [
                                'type' => 'FLOW',
                                'text' => $def['template_cta'],
                                'flow_id' => $flowId,
                                'flow_action' => 'navigate',
                                'navigate_screen' => $key === 'buy_zesa' ? 'BUY_ZESA_SCREEN' : 'SETTINGS_SCREEN',
                            ],
                        ],
                    ],
                ],
            ];

            if ($this->option('dry-run')) {
                $this->line('    <fg=gray>[DRY RUN] Would POST to:');
                $this->line("    {$this->apiUrl}/{$this->wabaId}/message_templates</>");
                $this->line("    <fg=gray>Template: {$def['template_name']}, flow_id: {$flowId}</>");
                $this->line("    <fg=gray>Payload: ".json_encode($payload, JSON_PRETTY_PRINT)."</>");
                continue;
            }

            $this->line("    Creating template...");
            $response = Http::withToken($this->token)
                ->post("{$this->apiUrl}/{$this->wabaId}/message_templates", $payload);

            $result = $response->json();

            if ($response->failed()) {
                $error =
                    $result['error']['error_user_msg'] ??
                    $result['error']['error_user_title'] ??
                    $result['error']['message'] ??
                    json_encode($result);

                // If template already exists, show a helpful message
                if (str_contains($error, 'already exists') || ($result['error']['code'] ?? 0) === 2388023) {
                    $this->warn("    ⚠ Template '{$def['template_name']}' already exists. Delete it in Meta Business Suite to re-create, or use a different name.");
                } else {
                    $this->error("    ✗ Failed: {$error}");
                }
                continue;
            }

            $templateId = $result['id'] ?? 'unknown';
            $status = $result['status'] ?? 'unknown';

            $statusColor = $status === 'APPROVED' ? 'green' : 'yellow';
            $this->line("    ✓ Created: <fg={$statusColor}>{$templateId}</> (status: {$status})");

            if ($status === 'PENDING') {
                $this->line('    ↳ Template is pending approval in Meta Business Suite');
            }
        }
    }

    // ── .env helpers ────────────────────────────────

    protected function updateEnvFile(array $flowIds): void
    {
        $envPath = base_path('.env');
        $content = File::get($envPath);

        foreach ($flowIds as $key => $id) {
            if (! $id) continue;

            $envKey = 'WHATSAPP_' . strtoupper($key) . '_FLOW_ID';

            if (preg_match("/^{$envKey}=.*/m", $content)) {
                $content = preg_replace("/^{$envKey}=.*/m", "{$envKey}={$id}", $content);
            } else {
                $content .= "\n{$envKey}={$id}";
            }

            $this->line("  ✓ {$envKey}={$id}");
        }

        File::put($envPath, $content);
        $this->info('  .env updated');
    }
}
