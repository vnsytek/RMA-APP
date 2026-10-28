<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * Accepts the current Vietnamese numbering plan: a 10-digit mobile number (03x/05x/07x/08x/09x)
 * or an 11-digit landline (0 + 3-digit area code + 7-digit number), written with any mix of
 * spaces, dots or dashes, and with or without a +84 / 0084 country code.
 */
class VietnamesePhoneNumber implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        $digits = preg_replace('/\D/', '', (string) $value);
        $digits = preg_replace('/^(0084|84)/', '0', $digits);

        $isMobile = preg_match('/^0(3[2-9]|5[25689]|7[06-9]|8[1-9]|9\d)\d{7}$/', $digits);
        $isLandline = preg_match('/^02\d{9}$/', $digits);

        if (! $isMobile && ! $isLandline) {
            $fail('Số điện thoại không đúng định dạng Việt Nam (VD: 0901 234 567 hoặc 0225 3845 566).');
        }
    }
}
