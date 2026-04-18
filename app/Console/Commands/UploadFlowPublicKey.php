<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;

/**
 * Upload the generated RSA public key to Meta's WhatsApp Business API.
 *
 * Run whatsapp:generate-keypair first, then run this command.
 */
class UploadFlowPublicKey extends Command
{
    protected $signature = 'whatsapp:upload-public-key
        {--phone-number-id= : WhatsApp Phone Number ID (overrides config)}
        {--token=           : Meta access token (overrides config)}
        {--public-key-path= : Path to the public key PEM file (overrides default)}
        {--dry-run          : Print the request without sending it}';

    protected $description = 'Upload the RSA public key to Meta for WhatsApp Flows encryption';

    public function handle(): int
    {
        $phoneNumberId = $this->option('phone-number-id')
            ?? config('whatsapp.phone_number_id');

        $token = $this->option('token')
            ?? config('whatsapp.token');

        $publicKeyPath = $this->option('public-key-path')
            ?? base_path('public.pem');

        // --- Validate inputs ------------------------------------------------

        if (empty($phoneNumberId)) {
            $this->error('Phone Number ID is required. Pass --phone-number-id or set whatsapp.phone_number_id in config.');
            return self::FAILURE;
        }

        if (empty($token)) {
            $this->error('Access token is required. Pass --token or set whatsapp.access_token in config.');
            return self::FAILURE;
        }

        if (! File::exists($publicKeyPath)) {
            $this->error("Public key not found at: {$publicKeyPath}");
            $this->line('Run php artisan whatsapp:generate-keypair first.');
            return self::FAILURE;
        }

        // --- Load public key ------------------------------------------------

        $publicKeyPem = trim(File::get($publicKeyPath));

        if (! openssl_pkey_get_public($publicKeyPem)) {
            $this->error('The file does not contain a valid RSA public key.');
            return self::FAILURE;
        }

        $this->info("Public key loaded from: {$publicKeyPath}");

        // --- Dry-run preview ------------------------------------------------

        $url = "https://graph.facebook.com/v21.0/{$phoneNumberId}/whatsapp_business_encryption";

        if ($this->option('dry-run')) {
            $this->warn('Dry-run mode — request NOT sent.');
            $this->newLine();
            $this->line("POST {$url}");
            $this->line('Content-Type: application/x-www-form-urlencoded');
            $this->line("Authorization: Bearer {$token}");
            $this->newLine();
            $this->line('Payload:');
            $this->line('  business_public_key=' . substr($publicKeyPem, 0, 64) . '...');
            return self::SUCCESS;
        }

        // --- Upload ---------------------------------------------------------

        $this->line('Uploading public key to Meta…');

        $response = Http::withToken($token)
            ->asForm()
            ->post($url, [
                'business_public_key' => $publicKeyPem,
            ]);

        // --- Handle response ------------------------------------------------

        if ($response->successful()) {
            $this->info('✅ Public key uploaded successfully!');
            $this->newLine();
            $this->line('Response from Meta:');
            $this->line($response->body());
            return self::SUCCESS;
        }

        $this->error('❌ Upload failed (HTTP ' . $response->status() . ')');
        $this->newLine();

        $body = $response->json();

        if (isset($body['error'])) {
            $err = $body['error'];
            $this->table(['Field', 'Value'], [
                ['Message',  $err['message']       ?? 'n/a'],
                ['Type',     $err['type']          ?? 'n/a'],
                ['Code',     $err['code']          ?? 'n/a'],
                ['Subcode',  $err['error_subcode'] ?? 'n/a'],
                ['Trace ID', $err['fbtrace_id']    ?? 'n/a'],
            ]);
        } else {
            $this->line($response->body());
        }

        return self::FAILURE;
    }
}
