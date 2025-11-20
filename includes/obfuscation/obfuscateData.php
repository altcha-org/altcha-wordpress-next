<?php

if (! defined('ABSPATH')) exit;

function obfuscateText(string $text): string
{
    return obfuscateString($text);
}

function obfuscateMail(string $mail): string
{
    if (strpos($mail, 'mailto:') !== 0) {
        $mail = 'mailto:' . $mail;
    }
    return obfuscateString($mail);
}

function obfuscateTelephone(string $telephone): string
{
    if (strpos($telephone, 'tel:') !== 0) {
        $telephone = 'tel:' . $telephone;
    }
    return obfuscateString($telephone);
}

/**
 * Port of scripts/obfuscate.ts (MIT) using AES-256-GCM + SHA-256 key derivation.
 */
function obfuscateString(string $string): string
{
    $string = filter_var($string, FILTER_SANITIZE_SPECIAL_CHARS);
    
    if (! function_exists('openssl_encrypt')) {
        return $string;
    }

    $maxNumber = (int) (getenv('MAX_NUMBER') ?: 10000);
    if ($maxNumber <= 0) {
        $maxNumber = 10000;
    }

    $numberEnv = getenv('NUMBER');
    $number = null;
    if ($numberEnv !== false && $numberEnv !== '') {
        $number = (int) $numberEnv;
        if ($number < 0) {
            $number = 0;
        }
    }

    if ($number === null) {
        try {
            $number = random_int(1, $maxNumber);
        } catch (Throwable $throwable) {
            $number = 1;
        }
    }

    $key = getenv('KEY');
    if ($key === false) {
        $key = '';
    }

    $keyHash = hash('sha256', $key, true);
    $iv = obfuscateNumberToIv($number);

    $tag = '';
    $ciphertext = openssl_encrypt(
        $string,
        'aes-256-gcm',
        $keyHash,
        OPENSSL_RAW_DATA,
        $iv,
        $tag,
        '',
        16
    );

    if ($ciphertext === false) {
        return $string;
    }

    return base64_encode($ciphertext . $tag);
}

function obfuscateNumberToIv(int $number, int $length = 12): string
{
    $bytes = '';
    for ($i = 0; $i < $length; $i++) {
        $bytes .= chr($number % 256);
        $number = intdiv($number, 256);
    }

    return $bytes;
}
