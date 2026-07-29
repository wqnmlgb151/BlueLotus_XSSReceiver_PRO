<?php
namespace App\Helper;

class IPLocator
{
    /**
     * 基于纯真IP数据库查询IP归属地
     * 来自Discuz X3.1 function_misc.php, 已适配PHP 8.x
     */
    public static function convertIP(string $ip, string $ipdatafile): string
    {
        $ipaddr = '未知';
        if (!preg_match('/^\d{1,3}\.\d{1,3}\.\d{1,3}\.\d{1,3}$/', $ip)) {
            return $ipaddr;
        }

        $iparray = explode('.', $ip);
        if ($iparray[0] == 10 || $iparray[0] == 127
            || ($iparray[0] == 192 && $iparray[1] == 168)
            || ($iparray[0] == 172 && ($iparray[1] >= 16 && $iparray[1] <= 31))) {
            return '局域网';
        }
        if ($iparray[0] > 255 || $iparray[1] > 255 || $iparray[2] > 255 || $iparray[3] > 255) {
            return '错误ip';
        }

        if (!@file_exists($ipdatafile)) {
            return $ipaddr;
        }

        $fd = @fopen($ipdatafile, 'rb');
        if (!$fd) {
            return 'ip库出错';
        }

        $ipNum = $iparray[0] * 16777216 + $iparray[1] * 65536 + $iparray[2] * 256 + $iparray[3];

        $DataBegin = fread($fd, 4);
        $DataEnd = fread($fd, 4);
        if (!$DataBegin || !$DataEnd) {
            fclose($fd);
            return $ipaddr;
        }

        $ipbegin = unpack('L', $DataBegin);
        if (!is_array($ipbegin)) {
            fclose($fd);
            return $ipaddr;
        }
        $ipbegin = implode('', $ipbegin);
        if ($ipbegin < 0) {
            $ipbegin += pow(2, 32);
        }

        $ipend = unpack('L', $DataEnd);
        if (!is_array($ipend)) {
            fclose($fd);
            return $ipaddr;
        }
        $ipend = implode('', $ipend);
        if ($ipend < 0) {
            $ipend += pow(2, 32);
        }

        $ipAllNum = ($ipend - $ipbegin) / 7 + 1;
        $BeginNum = 0;
        $EndNum = $ipAllNum;
        $ip1num = 0;
        $ip2num = 0;
        $ipAddr1 = '';
        $ipAddr2 = '';

        while ($ip1num > $ipNum || $ip2num < $ipNum) {
            $Middle = intval(($EndNum + $BeginNum) / 2);
            fseek($fd, $ipbegin + 7 * $Middle);
            $ipData1 = fread($fd, 4);
            if (strlen($ipData1) < 4) {
                fclose($fd);
                return '系统错误';
            }
            $ip1num = unpack('L', $ipData1);
            if (!is_array($ip1num)) {
                fclose($fd);
                return '系统错误';
            }
            $ip1num = implode('', $ip1num);
            if ($ip1num < 0) {
                $ip1num += pow(2, 32);
            }

            if ($ip1num > $ipNum) {
                $EndNum = $Middle;
                continue;
            }

            $DataSeek = fread($fd, 3);
            if (strlen($DataSeek) < 3) {
                fclose($fd);
                return '系统错误';
            }
            $DataSeek = unpack('L', $DataSeek . chr(0));
            if (!is_array($DataSeek)) {
                fclose($fd);
                return '系统错误';
            }
            $DataSeek = implode('', $DataSeek);
            fseek($fd, $DataSeek);

            $ipData2 = fread($fd, 4);
            if (strlen($ipData2) < 4) {
                fclose($fd);
                return '系统错误';
            }
            $ip2num = unpack('L', $ipData2);
            if (!is_array($ip2num)) {
                fclose($fd);
                return '系统错误';
            }
            $ip2num = implode('', $ip2num);
            if ($ip2num < 0) {
                $ip2num += pow(2, 32);
            }

            if ($ip2num < $ipNum) {
                if ($Middle == $BeginNum) {
                    fclose($fd);
                    return '未知';
                }
                $BeginNum = $Middle;
            }
        }

        $ipFlag = fread($fd, 1);
        if ($ipFlag == chr(1)) {
            $ipSeek = fread($fd, 3);
            if (strlen($ipSeek) < 3) {
                fclose($fd);
                return '系统错误';
            }
            $ipSeek = unpack('L', $ipSeek . chr(0));
            if (!is_array($ipSeek)) {
                fclose($fd);
                return '系统错误';
            }
            $ipSeek = implode('', $ipSeek);
            fseek($fd, $ipSeek);
            $ipFlag = fread($fd, 1);
        }

        if ($ipFlag == chr(2)) {
            $AddrSeek = fread($fd, 3);
            if (strlen($AddrSeek) < 3) {
                fclose($fd);
                return '系统错误';
            }
            $ipFlag = fread($fd, 1);
            if ($ipFlag == chr(2)) {
                $AddrSeek2 = fread($fd, 3);
                if (strlen($AddrSeek2) < 3) {
                    fclose($fd);
                    return '系统错误';
                }
                $AddrSeek2 = unpack('L', $AddrSeek2 . chr(0));
                if (!is_array($AddrSeek2)) {
                    fclose($fd);
                    return '系统错误';
                }
                $AddrSeek2 = implode('', $AddrSeek2);
                fseek($fd, $AddrSeek2);
            } else {
                fseek($fd, -1, SEEK_CUR);
            }
            while (($char = fread($fd, 1)) != chr(0)) {
                $ipAddr2 .= $char;
            }
            $AddrSeek = unpack('L', $AddrSeek . chr(0));
            if (!is_array($AddrSeek)) {
                fclose($fd);
                return '系统错误';
            }
            $AddrSeek = implode('', $AddrSeek);
            fseek($fd, $AddrSeek);
            while (($char = fread($fd, 1)) != chr(0)) {
                $ipAddr1 .= $char;
            }
        } else {
            fseek($fd, -1, SEEK_CUR);
            while (($char = fread($fd, 1)) != chr(0)) {
                $ipAddr1 .= $char;
            }
            $ipFlag = fread($fd, 1);
            if ($ipFlag == chr(2)) {
                $AddrSeek2 = fread($fd, 3);
                if (strlen($AddrSeek2) < 3) {
                    fclose($fd);
                    return '系统错误';
                }
                $AddrSeek2 = unpack('L', $AddrSeek2 . chr(0));
                if (!is_array($AddrSeek2)) {
                    fclose($fd);
                    return '系统错误';
                }
                $AddrSeek2 = implode('', $AddrSeek2);
                fseek($fd, $AddrSeek2);
            } else {
                fseek($fd, -1, SEEK_CUR);
            }
            while (($char = fread($fd, 1)) != chr(0)) {
                $ipAddr2 .= $char;
            }
        }
        fclose($fd);

        $ipAddr1 = iconv('gb18030', 'utf-8//IGNORE', $ipAddr1);
        if ($ipAddr2) {
            if (ord($ipAddr2[0]) == 2) {
                $ipAddr2 = '';
            } else {
                $ipAddr2 = iconv('gb18030', 'utf-8//IGNORE', $ipAddr2);
            }
        }

        if (preg_match('/http/i', $ipAddr2)) {
            $ipAddr2 = '';
        }

        $ipaddr = $ipAddr1 . $ipAddr2;
        $ipaddr = preg_replace('/CZ88\.NET/is', '', $ipaddr);
        $ipaddr = preg_replace('/^\s*/is', '', $ipaddr);
        $ipaddr = preg_replace('/\s*$/is', '', $ipaddr);
        if (preg_match('/http/i', $ipaddr) || $ipaddr === '') {
            $ipaddr = '未知';
        }

        return htmlspecialchars($ipaddr, ENT_QUOTES, 'UTF-8');
    }
}
