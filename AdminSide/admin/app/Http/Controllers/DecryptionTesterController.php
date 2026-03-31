<?php

namespace App\Http\Controllers;

use Illuminate\Encryption\Encrypter;
use Illuminate\Http\Request;

class DecryptionTesterController extends Controller
{
    public function index()
    {
        return view('decryption-tester');
    }

    public function decrypt(Request $request)
    {
        $validated = $request->validate([
            'encrypted_value' => ['required', 'string', 'max:50000'],
            'app_key' => ['nullable', 'string', 'max:255'],
        ]);

        $encryptedValue = trim($validated['encrypted_value']);
        $providedAppKey = trim((string) ($validated['app_key'] ?? ''));
        $rawAppKey = $providedAppKey !== '' ? $providedAppKey : (string) config('app.key');

        $key = $this->normalizeAppKey($rawAppKey);
        if ($key === null) {
            return back()
                ->withInput()
                ->with('decryption_error', 'Invalid APP_KEY format. Use a 32-byte key or base64:<value> that decodes to 32 bytes.');
        }

        $result = $this->decryptMultiLayer($encryptedValue, $key);

        if (!$result['success']) {
            return back()
                ->withInput()
                ->with('decryption_error', 'Unable to decrypt value using the provided APP_KEY.');
        }

        return back()->withInput()->with('decryption_result', [
            'decrypted_text' => $result['text'],
            'layers' => $result['layers'],
            'formats' => $result['formats'],
            'used_custom_key' => $providedAppKey !== '',
        ]);
    }

    private function decryptMultiLayer(string $value, string $key): array
    {
        $current = $value;
        $layers = 0;
        $formats = [];

        for ($i = 0; $i < 20; $i++) {
            $pass = $this->decryptOnce($current, $key);
            if ($pass === null) {
                break;
            }

            if ($pass['value'] === $current) {
                break;
            }

            $current = $pass['value'];
            $layers++;
            $formats[] = $pass['format'];
        }

        if ($layers === 0) {
            return ['success' => false];
        }

        return [
            'success' => true,
            'text' => $current,
            'layers' => $layers,
            'formats' => $formats,
        ];
    }

    private function decryptOnce(string $encrypted, string $key): ?array
    {
        $laravel = $this->tryLaravelDecrypt($encrypted, $key);
        if ($laravel !== null) {
            return [
                'value' => $laravel,
                'format' => 'laravel',
            ];
        }

        $node = $this->tryNodeDecrypt($encrypted, $key);
        if ($node !== null) {
            return [
                'value' => $node,
                'format' => 'node',
            ];
        }

        return null;
    }

    private function tryLaravelDecrypt(string $encrypted, string $key): ?string
    {
        try {
            $encrypter = new Encrypter($key, (string) config('app.cipher', 'AES-256-CBC'));
            return $encrypter->decryptString($encrypted);
        } catch (\Throwable $e) {
            return null;
        }
    }

    private function tryNodeDecrypt(string $encrypted, string $key): ?string
    {
        try {
            $combined = base64_decode($encrypted, true);
            if ($combined === false || strlen($combined) < 17) {
                return null;
            }

            $iv = substr($combined, 0, 16);
            $cipherText = substr($combined, 16);

            $decrypted = openssl_decrypt(
                $cipherText,
                'aes-256-cbc',
                $key,
                OPENSSL_RAW_DATA,
                $iv
            );

            if ($decrypted === false) {
                while (openssl_error_string()) {
                    // Drain OpenSSL error queue before next attempt.
                }
                return null;
            }

            return $decrypted;
        } catch (\Throwable $e) {
            return null;
        }
    }

    private function normalizeAppKey(string $appKey): ?string
    {
        if (str_starts_with($appKey, 'base64:')) {
            $decoded = base64_decode(substr($appKey, 7), true);
            return $decoded !== false && strlen($decoded) === 32 ? $decoded : null;
        }

        if (strlen($appKey) === 32) {
            return $appKey;
        }

        $decoded = base64_decode($appKey, true);
        if ($decoded !== false && strlen($decoded) === 32) {
            return $decoded;
        }

        return null;
    }
}