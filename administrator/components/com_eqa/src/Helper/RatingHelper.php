<?php
namespace Kma\Component\Eqa\Administrator\Helper;
defined('_JEXEC') or die();

use Joomla\CMS\Factory;
use Joomla\CMS\Language\Text;
use Joomla\Database\DatabaseInterface;
use Matrix\Exception;

abstract class RatingHelper{
	public const EXCELLENT      = 10;
	public const VERY_GOOD      = 15;
	public const GOOD           = 20;
	public const FAIRLY_GOOD    = 30;
	public const AVERAGE_FAIR   = 40;
	public const AVERAGE        = 50;
	public const WEAK           = 60;
	public const POOR           = 70;

	public static function decode(int $rating): string
	{
		return match ($rating) {
			self::EXCELLENT => 'Xuất sắc',
			self::VERY_GOOD => 'Giỏi',
			self::GOOD => 'Tốt',
			self::FAIRLY_GOOD => 'Khá',
			self::AVERAGE_FAIR => 'Trung bình khá',
			self::AVERAGE => 'Trung bình',
			self::WEAK => 'Yếu',
			self::POOR => 'Kém',
			default => ''
		};
	}
	public static function decodeToAbbr(int $rating): string
	{
		return match ($rating) {
			self::EXCELLENT => 'XS',
			self::VERY_GOOD => 'G',
			self::GOOD => 'T',
			self::FAIRLY_GOOD => 'K',
			self::AVERAGE_FAIR => 'TBK',
			self::AVERAGE => 'TB',
			self::WEAK => 'Y',
			self::POOR => 'Ke',
			default => ''
		};
	}

	public static function getRatings(): array
	{
		return [
			self::EXCELLENT => self::decode(self::EXCELLENT),
			self::VERY_GOOD => self::decode(self::VERY_GOOD),
			self::GOOD => self::decode(self::GOOD),
			self::FAIRLY_GOOD => self::decode(self::FAIRLY_GOOD),
			self::AVERAGE_FAIR => self::decode(self::AVERAGE_FAIR),
			self::AVERAGE => self::decode(self::AVERAGE),
			self::WEAK => self::decode(self::WEAK),
			self::POOR => self::decode(self::POOR)
		];
	}

	public static function rateConductScore(float $score): int
	{
		if($score >= 90)
			return  self::EXCELLENT;
		if($score >= 80)
			return self::GOOD;
		if($score >= 70)
			return self::FAIRLY_GOOD;
		if($score >= 60)
			return self::AVERAGE_FAIR;
		if($score >= 50)
			return self::AVERAGE;
		if($score >= 30)
			return self::WEAK;
		return self::POOR;
	}
	public static function rateAcademicScore(float $score, int $base=4): int
	{
		if($base==4)
		{
			if($score >= 3.60)
				return  self::EXCELLENT;
			if($score >= 3.20)
				return self::VERY_GOOD;
			if($score >= 2.50)
				return self::FAIRLY_GOOD;
			if($score >= 2.00)
				return self::AVERAGE;
			if($score >= 1.00)
				return self::WEAK;
			return self::POOR;
		}
		elseif($base==10)
		{
			if($score >= 9.0)
				return  self::EXCELLENT;
			if($score >= 8.0)
				return self::VERY_GOOD;
			if($score >= 7.0)
				return self::FAIRLY_GOOD;
			if($score >= 5.0)
				return self::AVERAGE;
			if($score >= 4.0)
				return self::WEAK;
			return self::POOR;
		}
		return -1;
	}


}

