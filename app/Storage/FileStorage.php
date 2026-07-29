<?php
namespace App\Storage;

use App\Helper\Crypto;
use App\Helper\IPLocator;

class FileStorage implements StorageInterface
{
    private $dataPath;
    private $ipDataPath;

    public function __construct(string $dataPath, string $ipDataPath)
    {
        $this->dataPath   = rtrim($dataPath, '/');
        $this->ipDataPath = $ipDataPath;
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

    /**
     * 加载封禁IP列表
     */
    public function loadForbiddenIPList(): array
    {
        $file = $this->dataPath . '/forbiddenIPList.dat';
        if (!file_exists($file)) {
            @touch($file);
        }
        $str = @file_get_contents($file);
        if ($str === false || $str === '') {
            return [];
        }
        $str = Crypto::decrypt($str);
        if ($str === '') {
            return [];
        }
        $result = json_decode($str, true);
        return ($result !== null) ? $result : [];
    }

    /**
     * 保存封禁IP列表
     */
    public function saveForbiddenIPList(array $list): void
    {
        $file = $this->dataPath . '/forbiddenIPList.dat';
        $str = json_encode($list);
        $str = Crypto::encrypt($str);
        @file_put_contents($file, $str);
    }
}
