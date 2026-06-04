<?php
/**
 * @package     Com_Eqa
 * @subpackage  Installation Script
 *
 * Thứ tự thực thi của Joomla là: preflight() → copy file → update() → chạy SQL → postflight()
 * Yêu cầu: Joomla 5.0+, PHP 8.1+, MySQL 8.0+
 *
 * Quên hết các version từ 2.0.7 trở về trước. Coi 2.0.8 là điểm xuất phát
 * để xóa bớt file update SQL, và đơn giản hóa file script.php này.
 *
 */

defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\CMS\Installer\InstallerScript;
use Joomla\CMS\Log\Log;

class Com_EqaInstallerScript extends InstallerScript
{
    // =========================================================================
    // Cấu hình
    // =========================================================================

    /** Joomla minimum version */
    protected $minimumJoomla = '5.0';

    /** PHP minimum version */
    protected $minimumPhp = '8.1';

    /**
     * Version đang cài trước khi update, được đọc trong preflight() khi
     * manifest_cache vẫn còn chứa version cũ.
     *
     * postflight() dùng giá trị này để quyết định migration nào cần chạy,
     * vì lúc postflight() thực thi Joomla đã ghi version mới vào manifest_cache
     * khiến getInstalledVersion() luôn trả về version mới.
     */
    private string $previousVersion = '0.0.0';


    /**
     * Các thư mục gốc của component cần xóa sạch trước khi Joomla copy file mới.
     * Đường dẫn tính từ JPATH_ROOT, dùng dấu '/'.
     *
     * Thư mục media/com_eqa được quản lý riêng bởi Joomla media installer,
     * KHÔNG liệt kê ở đây để tránh xóa nhầm asset đang được dùng.
     */
    private const COMPONENT_DIRS = [
        'administrator/components/com_eqa',
        'components/com_eqa',
    ];

    // =========================================================================
    // Joomla Installer Hooks
    // =========================================================================

    /**
     * Chạy TRƯỚC khi Joomla copy file mới lên server.
     *
     * Xóa sạch toàn bộ thư mục component hiện có để đảm bảo:
     * - Không còn file cũ bị đổi tên/xóa/di chuyển tồn tại song song
     * - Version mới là bản sạch hoàn toàn sau khi Joomla copy lại
     *
     * Thư mục không tồn tại → bỏ qua (an toàn cho cài lần đầu).
     */
    public function preflight($type, $parent): bool
    {
        if ($type === 'update') {
            // Đọc version cũ TRƯỚC KHI Joomla copy file mới.
            // Đây là thời điểm duy nhất manifest_cache còn chứa version cũ.
            // postflight() sẽ dùng $this->previousVersion để quyết định
            // migration nào cần chạy.
            $this->previousVersion = $this->getInstalledVersion();
            $this->logInfo("Phiên bản hiện tại (trước khi update): {$this->previousVersion}");

            $this->clearComponentDirs();
        }

        return true;
    }

    /**
     * Chạy sau khi Joomla copy file mới lên server NHƯNG TRƯỚC KHI chạy SQL update.
     *
     * Chỉ dùng cho các migration không phụ thuộc vào SQL update
     * (thực hiện bằng PHP thuần, không cần file .sql chạy trước).
     *
     * LƯU Ý: Các migration cần SQL chạy trước (ví dụ: tạo cột mới rồi mới populate dữ liệu)
     * phải được đặt trong postflight(), không phải ở đây.
     */
    public function update($parent): bool
    {

        return true;
    }

    /**
     * Chạy SAU KHI Joomla đã thực thi xong toàn bộ file SQL update.
     *
     * Đây là nơi đúng để thực hiện các migration cần dữ liệu/cấu trúc từ SQL update:
     * ví dụ populate dữ liệu vào cột vừa được tạo bởi file .sql.
     *
     * @param  string $type   Loại action: 'install' | 'update' | 'discover_install'
     * @param  object $parent Installer object
     * @return bool
     */
    public function postflight(string $type, $parent): bool
    {
        // Chỉ chạy khi là update (không chạy khi install lần đầu)
        if ($type !== 'update') {
            return true;
        }


		return true;
    }

    // =========================================================================
    // Dọn dẹp thư mục cũ
    // =========================================================================

    /**
     * Xóa sạch nội dung bên trong từng thư mục trong COMPONENT_DIRS.
     *
     * Lưu ý: Chỉ xóa NỘI DUNG bên trong (file + subfolder), KHÔNG xóa
     * thư mục gốc. Điều này để Joomla installer có thể copy file mới vào
     * đúng vị trí mà không cần tạo lại thư mục.
     *
     * Thư mục không tồn tại → bỏ qua.
     * Lỗi xóa một item → ghi log, tiếp tục xử lý item tiếp theo.
     */
    private function clearComponentDirs(): void
    {
        $root    = rtrim(JPATH_ROOT, DIRECTORY_SEPARATOR);
        $deleted = 0;
        $failed  = 0;

        foreach (self::COMPONENT_DIRS as $relativeDir) {
            $fullPath = $root . DIRECTORY_SEPARATOR
                . str_replace('/', DIRECTORY_SEPARATOR, $relativeDir);

            if (!is_dir($fullPath)) {
                Log::add(
                    "com_eqa: Thư mục không tồn tại, bỏ qua: {$relativeDir}",
                    Log::INFO,
                    'com_eqa'
                );
                continue;
            }

            $this->logInfo("Đang xóa nội dung thư mục: {$relativeDir}");

            [$dirDeleted, $dirFailed] = $this->clearDirContents($fullPath);
            $deleted += $dirDeleted;
            $failed  += $dirFailed;

            $this->logInfo(
                "Thư mục {$relativeDir}: đã xóa {$dirDeleted} item"
                . ($dirFailed > 0 ? ", thất bại {$dirFailed} item" : '')
                . '.'
            );
        }

        if ($failed === 0) {
            $this->logInfo("Dọn dẹp thư mục cũ hoàn tất: đã xóa {$deleted} item.");
        } else {
            $this->logWarning(
                "Dọn dẹp thư mục cũ: {$deleted} item xóa thành công, "
                . "{$failed} item thất bại. Kiểm tra quyền truy cập file hệ thống."
            );
        }
    }

    /**
     * Xóa toàn bộ nội dung bên trong một thư mục (đệ quy), giữ lại thư mục gốc.
     *
     * Duyệt theo thứ tự CHILD_FIRST: xóa file/subfolder con trước,
     * sau đó xóa subfolder cha — đảm bảo thứ tự đúng khi xóa cây thư mục.
     *
     * @return array{int, int} [$deletedCount, $failedCount]
     */
    private function clearDirContents(string $dirPath): array
    {
        $deleted = 0;
        $failed  = 0;

        $items = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($dirPath, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::CHILD_FIRST
        );

        foreach ($items as $item) {
            $itemPath = $item->getRealPath();

            if ($item->isDir()) {
                $ok = @rmdir($itemPath);
            } else {
                $ok = @unlink($itemPath);
            }

            if ($ok) {
                $deleted++;
            } else {
                $failed++;
                Log::add(
                    "com_eqa: Không thể xóa: {$itemPath}",
                    Log::WARNING,
                    'com_eqa'
                );
            }
        }

        return [$deleted, $failed];
    }

    /*
     * Đọc version đang cài của component từ #__extensions.manifest_cache.
     * Trả về '0.0.0' nếu không tìm thấy (cài lần đầu).
     */
    private function getInstalledVersion(): string
    {
        $db = Factory::getDbo();

        $query = $db->getQuery(true)
            ->select('`manifest_cache`')
            ->from('`#__extensions`')
            ->where('`element` = ' . $db->quote('com_eqa'))
            ->where('`type`    = ' . $db->quote('component'));

        $cache = $db->setQuery($query)->loadResult();
        $data  = json_decode($cache ?? '{}', true);

        return $data['version'] ?? '0.0.0';
    }

	// =========================================================================
    // Logging helpers
    // =========================================================================

    private function logInfo(string $msg): void
    {
        Factory::getApplication()->enqueueMessage("com_eqa: {$msg}", 'info');
        Log::add("com_eqa: {$msg}", Log::INFO, 'com_eqa');
    }

    private function logWarning(string $msg): void
    {
        Factory::getApplication()->enqueueMessage("com_eqa: {$msg}", 'warning');
        Log::add("com_eqa: {$msg}", Log::WARNING, 'com_eqa');
    }

    private function logError(string $msg): void
    {
        Factory::getApplication()->enqueueMessage("com_eqa: {$msg}", 'error');
        Log::add("com_eqa: {$msg}", Log::ERROR, 'com_eqa');
    }
}
