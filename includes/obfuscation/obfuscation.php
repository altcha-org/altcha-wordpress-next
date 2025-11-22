<?php

/**
 * Copyright (c) 2025 BAU Software s.r.o., Czechia. All rights reserved.
 *
 * This file is part of the Software licensed under the
 * END-USER LICENSE AGREEMENT (EULA)
 *
 * License Summary:
 * - Source is available for review, testing, debugging, and evaluation.
 * - Distribution of the Software or source code is prohibited.
 * - Modifications are allowed only for internal testing/debugging,
 *   not for production or deployment.
 *
 * The full license text can be found in the LICENSE file
 * distributed with this source code.
 *
 * Unauthorized distribution, modification, or production use of
 * this Software is strictly prohibited.
 */

if (! defined("ABSPATH")) exit;

class AltchaObfuscation
{
  static function obfuscate(
    string $string,
    string $key = "",
    int $number = 0,
    int $maxNumber = 10_000
  ): string {
    $string = filter_var($string, FILTER_SANITIZE_SPECIAL_CHARS);
    if (! function_exists("openssl_encrypt")) {
      return "";
    }
    if ($number < 1) {
      try {
        $number = random_int(1, $maxNumber);
      } catch (Throwable $throwable) {
        $number = 1;
      }
    }
    $keyHash = hash("sha256", $key, true);
    $iv = self::number_to_iv($number);
    $tag = "";
    $ciphertext = openssl_encrypt(
      $string,
      "aes-256-gcm",
      $keyHash,
      OPENSSL_RAW_DATA,
      $iv,
      $tag,
      "",
      16
    );
    if ($ciphertext === false) {
      return "";
    }
    return base64_encode($ciphertext . $tag);
  }

  private static function number_to_iv(int $number, int $length = 12): string
  {
    $bytes = "";
    for ($i = 0; $i < $length; $i++) {
      $bytes .= chr($number % 256);
      $number = intdiv($number, 256);
    }

    return $bytes;
  }
}
