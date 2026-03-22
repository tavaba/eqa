<?php
namespace Kma\Component\Survey\Administrator\Enum;


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
