<?php
namespace App\Helper;

class Crypto
{
    /**
     * 加密数据
     */
    public static function encrypt(string $info): string
    {
        if (ENCRYPT_ENABLE) {
            if (ENCRYPT_TYPE === 'AES') {
                require_once dirname(__DIR__, 2) . '/aes.php';
                $info = AESEncryptCtr($info, ENCRYPT_PASS);
            } else {
                require_once dirname(__DIR__, 2) . '/rc4.php';
                $info = base64_encode(rc4($info, ENCRYPT_PASS));
            }
        } else {
            $info = base64_encode($info);
        }
        return $info;
    }

    /**
     * 解密数据
     */
    public static function decrypt(string $info): string
    {
        if (ENCRYPT_ENABLE) {
            if (ENCRYPT_TYPE === 'AES') {
                require_once dirname(__DIR__, 2) . '/aes.php';
                $info = AESDecryptCtr($info, ENCRYPT_PASS);
            } else {
                require_once dirname(__DIR__, 2) . '/rc4.php';
                $info = rc4(base64_decode($info), ENCRYPT_PASS);
            }
        } else {
            $info = base64_decode($info);
        }
        return $info;
    }
}
