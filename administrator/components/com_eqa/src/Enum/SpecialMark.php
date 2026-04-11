<?php
namespace Kma\Component\Eqa\Administrator\Enum;

use Kma\Library\Kma\Enum\EnumHelper;

enum SpecialMark: int
{
	use EnumHelper;
	case N25 = -25;     //Nghỉ học quá 25% số buổi
	case N100 = -100;   //Nghỉ học 100% số buổi
	case TKD = -10;     //Thi giữa kỳ không đạt
	public function getLabel(): string
	{
		return match ($this) {
			self::N25 => 'N25',
			self::N100 => 'N100',
			self::TKD => 'TKD',
		};
	}

	public static function tryFromText(string $text): self|null
	{
		return match (mb_strtolower(trim($text))) {
			'n25' => self::N25,
			'n100', 'nghỉ học', 'nghi hoc' => self::N100,
			'tkd', 'tkđ', 'trượt gk' => self::TKD,
			default => null,
		};
	}
}
