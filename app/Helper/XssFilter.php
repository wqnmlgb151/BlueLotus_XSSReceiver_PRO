<?php
namespace App\Helper;

class XssFilter
{
    public const MAX_DEPTH = 32;
    /**
     * XSS过滤: 对单个字符串进行HTML实体编码
     */
    public static function stripStr(string $str): string
    {
        return htmlspecialchars($str, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }

    /**
     * XSS过滤: 递归处理数组的键和值
     */
    public static function stripArr(array $arr, int $depth = 0): array
    {
        if ($depth > self::MAX_DEPTH) {
            throw new \InvalidArgumentException('Parameter nesting limit exceeded');
        }
        $newArr = [];
        foreach ($arr as $k => $v) {
            if (!is_array($v) && !is_scalar($v) && $v !== null) {
                throw new \InvalidArgumentException('Unsupported parameter type');
            }
            $newArr[self::stripStr((string) $k)] = is_array($v)
                ? self::stripArr($v, $depth + 1) : self::stripStr((string) $v);
        }
        return $newArr;
    }

    /**
     * 对XSS记录内容进行安全转义 (用于后台展示)
     * 纵深防御: 即使存储时已过滤, 显示时再次过滤
     */
    public static function safeDisplay(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
    }
}
