<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;

/**
 * Generate an RSA key pair for WhatsApp Flows encryption.
 *
 * The private key is stored locally (never shared).
 * The public key must be uploaded to Meta via the Cloud API.
 */
class GenerateFlowKeypair extends Command
{
    protected $signature = 'whatsapp:generate-keypair
        {--bits=2048 : RSA key size in bits}
        {--force : Overwrite existing keys}';

    protected $description = 'Generate RSA key pair for WhatsApp Flows encryption';

    public function handle(): int
    {
        $bits = (int) $this->option('bits');
        $force = $this->option('force');

        $dir = storage_path('app/whatsapp');
        $privatePath = $dir . '/private.pem';
        $publicPath = $dir . '/public.pem';

        if (File::exists($privatePath) && ! $force) {
            $this->error("Key pair already exists at {$dir}. Use --force to overwrite.");
            return self::FAILURE;
        }

        // Create directory
        File::ensureDirectoryExists($dir, 0700);

        // Generate key pair
        $config = [
            'private_key_bits' => $bits,
            'private_key_type' => OPENSSL_KEYTYPE_RSA,
        ];

        $key = openssl_pkey_new($config);

        if (! $key) {
            $this->error('Failed to generate key pair: ' . openssl_error_string());
            return self::FAILURE;
        }

        // Export private key
        openssl_pkey_export($key, $privateKeyPem);
        File::put($privatePath, $privateKeyPem);
        chmod($privatePath, 0600); // Only owner can read

        // Export public key
        $publicKeyDetails = openssl_pkey_get_details($key);
        $publicKeyPem = $publicKeyDetails['key'];
        File::put($publicPath, $publicKeyPem);

        $this->info('✅ Key pair generated successfully!');
        $this->newLine();

        $this->table(['Key', 'Path'], [
            ['Private Key', $privatePath],
            ['Public Key', $publicPath],
        ]);

        $this->newLine();
        $this->warn('⚠️  Next steps:');
        $this->line('  1. Set WHATSAPP_FLOW_PRIVATE_KEY_PATH in .env:');
        $this->line("     WHATSAPP_FLOW_PRIVATE_KEY_PATH={$privatePath}");
        $this->newLine();
        $this->line('  2. Upload the public key to Meta:');
        $this->newLine();

        $phoneNumberId = config('whatsapp.phone_number_id') ?: '<PHONE_NUMBER_ID>';
        $token = '<ACCESS_TOKEN>';

        // Show the curl command
        $publicKeyEscaped = str_replace("\n", '\\n', trim($publicKeyPem));
        $this->line("     curl -X POST \\");
        $this->line("       'https://graph.facebook.com/v21.0/{$phoneNumberId}/whatsapp_business_encryption' \\");
        $this->line("       -H 'Authorization: Bearer {$token}' \\");
        $this->line("       -H 'Content-Type: application/x-www-form-urlencoded' \\");
        $this->line("       -d 'business_public_key={$publicKeyEscaped}'");

        $this->newLine();
        $this->line('  3. See docs/whatsapp-flows-setup.md for full instructions.');

        return self::SUCCESS;
    }
}
