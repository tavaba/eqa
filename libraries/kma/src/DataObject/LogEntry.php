<?php
namespace Kma\Library\Kma\DataObject;

/**
 * Lớp đại diện cho một bản ghi log hệ thống.
 */
class LogEntry
{
	/**
	 * Khởi tạo một đối tượng LogEntry mới sử dụng PHP 8 Constructor Promotion.
	 *
	 * @param int                       $action        Tên hành động (tạo, xem, sửa, xóa, phê duyệt,...)
	 * Cần được xác định thông qua các constants được định nghĩa trước.
	 * @param int                       $objectType    Loại đối tượng bị tác động: 'exam', 'exam_learner',...
	 * Cần được xác định thông qua các constants được định nghĩa trước.
	 * @param bool                      $isSuccess     Cho biết hành động của người dùng có thành công hay không.
	 * @param int|null                  $objectId      ID (có thể là surrogate ID) của đối tượng bị tác động.
	 * Có thể NULL, ví dụ với trường hợp ghi log cho một batch operation.
	 * @param string|null               $objectTitle   Tiêu đề hoặc tên hiển thị của đối tượng tác động.
	 * @param string|null               $errorMessage  Thông báo lỗi chi tiết nếu hành động thất bại (isSuccess = false).
	 * @param string|array|object|null  $oldValue      Giá trị cũ trước khi thay đổi (Cần encode JSON trước khi lưu vào CSDL).
	 * @param string|array|object|null  $newValue      Giá trị mới sau khi thay đổi (Cần encode JSON trước khi lưu vào CSDL).
	 * @param string|array|object|null  $extraData     Các dữ liệu bổ sung khác liên quan đến ngữ cảnh log.
	 * @param int|null                  $userId        User ID của người thực thi hành động.
	 * @param string|null               $username      Có dạng "Họ và tên (username)".
	 * @param string|null               $ipAddress     Địa chỉ IP của người dùng (Có thể là IPv4 hoặc IPv6).
	 * @param string|null               $created_at    Thời điểm tạo bản ghi log (Y-m-d H:i:s).
	 */
	public function __construct(
		public int $action,
		public int $objectType,
		public bool $isSuccess,
		public ?int $objectId = null,
		public ?string $objectTitle = null,
		public ?string $errorMessage = null,
		public string|array|object|null $oldValue = null,
		public string|array|object|null $newValue = null,
		public string|array|object|null $extraData = null,
		public ?int $userId = null,
		public ?string $username = null,
		public ?string $ipAddress = null,
		public ?string $created_at = null,
	) {
		// Thuộc tính tự động gán. Bạn có thể chèn thêm logic xử lý tự động (ví dụ: tự lấy IP nếu null) tại đây.
	}
}