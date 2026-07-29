<?php
namespace App\Config;

class AppConfig
{
    /** 后台登录密码hash */
    public static function pass(): string { return PASS; }

    /** XSS数据存储路径 */
    public static function dataPath(): string { return DATA_PATH; }

    /** JS模板存储路径 */
    public static function jsTemplatePath(): string { return JS_TEMPLATE_PATH; }

    /** 我的JS存储路径 */
    public static function myJsPath(): string { return MY_JS_PATH; }

    /** 是否启用加密 */
    public static function encryptEnable(): bool { return ENCRYPT_ENABLE; }

    /** 加密密码 */
    public static function encryptPass(): string { return ENCRYPT_PASS; }

    /** 加密方法 */
    public static function encryptType(): string { return ENCRYPT_TYPE; }

    /** 是否启用KeepSession */
    public static function keepSession(): bool { return defined('KEEP_SESSION') && KEEP_SESSION; }

    /** 是否启用管理员IP校验 */
    public static function adminIPCheckEnable(): bool { return defined('ADMIN_IP_CHECK_ENABLE') && ADMIN_IP_CHECK_ENABLE; }

    /** 是否启用XFF */
    public static function xffEnable(): bool { return defined('XFF_ENABLE') && XFF_ENABLE; }

    /** IP数据库路径 */
    public static function ipDataPath(): string { return IPDATA_PATH; }

    /** 是否启用邮件通知 */
    public static function mailEnable(): bool { return defined('MAIL_ENABLE') && MAIL_ENABLE; }

    /** 获取项目配置列表 */
    public static function projects(): array
    {
        if (defined('PROJECTS')) {
            return PROJECTS;
        }
        return ['default' => ['name' => '默认项目', 'token' => null]];
    }

    /** 根据token获取项目标识 */
    public static function getProjectByToken(string $token): ?string
    {
        foreach (self::projects() as $key => $proj) {
            if (isset($proj['token']) && $proj['token'] === $token) {
                return $key;
            }
        }
        return null;
    }
}
