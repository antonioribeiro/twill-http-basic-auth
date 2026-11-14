<?php

namespace A17\TwillHttpBasicAuth\Models\Behaviors;

use Illuminate\Support\Facades\Crypt;

/**
 * Credentials are stored encrypted and read back strictly: a value that cannot be decrypted throws (`DecryptException`), it is
 * never returned as it is. A blank value is stored as null.
 */
trait Encrypt
{
    public function encrypt(string|null $value): string|null
    {
        return blank($value) ? null : Crypt::encryptString($value);
    }

    public function decrypt(string|null $value): string|null
    {
        return blank($value) ? null : Crypt::decryptString($value);
    }
}
