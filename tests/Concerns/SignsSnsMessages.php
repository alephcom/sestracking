<?php

namespace Tests\Concerns;

use Aws\Sns\Message;
use Aws\Sns\MessageValidator;

/**
 * Signs SNS payloads with a throwaway key pair that the app's MessageValidator trusts,
 * standing in for the real AWS SNS signing certificate.
 */
trait SignsSnsMessages
{
    protected const SNS_CERT_URL = 'https://sns.us-east-1.amazonaws.com/SimpleNotificationService-test.pem';

    /** @var \OpenSSLAsymmetricKey */
    private $snsPrivateKey;

    protected function trustTestSnsCertificate(): void
    {
        $this->snsPrivateKey = openssl_pkey_new(['private_key_bits' => 2048, 'private_key_type' => OPENSSL_KEYTYPE_RSA]);
        $csr = openssl_csr_new(['commonName' => 'sns.amazonaws.com'], $this->snsPrivateKey);
        openssl_x509_export(openssl_csr_sign($csr, null, $this->snsPrivateKey, 1), $certificatePem);

        $this->app->instance(MessageValidator::class, new MessageValidator(
            fn (string $url) => $url === self::SNS_CERT_URL ? $certificatePem : false
        ));
    }

    protected function signSnsPayload(array $payload): array
    {
        $payload += [
            'SignatureVersion' => '2',
            'SigningCertURL' => self::SNS_CERT_URL,
        ];

        $stringToSign = (new MessageValidator)->getStringToSign(new Message($payload + ['Signature' => 'x']));
        openssl_sign($stringToSign, $signature, $this->snsPrivateKey, OPENSSL_ALGO_SHA256);
        $payload['Signature'] = base64_encode($signature);

        return $payload;
    }
}
