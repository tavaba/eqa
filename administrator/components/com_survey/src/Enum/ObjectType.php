<?php

namespace Kma\Component\Survey\Administrator\Enum;

enum ObjectType : int
{
	use EnumHelper;

	//Respondent
	case Respondent=1001;
	case Unit=1002;
	case CreditClass=1003;

	//Survey
	case Topic=2001;
	case Form=2002;
	case Survey=2003;
	case Campaign=2004;

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
