<?php

declare(strict_types=1);

namespace App\Service;

/**
 * Chiffrement libsodium identique à Hermes 2.2.7 / api-hermes (login /api/login).
 */
final class HermesEncryptionService
{
    /**
     * @return array{encryptedData: string, nonce: string}
     */
    public function encrypt(string $data, string $key): array
    {
        $nonce = random_bytes(\SODIUM_CRYPTO_SECRETBOX_NONCEBYTES);
        $encryptedData = sodium_crypto_secretbox($data, $nonce, $key);

        return [
            'encryptedData' => base64_encode($encryptedData),
            'nonce' => base64_encode($nonce),
        ];
    }

    public function decrypt(string $encryptedData, string $nonce, string $key): string|false
    {
        $decodedData = base64_decode($encryptedData, true);
        $decodedNonce = base64_decode($nonce, true);
        if ($decodedData === false || $decodedNonce === false) {
            return false;
        }

        $plain = sodium_crypto_secretbox_open($decodedData, $decodedNonce, $key);

        return $plain;
    }
}
