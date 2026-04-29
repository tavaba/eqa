<?php
namespace Kma\Component\Kmail\Administrator\Enum;

use Kma\Library\Kma\Enum\EnumHelper;

enum ObjectType : int
{
	use EnumHelper;

	case Template = 1001;
	case Campaign = 1002;

	/**
	 * Trả về nhãn (tên case)
	 */
	public function getLabel(): string
	{
		return $this->name;
	}

	/**
	 * Tìm kiếm một case dựa trên tên (không phân biệt hoa thường)
	 * * @param string $objectName Tên cần tìm (vd: "exam", "EXAM", "Learner")
	 * @return self|null
	 */
	public static function tryFromName(string $objectName): ?self
	{
		foreach (self::cases() as $case) {
			// So sánh không phân biệt hoa thường bằng strcasecmp
			if (strcasecmp($case->name, $objectName) === 0) {
				return $case;
			}
		}

		return null;
	}
}
