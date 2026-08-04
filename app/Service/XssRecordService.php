<?php
namespace App\Service;

use App\Storage\FileStorage;
use App\Config\AppConfig;

class XssRecordService
{
    private $storage;

    public function __construct()
    {
        $this->storage = new FileStorage(AppConfig::dataPath(), AppConfig::ipDataPath());
    }

    /**
     * 保存XSS记录
     */
    public function saveRecord(array $info, string $project = 'default'): bool
    {
        $info['project'] = $project;
        $id = $info['request_time'] ?? (string) time();
        return $this->storage->save($info, $id);
    }

    /**
     * 获取记录列表 (支持过滤)
     */
    public function listRecords(array $filters = []): array
    {
        $all = $this->storage->listAll();

        if (empty($filters)) {
            return $all;
        }

        return array_values(array_filter($all, function ($record) use ($filters) {
            if (isset($filters['project']) && ($record['project'] ?? 'default') !== $filters['project']) {
                return false;
            }
            if (isset($filters['ip']) && stripos($record['user_IP'] ?? '', $filters['ip']) === false) {
                return false;
            }
            if (isset($filters['keyword'])) {
                $json = json_encode($record);
                if (stripos($json, $filters['keyword']) === false) {
                    return false;
                }
            }
            if (isset($filters['time_from']) && ($record['request_time'] ?? 0) < $filters['time_from']) {
                return false;
            }
            if (isset($filters['time_to']) && ($record['request_time'] ?? 0) > $filters['time_to']) {
                return false;
            }
            if (isset($filters['method']) && ($record['request_method'] ?? '') !== $filters['method']) {
                return false;
            }
            return true;
        }));
    }

    /**
     * 搜索记录
     */
    public function searchRecords(string $keyword, string $project = 'default'): array
    {
        return $this->listRecords(['keyword' => $keyword, 'project' => $project]);
    }

    /**
     * 获取单条记录
     */
    public function getRecord(string $id): ?array
    {
        return $this->storage->load($id);
    }

    /**
     * 获取记录ID列表
     */
    public function listIds(): array
    {
        return $this->storage->listIds();
    }

    /**
     * 删除单条记录
     */
    public function deleteRecord(string $id): bool
    {
        return $this->storage->delete($id);
    }

    /**
     * 清空记录
     */
    public function clearRecords(): bool
    {
        return $this->storage->clearAll();
    }

    /**
     * 获取记录统计
     */
    public function getStats(string $project = 'default'): array
    {
        $all = $this->listRecords(['project' => $project]);
        $today = strtotime('today');
        $week  = strtotime('-7 days');

        $todayCount = 0;
        $weekCount  = 0;
        $ipMap      = [];
        $hourDist   = array_fill(0, 24, 0);

        foreach ($all as $record) {
            $time = (int) ($record['request_time'] ?? 0);
            if ($time >= $today) {
                $todayCount++;
            }
            if ($time >= $week) {
                $weekCount++;
            }
            $ip = $record['user_IP'] ?? 'unknown';
            $ipMap[$ip] = ($ipMap[$ip] ?? 0) + 1;
            $hourDist[(int) date('G', $time)]++;
        }

        arsort($ipMap);
        return [
            'total'       => count($all),
            'today'       => $todayCount,
            'week'        => $weekCount,
            'top_ips'     => array_slice($ipMap, 0, 10),
            'hourly_dist' => $hourDist,
        ];
    }

    /**
     * 导出为JSON
     */
    public function exportJson(string $project = 'default', ?int $timeFrom = null, ?int $timeTo = null): string
    {
        $filters = ['project' => $project];
        if ($timeFrom !== null) {
            $filters['time_from'] = $timeFrom;
        }
        if ($timeTo !== null) {
            $filters['time_to'] = $timeTo;
        }
        $records = $this->listRecords($filters);
        return json_encode($records, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    }

    /**
     * 导出为CSV
     */
    public function exportCsv(string $project = 'default', ?int $timeFrom = null, ?int $timeTo = null): string
    {
        $filters = ['project' => $project];
        if ($timeFrom !== null) {
            $filters['time_from'] = $timeFrom;
        }
        if ($timeTo !== null) {
            $filters['time_to'] = $timeTo;
        }
        $records = $this->listRecords($filters);

        $fp = fopen('php://temp', 'r+');
        fputcsv($fp, ['ID', '时间', 'IP', '位置', '项目', '请求方法', 'URL', 'User-Agent', 'Referer', 'GET参数', 'POST参数', 'Cookie']);

        foreach ($records as $r) {
            fputcsv($fp, [
                self::csvCell($r['request_time'] ?? ''),
                date('Y-m-d H:i:s', (int) ($r['request_time'] ?? 0)),
                self::csvCell($r['user_IP'] ?? ''),
                self::csvCell($r['location'] ?? ''),
                self::csvCell($r['project'] ?? 'default'),
                self::csvCell($r['request_method'] ?? ''),
                self::csvCell($r['request_URI'] ?? ''),
                self::csvCell($r['headers_data']['User-Agent'] ?? ''),
                self::csvCell($r['headers_data']['Referer'] ?? ''),
                self::csvCell(json_encode($r['get_data'] ?? [], JSON_UNESCAPED_UNICODE)),
                self::csvCell(json_encode($r['post_data'] ?? [], JSON_UNESCAPED_UNICODE)),
                self::csvCell(json_encode($r['cookie_data'] ?? [], JSON_UNESCAPED_UNICODE)),
            ]);
        }

        rewind($fp);
        $csv = stream_get_contents($fp);
        fclose($fp);
        return $csv;
    }

    /** 防止CSV公式注入：以 = + - @ 开头的单元格前加制表符 */
    private static function csvCell($value): string
    {
        $str = (string) $value;
        if ($str !== '' && strpbrk($str[0], '=+-@') !== false) {
            return "\t" . $str;
        }
        return $str;
    }
}
