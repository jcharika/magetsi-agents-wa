<?php

namespace Magetsi\Common\Services;

use Illuminate\Support\Facades\Log;

class FlowEncryptionService
{
    protected string $privateKeyPem;
    protected ?string $passphrase;
    protected ?string $appSecret;

    public function __construct()
    {
        $this->passphrase    = config('whatsapp.flow_private_key_passphrase', 'password') ?: null;
        $this->privateKeyPem = $this->loadPrivateKey();
        $this->appSecret     = config('whatsapp.app_secret');
    }

    public function decryptRequest(string $encryptedFlowData, string $encryptedAesKey, string $initialVector): array
    {
        $encryptedAesKeyBytes = base64_decode($encryptedAesKey, true);
        $ivBytes              = base64_decode($initialVector, true);
        $flowDataBytes        = base64_decode($encryptedFlowData, true);

        if ($encryptedAesKeyBytes === false || $ivBytes === false || $flowDataBytes === false) {
            throw new \RuntimeException('Invalid base64 encoding in request payload.');
        }

        $privateKey = openssl_pkey_get_private($this->privateKeyPem, $this->passphrase ?? '');

        if (! $privateKey) {
            $osslError = openssl_error_string();

            if (str_contains((string) $osslError, 'bad decrypt') || str_contains((string) $osslError, 'bad password')) {
                throw new \RuntimeException(
                    'Failed to load private key: the key appears to be password-protected. ' .
                    'Set WHATSAPP_FLOW_PRIVATE_KEY_PASSPHRASE in your .env file. ' .
                    "OpenSSL said: {$osslError}"
                );
            }

            throw new \RuntimeException("Failed to load private key: {$osslError}");
        }

        $aesKey = $this->rsaOaepSha256Decrypt($encryptedAesKeyBytes, $privateKey);

        if (empty($aesKey)) {
            $aesKey = '';
            openssl_private_decrypt(
                $encryptedAesKeyBytes,
                $aesKey,
                $privateKey,
                OPENSSL_PKCS1_OAEP_PADDING
            );
        }

        if (empty($aesKey)) {
            throw new \RuntimeException('Failed to decrypt AES key: ' . openssl_error_string());
        }

        $tagLength     = 16;
        $encryptedBody = substr($flowDataBytes, 0, -$tagLength);
        $tag           = substr($flowDataBytes, -$tagLength);

        $decryptedData = openssl_decrypt(
            $encryptedBody,
            'aes-128-gcm',
            $aesKey,
            OPENSSL_RAW_DATA,
            $ivBytes,
            $tag
        );

        if ($decryptedData === false) {
            throw new \RuntimeException('Failed to decrypt flow data: ' . openssl_error_string());
        }

        $jsonData = json_decode($decryptedData, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new \RuntimeException('Decrypted data is not valid JSON: ' . json_last_error_msg());
        }

        return [
            'decrypted_data' => $jsonData,
            'aes_key'        => $aesKey,
            'iv'             => $ivBytes,
        ];
    }

    public function encryptResponse(array $response, string $aesKey, string $iv): string
    {
        $flippedIv = $this->flipIv($iv);
        $plaintext = json_encode($response);
        $tag       = '';

        $encrypted = openssl_encrypt(
            $plaintext,
            'aes-128-gcm',
            $aesKey,
            OPENSSL_RAW_DATA,
            $flippedIv,
            $tag
        );

        if ($encrypted === false) {
            throw new \RuntimeException('Failed to encrypt response: ' . openssl_error_string());
        }

        return base64_encode($encrypted . $tag);
    }

    public function verifySignature(string $payload, string $signature): bool
    {
        if (! $this->appSecret) {
            Log::warning('App secret not configured — skipping signature verification.');
            return true;
        }

        $expectedSignature = 'sha256=' . hash_hmac('sha256', $payload, $this->appSecret);

        return hash_equals($expectedSignature, $signature);
    }

    protected function flipIv(string $iv): string
    {
        $flipped = '';
        for ($i = 0; $i < strlen($iv); $i++) {
            $flipped .= chr(ord($iv[$i]) ^ 0xFF);
        }
        return $flipped;
    }

    protected function loadPrivateKey(): string
    {
        $keyPath = config('whatsapp.flow_private_key_path');
        $keyEnv  = config('whatsapp.flow_private_key');

        if ($keyPath) {
            if (! file_exists($keyPath)) {
                throw new \RuntimeException(
                    "Private key file not found at path: {$keyPath}. " .
                    'Check WHATSAPP_FLOW_PRIVATE_KEY_PATH in your .env file.'
                );
            }

            Log::info("Loading WhatsApp Flow private key from file: {$keyPath}");
            return file_get_contents($keyPath);
        }

        if ($keyEnv) {
            $pem = str_replace('\\n', "\n", $keyEnv);
            $pem = $this->normalizePem($pem);
            return $pem;
        }

        throw new \RuntimeException(
            'No WhatsApp Flow private key configured. ' .
            'Set either WHATSAPP_FLOW_PRIVATE_KEY_PATH (path to .pem file) ' .
            'or WHATSAPP_FLOW_PRIVATE_KEY (PEM contents) in your .env file.'
        );
    }

    protected function normalizePem(string $pem): string
    {
        $pem = str_replace("\r", '', $pem);

        if (! str_contains($pem, "\n")) {
            $pem = preg_replace(
                '/-----BEGIN ([^-]+)-----/',
                "-----BEGIN $1-----\n",
                $pem
            );
            $pem = preg_replace(
                '/-----END ([^-]+)-----/',
                "\n-----END $1-----",
                $pem
            );

            if (preg_match('/-----BEGIN [^-]+-----\n(.+)\n-----END/', $pem, $m)) {
                $body    = chunk_split(trim($m[1]), 64, "\n");
                $pem     = preg_replace(
                    '/-----BEGIN ([^-]+)-----\n.+\n-----END ([^-]+)-----/s',
                    "-----BEGIN $1-----\n{$body}-----END $2-----",
                    $pem
                );
            }
        }

        return rtrim($pem) . "\n";
    }

    protected function rsaOaepSha256Decrypt(string $ciphertext, $privateKey): string
    {
        if (class_exists(\phpseclib3\Crypt\RSA::class)) {
            try {
                /** @var \phpseclib3\Crypt\RSA\PrivateKey $rsa */
                $rsa = \phpseclib3\Crypt\RSA::load($this->privateKeyPem, $this->passphrase ?? '')
                    ->withPadding(\phpseclib3\Crypt\RSA::ENCRYPTION_OAEP)
                    ->withHash('sha256')
                    ->withMGFHash('sha256');

                return $rsa->decrypt($ciphertext) ?: '';
            } catch (\Throwable $e) {
                Log::error('phpseclib3 RSA OAEP SHA-256 decrypt failed: ' . $e->getMessage());
                return '';
            }
        }

        Log::warning(
            'phpseclib3 is not installed — RSA OAEP SHA-256 decryption will fall back to SHA-1 ' .
            'and is likely to fail with WhatsApp Flows. Run: composer require phpseclib/phpseclib'
        );

        return '';
    }
}
