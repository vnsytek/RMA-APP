<?php

namespace Tests\Unit;

use App\Rules\VietnamesePhoneNumber;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class VietnamesePhoneNumberTest extends TestCase
{
    /**
     * @return iterable<string, array{0: string}>
     */
    public static function valid(): iterable
    {
        yield 'mobile, no separators' => ['0901234567'];
        yield 'mobile, spaced like the demo data' => ['0901 234 567'];
        yield 'mobile, another valid prefix' => ['0987654321'];
        yield 'landline, spaced like the demo data' => ['0225 3845 566'];
        yield 'mobile with +84 country code' => ['+84901234567'];
        yield 'mobile with 0084 country code' => ['0084901234567'];
        yield 'mobile with dashes' => ['090-123-4567'];
    }

    /**
     * @return iterable<string, array{0: string}>
     */
    public static function invalid(): iterable
    {
        yield 'too short' => ['090123'];
        yield 'old-style 10-digit landline (031x)' => ['0313513689'];
        yield 'invalid mobile prefix (01x no longer exists)' => ['0112345678'];
        yield 'letters' => ['khong-phai-so'];
        yield 'landline missing a digit' => ['022538455'];
    }

    #[DataProvider('valid')]
    public function test_accepts_valid_numbers(string $phone): void
    {
        $failed = false;
        (new VietnamesePhoneNumber())->validate('phone', $phone, function () use (&$failed) { $failed = true; });

        $this->assertFalse($failed, "expected \"{$phone}\" to pass");
    }

    #[DataProvider('invalid')]
    public function test_rejects_invalid_numbers(string $phone): void
    {
        $failed = false;
        (new VietnamesePhoneNumber())->validate('phone', $phone, function () use (&$failed) { $failed = true; });

        $this->assertTrue($failed, "expected \"{$phone}\" to fail");
    }
}
