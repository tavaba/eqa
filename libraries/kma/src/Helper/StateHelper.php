<?php
namespace Kma\Library\Kma\Helper;
defined('_JEXEC') or die();

use Joomla\CMS\HTML\HTMLHelper;
use Joomla\CMS\Language\Text;

/**
 * Nguồn duy nhất (single source of truth) cho cơ chế quản lý trạng thái của
 * các đối tượng do component quản lý.
 *
 * Bộ giá trị bám sát Joomla core (#__content.state) để tận dụng được toàn bộ
 * hạ tầng sẵn có: HTMLHelper 'jgrid.published', Table::publish(),
 * AdminController::publish() với các task publish/unpublish/archive/trash.
 *
 * Phân biệt hai bộ trạng thái:
 *   - STATES_FULL  : dành cho các thực thể DANH MỤC (môn học, khóa học, phòng,
 *                    cán bộ...). Đây là nhóm cần khái niệm 'lưu trữ' để thôi
 *                    sử dụng cho dữ liệu mới nhưng vẫn giữ được dữ liệu lịch sử.
 *   - STATES_BASIC : dành cho các thực thể VẬN HÀNH (kỳ thi, ca thi, phòng thi,
 *                    lớp học phần...). Nhóm này đã có vòng đời nghiệp vụ riêng
 *                    nên chỉ cần bật/tắt.
 *
 * @since 1.0.5
 */
abstract class StateHelper
{
    /*
     * States (stick to Joomla's state codes)
     */
    const int STATE_UNPUBLISHED = 0;
    const int STATE_PUBLISHED   = 1;
    const int STATE_ARCHIVED    = 2;
    const int STATE_TRASHED     = -2;

    /**
     * Giá trị đặc biệt của BỘ LỌC trạng thái, mang nghĩa 'không áp điều kiện nào'.
     * Không bao giờ được ghi xuống CSDL.
     *
     * @var string
     * @since 1.0.5
     */
    const string FILTER_ALL = '*';

    /**
     * Giá trị của BỘ LỌC trạng thái khi người dùng chưa chọn gì.
     *
     * Được khai báo tường minh thay vì dùng chuỗi rỗng trần, để phân biệt rõ ba
     * tình huống trong {@see \Kma\Library\Kma\Model\ListModel::applyStateFilter()}:
     * chưa chọn (FILTER_DEFAULT), chọn 'tất cả' (FILTER_ALL), và chọn một trạng
     * thái cụ thể.
     *
     * @var string
     * @since 1.0.5
     */
    const string FILTER_DEFAULT = '';

    /**
     * Bộ trạng thái đầy đủ, dùng cho các thực thể danh mục.
     *
     * @var int[]
     * @since 1.0.5
     */
    const array STATES_FULL = [
        self::STATE_PUBLISHED,
        self::STATE_UNPUBLISHED,
        self::STATE_ARCHIVED,
        self::STATE_TRASHED,
    ];

    /**
     * Bộ trạng thái rút gọn, dùng cho các thực thể vận hành.
     *
     * @var int[]
     * @since 1.0.5
     */
    const array STATES_BASIC = [
        self::STATE_PUBLISHED,
        self::STATE_UNPUBLISHED,
    ];

    /**
     * Các trạng thái được hiển thị MẶC ĐỊNH trong danh sách quản trị, tức là khi
     * người dùng chưa chọn gì ở bộ lọc trạng thái.
     *
     * Giống hệt hành vi của Joomla với article/category: ẩn 'Đã lưu trữ' và
     * 'Thùng rác', hiện phần còn lại. Quản trị viên vẫn thấy ngay các bản ghi
     * vừa tạm ngừng — nếu chỉ hiện 'Đang dùng' thì rất dễ tưởng nhầm là mất dữ liệu.
     *
     * PHÂN BIỆT với {@see self::getSelectableStates()}: hàm đó trả về tập trạng
     * thái được phép xuất hiện trong DROPDOWN CHỌN ĐỐI TƯỢNG khi tạo/sửa dữ liệu
     * (chỉ 'Đang dùng'). Hai khái niệm khác nhau, không được dùng lẫn.
     *
     * @var int[]
     * @since 1.0.5
     */
    const array STATES_DEFAULT_VISIBLE = [
        self::STATE_PUBLISHED,
        self::STATE_UNPUBLISHED,
    ];

    /**
     * Nhãn tiếng Việt của từng trạng thái.
     *
     * Cố ý KHÔNG dùng text key của Joomla (JPUBLISHED, JARCHIVED...) vì các nhãn
     * đó mang ngữ nghĩa 'xuất bản nội dung', không sát với ngữ cảnh quản lý danh
     * mục của com_eqa/com_survey.
     *
     * @param   int  $state  Mã trạng thái.
     *
     * @return  string
     * @since   1.0.5
     */
    public static function getLabel(int $state): string
    {
        return match ($state) {
	        self::STATE_PUBLISHED   => Text::_('JPUBLISHED'),
	        self::STATE_UNPUBLISHED => Text::_('JUNPUBLISHED'),
	        self::STATE_ARCHIVED    => Text::_('JARCHIVED'),
	        self::STATE_TRASHED     => Text::_('JTRASHED'),
	        default                 => Text::_('JUNDEFINED'),
        };
    }

    /**
     * Nhãn theo chuẩn Joomla, dùng khi cần đồng bộ với giao diện core.
     *
     * Đã bổ sung nhánh 'default' — trước đây thiếu, khiến dữ liệu cũ mang giá trị
     * NULL hoặc giá trị lạ gây Fatal error (UnhandledMatchError).
     *
     * @param   int  $state  Mã trạng thái.
     *
     * @return  string
     * @since   1.0.0
     */
    public static function decodeState(int $state): string
    {
        return match ($state) {
            self::STATE_UNPUBLISHED => Text::_('JUNPUBLISHED'),
            self::STATE_PUBLISHED   => Text::_('JPUBLISHED'),
            self::STATE_ARCHIVED    => Text::_('JARCHIVED'),
            self::STATE_TRASHED     => Text::_('JTRASHED'),
            default                 => Text::_('JUNDEFINED'),
        };
    }

    /**
     * Lớp CSS Bootstrap để hiển thị trạng thái dưới dạng badge.
     *
     * @param   int  $state  Mã trạng thái.
     *
     * @return  string
     * @since   1.0.5
     */
    public static function getBadgeClass(int $state): string
    {
        return match ($state) {
            self::STATE_PUBLISHED   => 'bg-success',
            self::STATE_UNPUBLISHED => 'bg-secondary',
            self::STATE_ARCHIVED    => 'bg-info',
            self::STATE_TRASHED     => 'bg-danger',
            default                 => 'bg-dark',
        };
    }

    /**
     * Kiểm tra một giá trị có phải là mã trạng thái hợp lệ hay không.
     *
     * @param   mixed     $value    Giá trị cần kiểm tra.
     * @param   int[]     $allowed  Tập trạng thái được chấp nhận. Rỗng = STATES_FULL.
     *
     * @return  bool
     * @since   1.0.5
     */
    public static function isValid(mixed $value, array $allowed = []): bool
    {
        if (!is_numeric($value)) {
            return false;
        }

        $allowed = $allowed ?: self::STATES_FULL;

        return in_array((int) $value, $allowed, true);
    }

    /**
     * Ép một giá trị bất kỳ về một mã trạng thái hợp lệ.
     *
     * Dùng ở Table::store() để chặn giá trị rác lọt xuống CSDL, và ở ListModel
     * để phòng thủ trước tham số bộ lọc do người dùng tự sửa trên URL.
     *
     * @param   mixed     $value     Giá trị cần chuẩn hóa.
     * @param   int[]     $allowed   Tập trạng thái được chấp nhận. Rỗng = STATES_FULL.
     * @param   int|null  $fallback  Giá trị trả về khi không hợp lệ. Null = STATE_PUBLISHED.
     *
     * @return  int
     * @since   1.0.5
     */
    public static function sanitize(mixed $value, array $allowed = [], ?int $fallback = null): int
    {
        $allowed  = $allowed ?: self::STATES_FULL;
        $fallback = $fallback ?? self::STATE_PUBLISHED;

        if (self::isValid($value, $allowed)) {
            return (int) $value;
        }

        return $fallback;
    }

    /**
     * Phân tích khai báo tập trạng thái thành mảng int hợp lệ.
     *
     * Chấp nhận:
     *   - chuỗi CSV lấy từ thuộc tính XML của form field, ví dụ "1,0,2"
     *   - mảng số
     *   - chuỗi rỗng/null → trả về $default
     *
     * @param   mixed   $value    Khai báo cần phân tích.
     * @param   int[]   $default  Giá trị trả về khi $value rỗng. Rỗng = STATES_FULL.
     *
     * @return  int[]  Mảng đã loại trùng, giữ nguyên thứ tự khai báo.
     * @since   1.0.5
     */
    public static function normalizeStates(mixed $value, array $default = []): array
    {
        $default = $default ?: self::STATES_FULL;

        if ($value === null || $value === '' || $value === []) {
            return $default;
        }

        $raw = is_array($value) ? $value : explode(',', (string) $value);

        $states = [];
        foreach ($raw as $item) {
            $item = is_string($item) ? trim($item) : $item;
            if (self::isValid($item)) {
                $states[] = (int) $item;
            }
        }

        return $states ? array_values(array_unique($states)) : $default;
    }

    /**
     * Sinh danh sách option cho form field trạng thái.
     *
     * @param   int[]  $states  Các trạng thái cần sinh option. Rỗng = STATES_FULL.
     *
     * @return  array  Mảng option của HTMLHelper.
     * @since   1.0.5
     */
    public static function getOptions(array $states = []): array
    {
        $states  = $states ?: self::STATES_FULL;
        $options = [];

        foreach ($states as $state) {
            $options[] = HTMLHelper::_('select.option', (string) $state, self::getLabel((int) $state));
        }

        return $options;
    }

    /**
     * Các trạng thái được hiển thị mặc định trong DROPDOWN CHỌN ĐỐI TƯỢNG
     * (môn học, khóa học, phòng...) khi tạo/sửa dữ liệu.
     *
     * CẢNH BÁO: đừng nhầm với {@see self::STATES_DEFAULT_VISIBLE} — hằng đó dùng
     * cho bộ lọc DANH SÁCH quản trị và có thêm 'Tạm ngừng'. Ở đây cố ý chỉ có
     * 'Đang dùng': một đối tượng đã tạm ngừng thì không được phép chọn cho dữ
     * liệu mới nữa. Nếu thêm STATE_UNPUBLISHED vào đây, môn học đã tạm ngừng sẽ
     * lại xuất hiện trong danh sách chọn khi tạo lớp học phần.
     *
     * @return  int[]
     * @since   1.0.5
     */
    public static function getSelectableStates(): array
    {
        return [self::STATE_PUBLISHED];
    }

    /**
     * Các trạng thái được hiển thị mặc định trong DANH SÁCH quản trị, đã giới hạn
     * theo tập trạng thái mà thực thể hỗ trợ.
     *
     * @param   int[]  $supportedStates  Tập trạng thái thực thể hỗ trợ. Rỗng = STATES_FULL.
     *
     * @return  int[]  Luôn khác rỗng; nếu phép giao cho kết quả rỗng thì trả về [STATE_PUBLISHED].
     * @since   1.0.5
     */
    public static function getDefaultVisibleStates(array $supportedStates = []): array
    {
        $supportedStates = $supportedStates ?: self::STATES_FULL;

        $states = array_values(array_intersect(self::STATES_DEFAULT_VISIBLE, $supportedStates));

        return $states ?: [self::STATE_PUBLISHED];
    }
}
