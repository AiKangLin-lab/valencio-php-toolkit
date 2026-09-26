<?php

declare(strict_types=1);

namespace Valencio\PhpToolkit\Tests;

use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use Valencio\PhpToolkit\Generate;

/**
 * Generate 单元测试
 */
final class GenerateTest extends TestCase
{
    // ========== uuidV4 ==========

    public function testUuidV4Format (): void
    {
        $uuid = Generate::uuidV4();

        $this->assertMatchesRegularExpression(
            '/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/',
            $uuid
        );
    }

    public function testUuidV4LowercaseGuaranteed (): void
    {
        // 独立断言：任何一次生成都必须是小写。
        for ($i = 0; $i < 100; $i++) {
            $uuid = Generate::uuidV4();

            $this->assertSame($uuid, strtolower($uuid));
        }
    }

    public function testUuidV4VersionNibbleIs4 (): void
    {
        for ($i = 0; $i < 100; $i++) {
            $uuid = Generate::uuidV4();
            $this->assertSame('4', $uuid[14], 'version nibble must be 4');
        }
    }

    public function testUuidV4VariantBitsAreRfc4122 (): void
    {
        for ($i = 0; $i < 100; $i++) {
            $uuid = Generate::uuidV4();
            $variantChar = $uuid[19];

            $this->assertContains(
                $variantChar,
                ['8', '9', 'a', 'b'],
                'variant char must be 8/9/a/b'
            );
        }
    }

    public function testUuidV4GeneratesDistinctValues (): void
    {
        $values = [];

        for ($i = 0; $i < 1000; $i++) {
            $values[Generate::uuidV4()] = true;
        }

        $this->assertCount(1000, $values);
    }

    // ========== randomString ==========

    public function testRandomStringDefaultAlphabet (): void
    {
        $result = Generate::randomString(64);

        $this->assertSame(64, strlen($result));
        $this->assertMatchesRegularExpression('/^[A-Za-z0-9]+$/', $result);
    }

    public function testRandomStringCustomAlphabet (): void
    {
        $result = Generate::randomString(32, 'ABC');

        $this->assertSame(32, strlen($result));
        $this->assertMatchesRegularExpression('/^[ABC]+$/', $result);
    }

    public function testRandomStringLengthExact (): void
    {
        $this->assertSame(1, strlen(Generate::randomString(1)));
        $this->assertSame(100, strlen(Generate::randomString(100)));
    }

    public function testRandomStringDefaultLengthIs16 (): void
    {
        $this->assertSame(16, strlen(Generate::randomString()));
    }

    public function testRandomStringInvalidLengthThrows (): void
    {
        $this->expectException(InvalidArgumentException::class);

        Generate::randomString(0);
    }

    public function testRandomStringNegativeLengthThrows (): void
    {
        $this->expectException(InvalidArgumentException::class);

        Generate::randomString(-1);
    }

    public function testRandomStringEmptyAlphabetThrows (): void
    {
        $this->expectException(InvalidArgumentException::class);

        Generate::randomString(8, '');
    }

    public function testRandomStringSingleDistinctCharAlphabetThrows (): void
    {
        $this->expectException(InvalidArgumentException::class);

        Generate::randomString(8, 'aaaa');
    }

    public function testRandomStringRepeatedAlphabetCharsAreDeduplicated (): void
    {
        // 'aabb' 去重后等价于 'ab'，输出只应包含 a / b。
        $result = Generate::randomString(64, 'aabb');

        $this->assertSame(64, strlen($result));
        $this->assertMatchesRegularExpression('/^[ab]+$/', $result);
    }

    public function testRandomStringNonAsciiAlphabetThrows (): void
    {
        $this->expectException(InvalidArgumentException::class);

        Generate::randomString(8, '中文abc');
    }

    public function testRandomStringMultiByteAlphabetThrows (): void
    {
        $this->expectException(InvalidArgumentException::class);

        Generate::randomString(8, 'abé');
    }

    // ========== randomNumber ==========

    public function testRandomNumberExactLength (): void
    {
        $this->assertSame(6, strlen(Generate::randomNumber()));
        $this->assertSame(1, strlen(Generate::randomNumber(1)));
        $this->assertSame(20, strlen(Generate::randomNumber(20)));
    }

    public function testRandomNumberContainsOnlyDigits (): void
    {
        for ($i = 0; $i < 50; $i++) {
            $this->assertMatchesRegularExpression('/^\d{8}$/', Generate::randomNumber(8));
        }
    }

    public function testRandomNumberSupportsLeadingZeros (): void
    {
        // 长度 4、采样 500 次，无前导零的概率为 0.9^500，可忽略。
        $hasLeadingZero = false;

        for ($i = 0; $i < 500; $i++) {
            if (str_starts_with(Generate::randomNumber(4), '0')) {
                $hasLeadingZero = true;
                break;
            }
        }

        $this->assertTrue($hasLeadingZero, 'randomNumber must be able to produce leading zeros');
    }

    public function testRandomNumberLargeLengthDoesNotOverflow (): void
    {
        // 远超 int 范围的长度，验证逐位生成不受整数溢出影响。
        $result = Generate::randomNumber(30);

        $this->assertSame(30, strlen($result));
        $this->assertMatchesRegularExpression('/^\d{30}$/', $result);
    }

    public function testRandomNumberInvalidLengthThrows (): void
    {
        $this->expectException(InvalidArgumentException::class);

        Generate::randomNumber(0);
    }

    // ========== verificationCode ==========

    public function testVerificationCodeDefaultLengthIs6 (): void
    {
        $code = Generate::verificationCode();

        $this->assertSame(6, strlen($code));
        $this->assertMatchesRegularExpression('/^\d{6}$/', $code);
    }

    public function testVerificationCodeCustomLength (): void
    {
        $code = Generate::verificationCode(4);

        $this->assertSame(4, strlen($code));
        $this->assertMatchesRegularExpression('/^\d{4}$/', $code);
    }

    // ========== secureToken ==========

    public function testSecureTokenIsUrlSafe (): void
    {
        for ($i = 0; $i < 50; $i++) {
            $token = Generate::secureToken();

            $this->assertMatchesRegularExpression('/^[A-Za-z0-9_-]+$/', $token);
            $this->assertStringNotContainsString('+', $token);
            $this->assertStringNotContainsString('/', $token);
            $this->assertStringNotContainsString('=', $token);
        }
    }

    public function testSecureTokenDefaultLengthMatches32Bytes (): void
    {
        // 32 bytes = 256 bit，Base64 编码后去掉 1 个填充符 = 43 字符。
        $this->assertSame(43, strlen(Generate::secureToken()));
    }

    public function testSecureTokenByteLengthControlsOutputLength (): void
    {
        // 16 bytes = 128 bit，Base64 编码后正好 22 字符无填充。
        $this->assertSame(22, strlen(Generate::secureToken(16)));
    }

    public function testSecureTokenWithPrefix (): void
    {
        $token = Generate::secureToken(prefix: 'tk_');

        $this->assertStringStartsWith('tk_', $token);
        $this->assertSame(43 + 3, strlen($token));
    }

    public function testSecureTokenInvalidBytesThrows (): void
    {
        $this->expectException(InvalidArgumentException::class);

        Generate::secureToken(0);
    }

    public function testSecureTokenGeneratesDistinctValues (): void
    {
        $values = [];

        for ($i = 0; $i < 100; $i++) {
            $values[Generate::secureToken()] = true;
        }

        $this->assertCount(100, $values);
    }

    // ========== secureKey ==========

    public function testSecureKey32BytesYields64HexChars (): void
    {
        $key = Generate::secureKey();

        $this->assertSame(64, strlen($key));
        $this->assertMatchesRegularExpression('/^[0-9a-f]{64}$/', $key);
    }

    public function testSecureKeyOnlyLowercaseHex (): void
    {
        for ($i = 0; $i < 50; $i++) {
            $this->assertMatchesRegularExpression('/^[0-9a-f]+$/', Generate::secureKey(16));
        }
    }

    public function testSecureKeyWithPrefix (): void
    {
        $key = Generate::secureKey(prefix: 'sk_');

        $this->assertStringStartsWith('sk_', $key);
        $this->assertSame(64 + 3, strlen($key));
    }

    public function testSecureKeyInvalidBytesThrows (): void
    {
        $this->expectException(InvalidArgumentException::class);

        Generate::secureKey(-1);
    }

    // ========== orderNumber ==========

    public function testOrderNumberFormat (): void
    {
        $number = Generate::orderNumber();

        // 14 位时间 + 6 位随机数字。
        $this->assertMatchesRegularExpression('/^\d{20}$/', $number);
    }

    public function testOrderNumberTimePartMatchesCurrentTime (): void
    {
        $before = date('YmdHis');
        $number = Generate::orderNumber();
        $after = date('YmdHis');

        $timePart = substr($number, 0, 14);

        $this->assertTrue(
            $timePart === $before || $timePart === $after,
            "time part {$timePart} should match {$before} or {$after}"
        );
    }

    public function testOrderNumberWithPrefix (): void
    {
        $number = Generate::orderNumber('ORD');

        $this->assertMatchesRegularExpression('/^ORD\d{20}$/', $number);
    }

    public function testOrderNumberRandomPartIsSixDigits (): void
    {
        $number = Generate::orderNumber();

        $this->assertMatchesRegularExpression('/^\d{6}$/', substr($number, 14, 6));
    }

    // ========== serialNumber ==========

    public function testSerialNumberFormat (): void
    {
        $number = Generate::serialNumber('PAY');

        // PAY + 14 位时间 + 默认 8 位随机数字。
        $this->assertMatchesRegularExpression('/^PAY\d{22}$/', $number);
    }

    public function testSerialNumberCustomRandomLength (): void
    {
        $number = Generate::serialNumber('REFUND', 4);

        $this->assertMatchesRegularExpression('/^REFUND\d{18}$/', $number);
    }

    public function testSerialNumberTimePartMatchesCurrentTime (): void
    {
        $before = date('YmdHis');
        $number = Generate::serialNumber('TASK');
        $after = date('YmdHis');

        $timePart = substr($number, 4, 14);

        $this->assertTrue(
            $timePart === $before || $timePart === $after,
            "time part {$timePart} should match {$before} or {$after}"
        );
    }

    public function testSerialNumberEmptyPrefixThrows (): void
    {
        $this->expectException(InvalidArgumentException::class);

        Generate::serialNumber('');
    }

    public function testSerialNumberInvalidRandomLengthThrows (): void
    {
        $this->expectException(InvalidArgumentException::class);

        Generate::serialNumber('PAY', 0);
    }

    // ========== 类设计约束 ==========

    public function testGenerateCannotBeInstantiated (): void
    {
        $this->expectException(\Error::class);

        new Generate();
    }
}
