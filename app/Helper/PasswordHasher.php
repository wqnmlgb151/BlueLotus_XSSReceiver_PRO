<?php
namespace App\Helper;

final class PasswordHasher
{
    /** 密码盐值 —— 与 static/js/login.min.js 中保持同步 */
    public const SALT = '!KTMdg#^^I6Z!deIVR#SgpAI6qTN7oVl';

    /**
     * 生成存储于 config.php PASS 常量中的 hash。
     * 算法: md5(salt . plain . salt) 迭代 3 次
     */
    public static function hash(string $plain): string
    {
        $key = md5(self::SALT . $plain . self::SALT);
        $key = md5(self::SALT . $key . self::SALT);
        return md5(self::SALT . $key . self::SALT);
    }

    /**
     * 校验客户端挑战应答。
     * 算法: md5(salt . storedHash . challenge . salt) 迭代 3 次
     */
    public static function verifyChallenge(string $storedHash, string $challenge, string $clientResponse): bool
    {
        $key = md5(self::SALT . $storedHash . $challenge . self::SALT);
        $key = md5(self::SALT . $key . $challenge . self::SALT);
        $key = md5(self::SALT . $key . $challenge . self::SALT);
        return hash_equals($key, $clientResponse);
    }
}
