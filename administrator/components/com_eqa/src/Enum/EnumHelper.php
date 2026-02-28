<?php
namespace Kma\Component\Eqa\Administrator\Enum;

use PHPSQLParser\Test\Parser\selectTest;

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
