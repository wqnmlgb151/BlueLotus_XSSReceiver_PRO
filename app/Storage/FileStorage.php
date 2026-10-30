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
        if (!is_readable($this->ipDataPath)) {
            error_log('XSSReceiver: IP database unavailable');
        }
    }

    /**
     * 保存XSS记录
     */
    public function save(array $record, string $id): bool
    {
        return $this->write($record, $id, false);
    }

    /** 创建绝不覆盖现有 ID；更新通过 save 原子替换。 */
    public function create(array $record, string $id): bool
    {
        return $this->write($record, $id, true);
    }

    private function write(array $record, string $id, bool $createOnly): bool
    {
        if (!RecordId::isValid($id)) {
            error_log('XSSReceiver: invalid record ID on save');
            return false;
        }
        $file = $this->dataPath . '/' . $id . '.php';
        $json = json_encode($record);
        if ($json === false) {
            error_log('XSSReceiver: record encoding failed');
            return false;
        }
        $encrypted = Crypto::encrypt($json);
        $content = '<?php exit();?>' . $encrypted;
        // Same directory: atomic publication must not cross filesystems.
        $temporary = $this->dataPath . '/.record-' . bin2hex(random_bytes(16)) . '.php';
        $handle = @fopen($temporary, 'xb');
        if ($handle === false) {
            error_log('XSSReceiver: temporary record creation failed');
            return false;
        }
        $written = @fwrite($handle, $content) === strlen($content);
        $flushed = @fflush($handle);
        $closed = fclose($handle);
        // link fails if the destination exists, unlike rename. No unsafe fallback.
        $saved = $written && $flushed && $closed && ($createOnly
            ? @link($temporary, $file) : @rename($temporary, $file));
        if (is_file($temporary) && !@unlink($temporary)) {
            error_log('XSSReceiver: temporary record cleanup failed');
        }
        if (!$saved) {
            error_log('XSSReceiver: record write failed');
        }
        return $saved;
    }

    /**
     * 读取单条XSS记录
     */
    public function load(string $id): ?array
    {
        if (!RecordId::isValid($id)) {
            return null;
        }

        $file = $this->dataPath . '/' . $id . '.php';
        if (!file_exists($file)) {
            return null;
        }

        $info = @file_get_contents($file);
        if ($info === false || strncmp($info, '<?php exit();?>', 15) !== 0) {
            error_log('XSSReceiver: record read or prefix validation failed');
            return null;
        }

        $info = substr($info, 15);
        $info = Crypto::decrypt($info);

        if (!preg_match('/^[A-Za-z0-9\x00-\x80~!@#$%&_+-=:";\'<>,\/"\[\]\\\^\.\|\?\*\+\(\)\{\}\s]+$/', $info)) {
            error_log('XSSReceiver: record plaintext validation failed');
            return null;
        }

        $data = json_decode($info, true);
        if (!is_array($data) || !isset($data['request_time'])
            || (!is_string($data['request_time']) && !is_int($data['request_time']))
            || !preg_match('/\A[0-9]{10}\z/', (string) $data['request_time'])
            || (isset($data['user_IP']) && !is_string($data['user_IP']))) {
            error_log('XSSReceiver: record JSON validation failed');
            return null;
        }

        // 文件名是记录身份的唯一来源，旧记录无需改写。
        $data['id'] = $id;
        // 仅补充返回值，不在读取时改写历史记录。
        if (!isset($data['location'])) {
            $data['location'] = IPLocator::convertIP($data['user_IP'] ?? '', $this->ipDataPath);
        }

        return $data;
    }

    /**
     * 删除单条记录
     */
    public function delete(string $id): bool
    {
        if (!RecordId::isValid($id)) {
            return false;
        }
        $file = $this->dataPath . '/' . $id . '.php';
        $deleted = @unlink($file);
        if (!$deleted) {
            error_log('XSSReceiver: record delete failed');
        }
        return $deleted;
    }

    /**
     * 清空所有记录
     */
    public function clearAll(): bool
    {
        if (!is_dir($this->dataPath)) {
            error_log('XSSReceiver: record directory unavailable');
            return false;
        }
        $files = glob($this->dataPath . '/*.php');
        if ($files === false) {
            error_log('XSSReceiver: record directory scan failed');
            return false;
        }
        $success = true;
        foreach ($files as $file) {
            $id = basename($file, '.php');
            if (RecordId::isValid($id) && !$this->delete($id)) {
                $success = false;
            }
        }
        return $success;
    }

    /**
     * 获取所有记录ID列表
     */
    public function listIds(): array
    {
        $files = glob($this->dataPath . '/*.php');
        if ($files === false) {
            error_log('XSSReceiver: record directory scan failed');
            return [];
        }
        $list  = [];
        foreach ($files as $file) {
            $filename = basename($file, '.php');
            if (RecordId::isValid($filename)) {
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
        $files = $this->listIds();
        rsort($files, SORT_STRING);
        $list = [];
        foreach ($files as $id) {
            $data = $this->load($id);
            if ($data !== null) {
                $list[] = $data;
            }
        }
        return $list;
    }

}
