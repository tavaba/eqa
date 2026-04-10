<?php
namespace Kma\Library\Kma\Service;

use Joomla\CMS\Factory;
use Joomla\Database\DatabaseDriver;
use Kma\Library\Kma\DataObject\LogEntry;
use Kma\Library\Kma\Helper\DatetimeHelper;

class LogService
{
	private bool $enabled = true;

	public function __construct(
		private DatabaseDriver $db,
		private string         $tableName,
	)
	{
	}

	// ── Bật/tắt logging ────────────────────────────────────────────────────

	public function enable(): static
	{
		$this->enabled = true;

		return $this;
	}

	public function disable(): static
	{
		$this->enabled = false;

		return $this;
	}

	public function isEnabled(): bool
	{
		return $this->enabled;
	}

	public function getLogTableName(): string
	{
		return $this->tableName;
	}

	/**
	 * Thực thi callback mà không ghi log.
	 * Tự động restore trạng thái enabled sau khi callback kết thúc.
	 */
	public function withoutLogging(callable $fn): mixed
	{
		$wasEnabled = $this->enabled;
		$this->disable();
		try
		{
			return $fn();
		} finally
		{
			if ($wasEnabled) $this->enable();
		}
	}

	// ── Ghi log ────────────────────────────────────────────────────────────

	/**
	 * Ghi một bản ghi log từ LogEntry.
	 *
	 * Các trường userId và username trong LogEntry được ưu tiên.
	 * Nếu chưa được set (= 0 hoặc rỗng), tự động lấy từ user đang đăng nhập.
	 * @return int ID của log entry vừa được tạo ra
	 * @throws \RuntimeException nếu INSERT thất bại
	 */
	public function write(LogEntry $entry): int
	{
		if (!$this->enabled)
		{
			return 0;
		}

		$this->resolveUser($entry);
		$this->resolveIp($entry);

		$row = (object) [
			'user_id'       => $entry->userId ?: null,
			'username'      => $entry->username ?: null,
			'action'        => $entry->action,
			'object_type'   => $entry->objectType,
			'object_id'     => $entry->objectId,
			'object_title'  => $entry->objectTitle,
			'is_success'    => (int) $entry->isSuccess,
			'error_message' => $entry->errorMessage,
			'old_value'     => $this->encodeValue($entry->oldValue),
			'new_value'     => $this->encodeValue($entry->newValue),
			'extra_data'    => $this->encodeValue($entry->extraData),
			'ip_address'    => $this->encodeIp($entry->ipAddress),
			'created_at'    => $entry->created_at ?? DatetimeHelper::getCurrentUtcTime(),
		];

		try
		{
			$this->db->insertObject($this->tableName, $row);
		}
		catch (\RuntimeException $e)
		{
			Factory::getApplication()->enqueueMessage($e->getMessage(), 'error');
			return 0;
		}

		return (int) $this->db->insertid();
	}

	// ── Helpers nội bộ ─────────────────────────────────────────────────────

	/**
	 * Nếu LogEntry chưa có userId/username, lấy từ user đang đăng nhập.
	 * @since 1.0.3
	 */
	private function resolveUser(LogEntry $entry): void
	{
		if (!empty($entry->userId && !empty($entry->username)))
		{
			return;
		}

		try
		{
			$user = Factory::getApplication()->getIdentity();
			if (empty($entry->userId))
			{
				$entry->userId = (int) $user->id;
			}
			if (empty($entry->username))
			{
				$name            = trim($user->name ?? '');
				$uname           = trim($user->username ?? '');
				$entry->username = $name !== '' && $uname !== ''
					? "{$name} ({$uname})"
					: ($name ?: $uname);
			}
		}
		catch (\Throwable)
		{
			// Môi trường CLI hoặc unit test — bỏ qua
		}
	}

	/**
	 * Nếu LogEntry chưa có IpAddress thì lấy từ hệ thống.
	 * Ưu tiên lấy $_SERVER['HTTP_X_FORWARDED_FOR'] để có được địa chỉ
	 * thực của user, thay vì địa chỉ của proxy
	 * @since 1.0.3
	 */
	private function resolveIp(LogEntry $entry): void
	{
		if ($entry->ipAddress !== null) {
			return;
		}

		$forwarded = $_SERVER['HTTP_X_FORWARDED_FOR'] ?? null;
		if ($forwarded) {
			$ip = $this->sanitizeIp(trim(explode(',', $forwarded)[0]));
			if ($ip !== null) {
				$entry->ipAddress = $ip;
				return;
			}
		}

		$candidate = $_SERVER['HTTP_CLIENT_IP']
			?? $_SERVER['REMOTE_ADDR']
			?? null;

		$entry->ipAddress = $candidate !== null
			? $this->sanitizeIp($candidate)
			: null;
	}

	/**
	 * Làm sạch và validate địa chỉ IP.
	 * Xử lý các dạng: IPv4, IPv6, IPv6 có bracket ([::1]),
	 * IPv6 có port ([::1]:8080), IPv4-mapped IPv6 (::ffff:x.x.x.x).
	 * Trả về IP hợp lệ dạng chuẩn, hoặc null nếu không hợp lệ.
	 */
	private function sanitizeIp(string $ip): ?string
	{
		// Xử lý IPv6 dạng [::1] hoặc [::1]:8080
		if (str_starts_with($ip, '[')) {
			$end = strpos($ip, ']');
			if ($end === false) {
				return null;
			}
			$ip = substr($ip, 1, $end - 1);
		}

		// Xử lý IPv4-mapped IPv6: ::ffff:192.168.1.1
		if (str_starts_with(strtolower($ip), '::ffff:')) {
			$candidate = substr($ip, 7);
			if (filter_var($candidate, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
				return $candidate;   // Trả về IPv4 thuần để đơn giản hơn
			}
		}

		// Validate IPv4 hoặc IPv6 thuần
		if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4 | FILTER_FLAG_IPV6)) {
			return $ip;
		}

		return null;
	}

	/**
	 * Chuyển đổi giá trị old/new/extra thành JSON string.
	 * - null      → null  (lưu NULL vào DB)
	 * - string    → giữ nguyên (giả định đã là JSON hoặc plain text)
	 * - array|object → json_encode
	 */
	private function encodeValue(string|array|object|null $value): ?string
	{
		if ($value === null)
		{
			return null;
		}

		if (is_string($value))
		{
			return $value;
		}

		$json = json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

		if ($json === false)
		{
			// Fallback: serialize nếu json_encode thất bại (ví dụ: có binary data)
			return serialize($value);
		}

		return $json;
	}

	/**
	 * Chuyển đổi địa chỉ IP từ string sang BINARY(16).
	 * Hỗ trợ cả IPv4 và IPv6.
	 * Trả về null nếu địa chỉ không hợp lệ hoặc là null.
	 */
	private function encodeIp(?string $ipAddress): ?string
	{
		if ($ipAddress === null || $ipAddress === '')
		{
			return null;
		}

		$binary = inet_pton($ipAddress);

		if ($binary === false)
		{
			return null;
		}

		// inet_pton trả về 4 bytes (IPv4) hoặc 16 bytes (IPv6)
		// Cần pad IPv4 lên 16 bytes để đồng nhất với cột BINARY(16)
		if (strlen($binary) === 4)
		{
			$binary = str_pad($binary, 16, "\x00", STR_PAD_LEFT);
		}

		return $binary;
	}
}