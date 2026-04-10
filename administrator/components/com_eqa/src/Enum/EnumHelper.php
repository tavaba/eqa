<?php
namespace Kma\Component\Eqa\Administrator\Enum;

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
