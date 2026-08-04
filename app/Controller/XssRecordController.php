<?php
namespace App\Controller;

use App\Service\XssRecordService;

final class XssRecordController
{
    public static function handle(string $cmd)    {
        $service = new XssRecordService();
        $project = $_GET['project'] ?? null;

        switch ($cmd) {
            // list / id_list 返回全部记录（不按项目过滤，保持旧版行为）
            case 'list':
                return $service->listRecords($project !== null ? ['project' => $project] : []);

            case 'id_list':
                return $service->listIds();

            case 'get':
                return isset($_GET['id']) ? ($service->getRecord($_GET['id']) ?? false) : false;

            case 'del':
                return isset($_GET['id']) ? $service->deleteRecord($_GET['id']) : false;

            case 'clear':
                return $service->clearRecords();

            case 'search':
                $keyword = $_GET['keyword'] ?? '';
                $filters = ['project' => $project ?? 'default'];
                if ($keyword !== '') {
                    $filters['keyword'] = $keyword;
                }
                return $service->listRecords($filters);

            case 'stats':
                return $service->getStats($project ?? 'default');

            case 'export_json':
                header('Content-Type: application/json; charset=utf-8');
                header('Content-Disposition: attachment; filename="xss_export.json"');
                echo $service->exportJson($project ?? 'default');
                exit;

            case 'export_csv':
                header('Content-Type: text/csv; charset=utf-8');
                header('Content-Disposition: attachment; filename="xss_export.csv"');
                echo "\xEF\xBB\xBF" . $service->exportCsv($project ?? 'default');
                exit;

            default:
                return false;
        }
    }
}
