<?php
namespace Kma\Component\Eqa\Administrator\Helper;
defined('_JEXEC') or die();
abstract class TermHelper
{
	const TERM_NONE = 0;
	const TERM_1 = 1;
	const TERM_2 = 2;
	const TERM_3 = 3;
	const TERM_SUMMER = 9;
	public static function decodeTerm(int $term): string
	{
		return match ($term) {
			self::TERM_NONE => 'Cả năm',
			self::TERM_1 => 'Học kỳ 1',
			self::TERM_2 => 'Học kỳ 2',
			self::TERM_3 => 'Học kỳ 3',
			self::TERM_SUMMER=>'Học kỳ hè'
		};
	}
	public static function getTerms(): array
	{
		return [
			self::TERM_NONE => self::decodeTerm(self::TERM_NONE),
			self::TERM_1 => self::decodeTerm(self::TERM_1),
			self::TERM_2 => self::decodeTerm(self::TERM_2),
			self::TERM_3 => self::decodeTerm(self::TERM_3),
			self::TERM_SUMMER=>self::decodeTerm(self::TERM_SUMMER),
		];
	}
}


