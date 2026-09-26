<?php
// +----------------------------------------------------------------------
// | Success, real success,
// | is being willing to do the things that other people are not.
// +----------------------------------------------------------------------
// | Author:    Valencio Kang <ailin1219@foxmail.com>
// +----------------------------------------------------------------------
// | FileName:  Generate.php
// +----------------------------------------------------------------------
// | Year:      2026
// +----------------------------------------------------------------------
declare(strict_types=1);

namespace Valencio\PhpToolkit;

use InvalidArgumentException;
use Random\RandomException;

/**
 * 通用生成器
 *
 * 无状态的随机值、标识符和业务编号生成工具。
 * 所有安全随机能力均基于 random_bytes() / random_int()。
 */
final class Generate
{
    /**
     * 默认随机字符串字符集（大小写字母 + 数字）。
     */
    private const DEFAULT_ALPHABET = 'ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789';

    /**
     * 数字字符集。
     */
    private const DIGITS = '0123456789';

    /**
     * 禁止实例化。
     */
    private function __construct ()
    {
    }

    /**
     * 生成标准 RFC 4122 兼容的 UUID v4。
     *
     * 格式固定为 xxxxxxxx-xxxx-4xxx-yxxx-xxxxxxxxxxxx，全小写。
     *
     * @return string
     * @throws RandomException
     */
    public static function uuidV4 (): string
    {
        $bytes = random_bytes(16);

        // version 4：高 4 位固定为 0100。
        $bytes[6] = chr((ord($bytes[6]) & 0x0f) | 0x40);

        // variant：RFC 4122，高 2 位固定为 10。
        $bytes[8] = chr((ord($bytes[8]) & 0x3f) | 0x80);

        $hex = bin2hex($bytes);

        return sprintf(
            '%s-%s-%s-%s-%s',
            substr($hex, 0, 8),
            substr($hex, 8, 4),
            substr($hex, 12, 4),
            substr($hex, 16, 4),
            substr($hex, 20, 12)
        );
    }

    /**
     * 生成指定长度的随机字符串。
     *
     * 每个字符严格等概率出现，
     * 自定义字符集中的重复字符会先去重。
     *
     * @param int $length 字符串长度，必须大于 0
     * @param string|null $alphabet 字符集，仅支持可打印 ASCII 单字节字符；null 时使用大小写字母 + 数字；至少需包含 2 个不同字符
     * @return string
     * @throws RandomException
     */
    public static function randomString (int $length = 16, ?string $alphabet = null): string
    {
        self::assertPositiveLength($length, 'length');

        $chars = $alphabet === null
            ? self::DEFAULT_ALPHABET
            : self::normalizeAlphabet($alphabet);

        $result = '';

        for ($i = 0; $i < $length; $i++) {
            $result .= self::randomChar($chars);
        }

        return $result;
    }

    /**
     * 生成指定长度的随机数字字符串。
     *
     * 返回 string 以保留前导零，例如 "003821"。
     * 逐位生成以避免大长度时的整数溢出。
     *
     * @param int $length 数字长度，必须大于 0
     * @return string
     * @throws RandomException
     */
    public static function randomNumber (int $length = 6): string
    {
        self::assertPositiveLength($length, 'length');

        $result = '';

        for ($i = 0; $i < $length; $i++) {
            $result .= random_int(0, 9);
        }

        return $result;
    }

    /**
     * 生成数字验证码。
     *
     * 语义型 API，底层复用 randomNumber()，默认 6 位，
     * 允许前导零。
     *
     * @param int $length 验证码长度，必须大于 0
     * @return string
     * @throws RandomException
     */
    public static function verificationCode (int $length = 6): string
    {
        return self::randomNumber($length);
    }

    /**
     * 生成适用于密码重置、邀请、临时 API 等场景的 URL-safe token。
     *
     * 使用 URL-safe Base64 编码（+ -> -，/ -> _，移除末尾 =），
     * 输出仅包含 [A-Za-z0-9_-]。
     *
     * @param int $bytes 随机字节数（非最终字符串长度），必须大于 0；默认 32 bytes = 256-bit 熵
     * @param string|null $prefix 可选前缀，直接拼接，不添加分隔符
     * @return string
     * @throws RandomException
     */
    public static function secureToken (int $bytes = 32, ?string $prefix = null): string
    {
        self::assertPositiveLength($bytes, 'bytes');

        return ($prefix ?? '') . self::urlSafeBase64(random_bytes($bytes));
    }

    /**
     * 生成 Secret / Key。
     *
     * 使用 bin2hex() 编码，固定小写 hex，
     * 例如 32 bytes 输出 64 个 hex 字符。
     *
     * @param int $bytes 随机字节数（非最终字符串长度），必须大于 0；默认 32 bytes
     * @param string|null $prefix 可选前缀，直接拼接，不添加分隔符
     * @return string
     * @throws RandomException
     */
    public static function secureKey (int $bytes = 32, ?string $prefix = null): string
    {
        self::assertPositiveLength($bytes, 'bytes');

        return ($prefix ?? '') . bin2hex(random_bytes($bytes));
    }

    /**
     * 生成时间型订单编号。
     *
     * 格式：[prefix] + YYYYMMDDHHIISS + 6 位随机数字。
     * 该方法提供高碰撞抗性，但不保证跨进程、跨机器绝对唯一；
     * 如业务要求严格唯一，应由数据库、Redis、Snowflake 等外部机制保证。
     *
     * @param string|null $prefix 可选前缀
     * @return string
     * @throws RandomException
     */
    public static function orderNumber (?string $prefix = null): string
    {
        return ($prefix ?? '') . date('YmdHis') . self::randomNumber(6);
    }

    /**
     * 生成通用业务流水号。
     *
     * 格式：prefix + YYYYMMDDHHIISS + 指定位数随机数字。
     * 该方法提供高碰撞抗性，但不保证跨进程、跨机器绝对唯一；
     * 如业务要求严格唯一，应由数据库、Redis、Snowflake 等外部机制保证。
     *
     * @param string $prefix 业务前缀，不能为空字符串，例如 PAY、REFUND、TASK
     * @param int $randomLength 随机数字部分长度，必须大于 0
     * @return string
     * @throws RandomException
     */
    public static function serialNumber (string $prefix, int $randomLength = 8): string
    {
        if ($prefix === '') {
            throw new InvalidArgumentException('Serial number prefix must not be empty.');
        }

        self::assertPositiveLength($randomLength, 'randomLength');

        return $prefix . date('YmdHis') . self::randomNumber($randomLength);
    }

    /**
     * 从字符集中等概率取一个字符。
     *
     * random_int() 本身即为密码学安全且均匀分布的整数生成器，
     * 直接在 [0, 字符数-1] 区间取值，无需额外处理。
     *
     * @param string $chars 已去重的 ASCII 单字节字符集
     * @throws RandomException
     */
    private static function randomChar (string $chars): string
    {
        return $chars[random_int(0, strlen($chars) - 1)];
    }

    /**
     * 规范化自定义字符集。
     *
     * 仅支持可打印 ASCII 单字节字符（0x20-0x7E），
     * 去除重复字符以避免概率分布异常，
     * 并校验至少包含 2 个不同字符。
     */
    private static function normalizeAlphabet (string $alphabet): string
    {
        if (preg_match('/[^\x20-\x7e]/', $alphabet) === 1) {
            throw new InvalidArgumentException(
                'Alphabet must only contain printable single-byte ASCII characters.'
            );
        }

        $alphabet = implode('', array_unique(str_split($alphabet)));

        if (strlen($alphabet) < 2) {
            throw new InvalidArgumentException(
                'Alphabet must contain at least 2 distinct characters.'
            );
        }

        return $alphabet;
    }

    /**
     * URL-safe Base64 编码。
     *
     * + 替换为 -，/ 替换为 _，并移除末尾的 = 填充符。
     */
    private static function urlSafeBase64 (string $bytes): string
    {
        return rtrim(strtr(base64_encode($bytes), '+/', '-_'), '=');
    }

    /**
     * 校验长度参数必须为正整数。
     */
    private static function assertPositiveLength (int $value, string $name): void
    {
        if ($value <= 0) {
            throw new InvalidArgumentException(
                sprintf('"%s" must be greater than 0, got %d.', $name, $value)
            );
        }
    }
}
