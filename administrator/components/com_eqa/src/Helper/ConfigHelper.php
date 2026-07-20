<?php
namespace Kma\Component\Eqa\Administrator\Helper;
defined('_JEXEC') or die();

use Joomla\CMS\Component\ComponentHelper;
use Joomla\Registry\Registry;
use Kma\Component\Eqa\Administrator\Enum\FeeMode;
use Kma\Component\Eqa\Administrator\Enum\SecondAttemptMarkLimitMode;
use RuntimeException;

abstract class ConfigHelper
{
	private static bool $uninitialized = true;
	private static Registry $params;
	private static function init():void
	{
		self::$params = ComponentHelper::getParams('com_eqa');
		self::$uninitialized = false;
	}
	public static function getProgressMarkPrecision(): int
	{
		if(self::$uninitialized)
			self::init();
		return self::$params->get('params.precision_progress');
	}
	public static function getExamMarkPrecision(): int
	{
		if(self::$uninitialized)
			self::init();
		return self::$params->get('params.precision_exam', 1);
	}
	public static function getModuleMarkPrecision(): int
	{
		if(self::$uninitialized)
			self::init();
		return self::$params->get('params.precision_module', 1);
	}
	public static function getSecondAttemptMarkLimitMode(): SecondAttemptMarkLimitMode
	{
		if(self::$uninitialized)
			self::init();
		$value = self::$params->get('params.second_attempt_limit', SecondAttemptMarkLimitMode::OnExamMark->value);
		return SecondAttemptMarkLimitMode::from($value);
	}
	public static function getThresholdForPam1(): float
	{
		if(self::$uninitialized)
			self::init();
		return self::$params->get('params.threshold_pam1');
	}
	public static function getThresholdForPam2(): float
	{
		if(self::$uninitialized)
			self::init();
		return self::$params->get('params.threshold_pam2');
	}
	public static function getThresholdForPam(): float
	{
		if(self::$uninitialized)
			self::init();
		return self::$params->get('params.threshold_pam');
	}
	public static function getThresholdForFinalExamMark(): float
	{
		if(self::$uninitialized)
			self::init();
		return self::$params->get('params.threshold_final_exam_mark');
	}
	public static function getRegradingFeeMode(): FeeMode
	{
		if(self::$uninitialized)
			self::init();
		$value = self::$params->get('params.regrading_fee_mode', FeeMode::PerExam->value);
		return FeeMode::from($value);
	}
	public static function getRegradingFeeRate(): float
	{
		if(self::$uninitialized)
			self::init();
		return self::$params->get('params.regrading_fee_rate', 30000);
	}

	public static function getSecondAttemptFeeMode(): FeeMode
	{
		if(self::$uninitialized)
			self::init();
		$value = self::$params->get('params.second_attempt_fee_mode', FeeMode::PerExam->value);
		return FeeMode::from($value);
	}

	public static function getSecondAttemptFeeRate(): float
	{
		if (self::$uninitialized)
			self::init();
		return self::$params->get('params.second_attempt_fee_rate', 90000);
	}

}


