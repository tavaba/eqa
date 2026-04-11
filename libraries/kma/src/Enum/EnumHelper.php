<?php
namespace Kma\Library\Kma\Enum;

trait EnumHelper
{
	public static function getOptions(): array
	{
		$options = [];
		foreach (self::cases() as $case)
			$options[$case->value] = $case->getLabel();
		return $options;
	}
}
