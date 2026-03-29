<?php

namespace App\Services;

use Illuminate\Support\Facades\Log;

/**
 * WhatsApp Flows endpoint encryption/decryption.
 *
 * Implements the Meta data_api_version "3.0" specification:
 * https://developers.facebook.com/docs/whatsapp/flows/guides/implementingyourflowendpoint/#encrypt
 *
 * Request:
 *  - Decrypt the AES key with the RSA private key (OAEP + SHA256 + MGF1/SHA256)
 *  - Decrypt the flow data with AES-128-GCM using the AES key + IV
 *
 * Response:
 *  - Encrypt the JSON response with AES-128-GCM using the same AES key + flipped IV
 *  - Return as base64 plain text
 */
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

    /**
     * Decrypt an incoming request from WhatsApp Flows.
     *
     * @param string $encryptedFlowData  Base64-encoded encrypted flow data
     * @param string $encryptedAesKey    Base64-encoded encrypted AES key
     * @param string $initialVector      Base64-encoded initialization vector
     * @return array{decrypted_data: array, aes_key: string, iv: string}
     * @throws \RuntimeException if decryption fails
     */
    public function decryptRequest(string $encryptedFlowData, string $encryptedAesKey, string $initialVector): array
    {
        $encryptedAesKeyBytes = base64_decode($encryptedAesKey, true);
        $ivBytes              = base64_decode($initialVector, true);
        $flowDataBytes        = base64_decode($encryptedFlowData, true);

        if ($encryptedAesKeyBytes === false || $ivBytes === false || $flowDataBytes === false) {
            throw new \RuntimeException('Invalid base64 encoding in request payload.');
        }

        // ---------------------------------------------------------------
        // 1. Load the RSA private key (with optional passphrase)
        // ---------------------------------------------------------------
        $privateKey = openssl_pkey_get_private($this->privateKeyPem, $this->passphrase ?? '');

        if (! $privateKey) {
            $osslError = openssl_error_string();

            // Provide an actionable error message for the most common causes.
            if (str_contains((string) $osslError, 'bad decrypt') || str_contains((string) $osslError, 'bad password')) {
                throw new \RuntimeException(
                    'Failed to load private key: the key appears to be password-protected. ' .
                    'Set WHATSAPP_FLOW_PRIVATE_KEY_PASSPHRASE in your .env file. ' .
                    "OpenSSL said: {$osslError}"
                );
            }

            throw new \RuntimeException("Failed to load private key: {$osslError}");
        }

        // ---------------------------------------------------------------
        // 2. Decrypt the AES key — try SHA-256 OAEP first, fall back to SHA-1
        // ---------------------------------------------------------------
        $aesKey = $this->rsaOaepSha256Decrypt($encryptedAesKeyBytes, $privateKey);

        if (empty($aesKey)) {
            // SHA-256 OAEP failed; try PHP's built-in (SHA-1 OAEP) as last resort
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

        // ---------------------------------------------------------------
        // 3. Decrypt the flow data with AES-128-GCM
        //    The last 16 bytes of the payload are the GCM auth tag.
        // ---------------------------------------------------------------
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

    /**
     * Encrypt a response payload for WhatsApp Flows.
     *
     * @param array  $response  The JSON-serializable response payload
     * @param string $aesKey    The raw AES key from decryption
     * @param string $iv        The raw IV from decryption (will be bit-flipped)
     * @return string Base64-encoded encrypted response
     */
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

    /**
     * Verify the X-Hub-Signature-256 header from Meta.
     *
     * @param string $payload   Raw request body
     * @param string $signature The X-Hub-Signature-256 header value
     * @return bool
     */
    public function verifySignature(string $payload, string $signature): bool
    {
        if (! $this->appSecret) {
            Log::warning('App secret not configured — skipping signature verification.');
            return true;
        }

        $expectedSignature = 'sha256=' . hash_hmac('sha256', $payload, $this->appSecret);

        return hash_equals($expectedSignature, $signature);
    }

    // =========================================================================
    // Internal helpers
    // =========================================================================

    /**
     * Flip all bits of the initialization vector.
     */
    protected function flipIv(string $iv): string
    {
        $flipped = '';
        for ($i = 0; $i < strlen($iv); $i++) {
            $flipped .= chr(ord($iv[$i]) ^ 0xFF);
        }
        return $flipped;
    }

    /**
     * Load and normalize the RSA private key PEM from file or env var.
     *
     * Throws immediately if no key source is configured so the error is obvious.
     */
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
            // Env vars stored as a single line use literal \n — convert them back.
            $pem = str_replace('\\n', "\n", $keyEnv);

            // Ensure the PEM has the proper header/footer and no stray whitespace.
            $pem = $this->normalizePem($pem);

            return $pem;
        }

        throw new \RuntimeException(
            'No WhatsApp Flow private key configured. ' .
            'Set either WHATSAPP_FLOW_PRIVATE_KEY_PATH (path to .pem file) ' .
            'or WHATSAPP_FLOW_PRIVATE_KEY (PEM contents) in your .env file.'
        );
    }

    /**
     * Ensure the PEM string is well-formed.
     *
     * When a PEM is stored in an env var, extra spaces or missing newlines around
     * the header/footer lines cause openssl_pkey_get_private() to return false.
     */
    protected function normalizePem(string $pem): string
    {
        // Strip any Windows-style CR characters.
        $pem = str_replace("\r", '', $pem);

        // If the whole key was collapsed onto a single line (no newlines at all),
        // try to reconstruct it: split on the header/footer markers.
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

            // Break the base64 body into 64-character lines (PEM standard).
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

    /**
     * RSA OAEP decryption with SHA-256 hash and SHA-256 MGF1.
     *
     * PHP's openssl_private_decrypt with OPENSSL_PKCS1_OAEP_PADDING uses SHA-1.
     * WhatsApp requires SHA-256 for both the OAEP hash and MGF1.
     * Uses phpseclib3 when available; logs a warning if it is not installed.
     *
     * @param string   $ciphertext  Raw (non-base64) ciphertext bytes
     * @param resource $privateKey  An OpenSSL key resource (used only to get the PEM)
     * @return string Raw decrypted bytes, or empty string on failure
     */
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
