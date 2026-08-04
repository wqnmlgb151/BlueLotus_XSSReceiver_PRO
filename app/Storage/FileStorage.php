<?php
namespace App\Storage;

use App\Helper\Crypto;
use App\Helper\IPLocator;

class FileStorage implements StorageInterface
{
    private $dataPath;
    private $ipDataPath;

    private static function resolvePath(string $path): string
    {
        // 绝对路径直接返回，相对路径基于项目根目录解析
        if (strpos($path, '/') === 0 || (PHP_OS_FAMILY === 'Windows' && preg_match('/^[A-Za-z]:[\\\\\\/]/', $path))) {
            return $path;
        }
        return __DIR__ . '/../..' . '/' . $path;
    }

    public function __construct(string $dataPath, string $ipDataPath)
    {
        $this->dataPath   = rtrim(self::resolvePath($dataPath), '/');
        $this->ipDataPath = self::resolvePath($ipDataPath);
    }

    /**
     * 保存XSS记录
     */
    public function save(array $record, string $id): bool
    {
        $file = $this->dataPath . '/' . $id . '.php';
        $json = json_encode($record);
        $encrypted = Crypto::encrypt($json);
        return file_put_contents($file, '<?php exit();?>' . $encrypted) !== false;
    }

    /**
     * 读取单条XSS记录
     */
    public function load(string $id): ?array
    {
        if (!preg_match('/^[0-9]{10}$/', $id)) {
            return null;
        }

        $file = $this->dataPath . '/' . $id . '.php';
        if (!file_exists($file)) {
            return null;
        }

        $info = @file_get_contents($file);
        if ($info === false || strncmp($info, '<?php exit();?>', 15) !== 0) {
            return null;
        }

        $info = substr($info, 15);
        $info = Crypto::decrypt($info);

        if (!preg_match('/^[A-Za-z0-9\x00-\x80~!@#$%&_+-=:";\'<>,\/"\[\]\\\^\.\|\?\*\+\(\)\{\}\s]+$/', $info)) {
            return null;
        }

        $data = json_decode($info, true);
        if ($data === null || !isset($data['request_time'])) {
            return null;
        }

        // 首次加载时补充IP归属地
        if (!isset($data['location'])) {
            $data['location'] = IPLocator::convertIP($data['user_IP'], $this->ipDataPath);
            $this->save($data, $id);
        }

        return $data;
    }

    /**
     * 删除单条记录
     */
    public function delete(string $id): bool
    {
        if (!preg_match('/^[0-9]{10}$/', $id)) {
            return false;
        }
        $file = $this->dataPath . '/' . $id . '.php';
        return @unlink($file);
    }

    /**
     * 清空所有记录
     */
    public function clearAll(): bool
    {
        $files = glob($this->dataPath . '/*.php');
        foreach ($files as $file) {
            @unlink($file);
        }
        return true;
    }

    /**
     * 获取所有记录ID列表
     */
    public function listIds(): array
    {
        $files = glob($this->dataPath . '/*.php');
        $list  = [];
        foreach ($files as $file) {
            $filename = basename($file, '.php');
            if (preg_match('/^[0-9]{10}$/', $filename)) {
                $list[] = $filename;
            }
        }
        return $list;
    }

    /**
     * 获取所有记录 (含完整数据)
     */
    public function listAll(): array
    {
        $files = glob($this->dataPath . '/*.php');
        arsort($files);
        $list = [];
        foreach ($files as $file) {
            $filename = basename($file, '.php');
            $data = $this->load($filename);
            if ($data !== null) {
                $list[] = $data;
            }
        }
        return $list;
    }

}
