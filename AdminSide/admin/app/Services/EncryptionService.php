<?php

namespace App\Services;

use Illuminate\Support\Facades\Crypt;
use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Support\Facades\Log;

// AES helper for AdminSide.
// Supports both Laravel and Node-style encrypted values.
class EncryptionService
{
    // Read APP_KEY. If it's base64:..., decode it.
    private static function getEncryptionKey()
    {
        $key = config('app.key');
        
        // If key starts with "base64:", decode it
        if (str_starts_with($key, 'base64:')) {
            return base64_decode(substr($key, 7));
        }
        
        return $key;
    }

    // Encrypt text with Laravel Crypt.
    public static function encrypt($text)
    {
        if (empty($text)) {
            return $text;
        }

        try {
            // Laravel encryption (AES-256-CBC by default)
            return Crypt::encryptString($text);
        } catch (\Exception $e) {
            Log::error('Encryption error: ' . $e->getMessage());
            throw new \Exception('Failed to encrypt data');
        }
    }

    // Decrypt Node format: base64(iv + ciphertext).
    private static function decryptNodeJsFormat($encryptedData)
    {
        try {
            // Decode input
            $combined = base64_decode($encryptedData, true);
            if ($combined === false) {
                return null;
            }
            
            // Need at least IV (16 bytes) + 1 byte payload
            if (strlen($combined) < 17) {
                return null;
            }
            
            // Split into IV and ciphertext
            $iv = substr($combined, 0, 16);
            $encrypted = substr($combined, 16);
            
            // Try main key first
            $key = self::getEncryptionKey();
            
            $decrypted = openssl_decrypt(
                $encrypted,
                'aes-256-cbc',
                $key,
                OPENSSL_RAW_DATA,
                $iv
            );
            
            if ($decrypted !== false) {
                return $decrypted;
            }
            
            // Fallback key for cross-service APP_KEY mismatch
            $fallbackKey = base64_decode('ciPqFYTQJ2bGZ0NUrfY7mvwODuOZ6zyUTlIh1D+pb+w=');
            if ($fallbackKey !== $key) {
                $decrypted = openssl_decrypt(
                    $encrypted,
                    'aes-256-cbc',
                    $fallbackKey,
                    OPENSSL_RAW_DATA,
                    $iv
                );
                
                if ($decrypted !== false) {
                    return $decrypted;
                }
            }
            
            // Clear OpenSSL error queue
            while (openssl_error_string()) {}
            return null;
        } catch (\Exception $e) {
            return null;
        }
    }

    // Decrypt text. Handles Laravel + Node formats and multi-layer values.
    public static function decrypt($encryptedText)
    {
        if (empty($encryptedText) || !is_string($encryptedText)) {
            return $encryptedText;
        }
        
        try {
            // Unwrap up to 20 layers (old data may be encrypted multiple times)
            $current = $encryptedText;
            for ($i = 0; $i < 20; $i++) {
                $decrypted = self::decryptOnce($current);
                if ($decrypted === $current) break;
                $current = $decrypted;
            }
            return $current;
        } catch (\Exception $e) {
            Log::error('Unexpected error during decryption', [
                'error' => $e->getMessage(),
                'data_preview' => substr($encryptedText, 0, 50)
            ]);
            return $encryptedText;
        }
    }

    // Try one decrypt pass: Laravel first, then Node format.
    private static function decryptOnce($encryptedText)
    {
        if (empty($encryptedText) || !is_string($encryptedText)) {
            return $encryptedText;
        }

        try {
            // Try Laravel payload first
            try {
                return Crypt::decryptString($encryptedText);
            } catch (\Exception $e) {
                // Not Laravel format
            }
            
            $decrypted = self::decryptNodeJsFormat($encryptedText);
            if ($decrypted !== null) {
                return $decrypted;
            }
            
            // If both fail, likely plaintext
            return $encryptedText;
        } catch (\Exception $e) {
            return $encryptedText;
        }
    }

    // Encrypt selected fields in an array.
    public static function encryptFields(array $data, array $fields)
    {
        foreach ($fields as $field) {
            if (isset($data[$field]) && !empty($data[$field])) {
                $data[$field] = self::encrypt($data[$field]);
            }
        }

        return $data;
    }

    // Decrypt selected fields in an array.
    public static function decryptFields(array $data, array $fields)
    {
        foreach ($fields as $field) {
            if (isset($data[$field]) && !empty($data[$field])) {
                $data[$field] = self::decrypt($data[$field]);
            }
        }

        return $data;
    }

    // Decrypt selected fields on an object/model.
    public static function decryptModelFields($model, array $fields)
    {
        foreach ($fields as $field) {
            if (isset($model->$field) && !empty($model->$field)) {
                $model->$field = self::decrypt($model->$field);
            }
        }

        return $model;
    }

    // Role check for who can view decrypted values.
    public static function canDecrypt($userRole)
    {
        $authorizedRoles = ['police', 'admin', 'super_admin'];
        return in_array($userRole, $authorizedRoles);
    }

    // Best-effort check if a value looks encrypted.
    public static function isEncrypted($text)
    {
        if (empty($text) || !is_string($text)) {
            return false;
        }

        // Laravel format: base64(JSON with iv/value/mac)
        try {
            $decoded = base64_decode($text, true);
            if ($decoded !== false) {
                $json = json_decode($decoded, true);
                if (is_array($json) && isset($json['iv']) && isset($json['value']) && isset($json['mac'])) {
                    return true;
                }
            }
        } catch (\Exception $e) {
            // Not Laravel format
        }

        // Node format: base64(iv + ciphertext)
        // 16-byte IV + at least 16-byte block => ~44 base64 chars minimum
        if (strlen($text) >= 44) {
            $decoded = base64_decode($text, true);
            if ($decoded !== false && strlen($decoded) >= 32) {
                // Encrypted binary usually has non-printable bytes
                $nonPrintable = preg_match('/[^\x20-\x7E]/', $decoded);
                if ($nonPrintable) {
                    return true;
                }
            }
        }

        return false;
    }
}
