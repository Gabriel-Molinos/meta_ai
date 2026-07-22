<?php

declare(strict_types=1);

namespace App\Core\Encryption;

use RuntimeException;

class Encryptor
{
    private const CIPHER = 'AES-256-CBC';
    private string $key;

    public function __construct(string $key)
    {
        $this->key = hash('sha256', $key, true);
    }

    public function encrypt(string $value): string
    {
        $ivLen  = openssl_cipher_iv_length(self::CIPHER);
        $iv     = random_bytes($ivLen);
        $cipher = openssl_encrypt($value, self::CIPHER, $this->key, OPENSSL_RAW_DATA, $iv);
        return base64_encode($iv . $cipher);
    }

    public function decrypt(string $payload): string
    {
        $data   = base64_decode($payload, true);
        $ivLen  = openssl_cipher_iv_length(self::CIPHER);
        $iv     = substr($data, 0, $ivLen);
        $cipher = substr($data, $ivLen);
        $plain  = openssl_decrypt($cipher, self::CIPHER, $this->key, OPENSSL_RAW_DATA, $iv);

        if ($plain === false) {
            throw new RuntimeException('Decryption failed — wrong key or corrupted data');
        }

        return $plain;
    }
}
