<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Verifies that an SNS message was signed by AWS.
 *
 * Follows https://docs.aws.amazon.com/sns/latest/dg/sns-verify-signature-of-message.html
 * (the same rules as AWS's aws-php-sns-message-validator package).
 */
class SnsMessageValidator
{
    /** Signing certificates must come from an SNS endpoint (prevents certificate substitution). */
    private const CERT_HOST_PATTERN = '/^sns\.[a-zA-Z0-9\-]{3,}\.amazonaws\.com(\.cn)?$/';

    private const CERT_CACHE_SECONDS = 86400;

    private const SIGNED_KEYS_NOTIFICATION = ['Message', 'MessageId', 'Subject', 'Timestamp', 'TopicArn', 'Type'];

    private const SIGNED_KEYS_SUBSCRIPTION = ['Message', 'MessageId', 'SubscribeURL', 'Timestamp', 'Token', 'TopicArn', 'Type'];

    /**
     * @param  array<string, mixed>  $message  The decoded SNS JSON body
     */
    public function isValid(array $message): bool
    {
        $type = $message['Type'] ?? null;
        $signedKeys = match ($type) {
            'Notification' => self::SIGNED_KEYS_NOTIFICATION,
            'SubscriptionConfirmation', 'UnsubscribeConfirmation' => self::SIGNED_KEYS_SUBSCRIPTION,
            default => null,
        };
        if ($signedKeys === null) {
            return false;
        }

        $algorithm = match ((string) ($message['SignatureVersion'] ?? '')) {
            '1' => OPENSSL_ALGO_SHA1,
            '2' => OPENSSL_ALGO_SHA256,
            default => null,
        };
        $signature = is_string($message['Signature'] ?? null) ? base64_decode($message['Signature'], true) : false;
        $certUrl = $message['SigningCertURL'] ?? null;
        if ($algorithm === null || $signature === false || ! is_string($certUrl) || ! $this->isTrustedCertUrl($certUrl)) {
            return false;
        }

        $stringToSign = '';
        foreach ($signedKeys as $key) {
            // Subject is optional on notifications (and left out of the signature when absent or null)
            if ($key === 'Subject' && ! isset($message['Subject'])) {
                continue;
            }
            if (! isset($message[$key]) || ! is_scalar($message[$key])) {
                return false;
            }
            $stringToSign .= $key."\n".$message[$key]."\n";
        }

        $publicKey = $this->publicKeyFor($certUrl);
        if ($publicKey === null) {
            return false;
        }

        return openssl_verify($stringToSign, $signature, $publicKey, $algorithm) === 1;
    }

    private function isTrustedCertUrl(string $url): bool
    {
        $parts = parse_url($url);

        return is_array($parts)
            && ($parts['scheme'] ?? null) === 'https'
            && ! isset($parts['port']) && ! isset($parts['user']) && ! isset($parts['pass'])
            && preg_match(self::CERT_HOST_PATTERN, $parts['host'] ?? '') === 1
            && str_ends_with($parts['path'] ?? '', '.pem');
    }

    private function publicKeyFor(string $certUrl): ?\OpenSSLAsymmetricKey
    {
        $cacheKey = 'sns-signing-cert:'.sha1($certUrl);
        $pem = Cache::get($cacheKey);

        if (! is_string($pem)) {
            try {
                $response = Http::timeout(5)->get($certUrl);
            } catch (\Throwable $e) {
                Log::warning('Could not fetch SNS signing certificate', ['url' => $certUrl, 'error' => $e->getMessage()]);

                return null;
            }
            if (! $response->successful()) {
                Log::warning('Could not fetch SNS signing certificate', ['url' => $certUrl, 'status' => $response->status()]);

                return null;
            }
            $pem = $response->body();
        }

        $publicKey = openssl_pkey_get_public($pem);
        if ($publicKey === false) {
            return null;
        }

        // Only cache certificates that parse, so a bad response is retried next time
        Cache::put($cacheKey, $pem, self::CERT_CACHE_SECONDS);

        return $publicKey;
    }
}
