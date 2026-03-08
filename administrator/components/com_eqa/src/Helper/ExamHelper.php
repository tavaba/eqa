<?php
namespace Kma\Component\Eqa\Administrator\Helper;
defined('_JEXEC') or die();

use Joomla\CMS\Language\Text;
use Kma\Component\Eqa\Administrator\Enum\Anomaly;
use Kma\Component\Eqa\Administrator\Enum\Conclusion;
use Kma\Component\Eqa\Administrator\Enum\SecondAttemptMarkLimitMode;
use Kma\Component\Eqa\Administrator\Enum\SpecialMark;
use Kma\Component\Eqa\Administrator\Enum\TestType;
use Kma\Component\Eqa\Administrator\Service\ConfigService;

abstract class ExamHelper
{
	private static ConfigService $configService;

	static public function markToText($mark):string
	{
		//Trường hợp 1: Chưa có điểm
		if(is_null($mark))
			return '';

		//Trường hợp 2: Điểm đặc biệt
		$specialMark = SpecialMark::tryFrom((int)$mark);
		if($specialMark !== null)
			return $specialMark->getLabel();

		//Trường hợp 3: Điểm bình thường
		return (string)$mark;
	}

	static public function isAllowedToFinalExam(float $pam1, float $pam2, float $pam): bool
	{
		$thresholdPam1 = ConfigHelper::getThresholdForPam1();
		$thresholdPam2 = ConfigHelper::getThresholdForPam2();
		$thresholdPam = ConfigHelper::getThresholdForPam();
		if($pam1<$thresholdPam1 || $pam2<$thresholdPam2 || $pam<$thresholdPam)
			return false;
		return  true;
	}
	static public function toPam($value, string|null $description=null): float|bool
	{
		//Prepare
		if(is_string($value)){
			$value = str_replace(',','.',trim($value));
		}

		//If $value is NOT a numeric value
		if(!empty($value) && !is_numeric($value))
		{
			$specialMark = SpecialMark::tryFromText($value);
			if($specialMark)
				return $specialMark->value;
			return false;
		}

		//If $value is of some cases of "empty" (not exactly is_empty() function)
		if($value==0 || $value=='' || is_null($value))
		{
			$specialMark = SpecialMark::tryFromText($description);
			if($specialMark)
				return $specialMark->value;
			else if ($value==0)
				return 0;
			else
				return false;
		}

		//$value is a number
		$value = (float)$value;
		if($value<0 || $value>10)
			return false;

		//Round the value as required by configuration
		$precision = ConfigHelper::getProgressMarkPrecision();
		return round($value, $precision);
	}
    static public function calculatePamForDefaultFormular(float $pam1, float $pam2): float
    {
	    $precision = ConfigHelper::getProgressMarkPrecision();
	    $pam = 0.7*$pam1 + 0.3*$pam2;
	    return round($pam, $precision);
    }
	static public function calculatePam(int $subjectId, float $pam1, float $pam2): float
	{
		//Nothing to do with $subjectId for now
		//TODO: Xử lý trường hợp môn thi có các hệ số khác nhau
		$pam = 0.7*$pam1 + 0.3*$pam2;
		return $pam;
	}

	/**
	 * @param   float  $examMark Điểm thi gốc, có thể trước hoặc sau phúc khảo, đính chính
	 * @param   int    $anomalyValue
	 * @param   int    $attempt
	 * @param   float  $addValue
	 * @param   int    $admissionYear
	 *
	 * @return float|int|mixed
	 *
	 * @since 1.0.0
	 */
	static public function calculateFinalMark(float $examMark, int $anomalyValue, int $attempt, float $addValue, int $admissionYear)
	{
		$precision = ConfigHelper::getExamMarkPrecision();

		//Xử lý kỷ luật, nếu có
		$finalMark = match ($anomalyValue)
		{
			Anomaly::None->value => $examMark,
			Anomaly::Penalized25->value => round(0.75 * $examMark, $precision),
			Anomaly::Penalized50->value => round(0.5 * $examMark, $precision),
			default => 0,
		};

		//Cộng điểm khuyến khích, nếu có
		//(Chỉ cộng nếu điểm thi đạt ngưỡng)
		$threshold = ConfigHelper::getThresholdForFinalExamMark();
		if($finalMark >= $threshold)
			$finalMark = min([10, $finalMark+$addValue]);

		//Giới hạn điểm thi lần 2
		$limitMode = ConfigHelper::getSecondAttemptMarkLimitMode();
		if($attempt>1 && $admissionYear>=2021 && $limitMode==SecondAttemptMarkLimitMode::OnExamMark)
			$finalMark = min([$finalMark, 6.9]);

		return $finalMark;
	}
	static public function calculateModuleMark(int $subjectId, float $pam, float $examMark, int $attempt, int $admissionYear)
	{
		//Nothing to do with $subjectId for now
		//TODO: Xử lý trường hợp môn thi có các hệ số khác nhau
		$precision = ConfigHelper::getModuleMarkPrecision();
		$limit = ConfigHelper::getSecondAttemptMarkLimitMode();
		$moduleMark = 0.3*$pam + 0.7*$examMark;
		$moduleMark = round($moduleMark, $precision);
		if($attempt>1 && $admissionYear>=2021 && $limit==SecondAttemptMarkLimitMode::OnModuleMark)
			$moduleMark = min([$moduleMark, 6.9]);
		return $moduleMark;
	}
	static public function calculateBase4Mark(float $moduleMark): float
	{
		if($moduleMark >= 9.0)
			return 4.0;
		if($moduleMark >= 8.5)
			return 3.8;
		if($moduleMark >= 7.8)
			return 3.5;
		if($moduleMark >= 7.0)
			return 3.0;
		if($moduleMark >= 6.3)
			return 2.4;
		if($moduleMark >= 5.5)
			return 2.0;
		if($moduleMark >= 4.8)
			return 1.5;
		if($moduleMark >= 4.0)
			return 1.0;
		return 0;
	}

	static public function calculateModuleGrade(float $moduleMark, Conclusion $conclusion): string
	{
		if($conclusion == Conclusion::Postponed)
			return 'I';
		if($conclusion == Conclusion::RetakeExam || $conclusion == Conclusion::RetakeCourse)
			return 'F';

		if($moduleMark <= 4.7)
			return 'D';
		if($moduleMark <= 5.4)
			return 'D+';
		if($moduleMark <= 6.2)
			return 'C';
		if($moduleMark <= 6.9)
			return 'C+';
		if($moduleMark <= 7.7)
			return 'B';
		if($moduleMark <= 8.4)
			return 'B+';
		if($moduleMark <= 8.9)
			return 'A';
		return 'A+';
	}
	static public function normalizeMarks(object &$learner):void
	{
		if(!empty($learner->pam1) && $learner->pam1<0)
			$learner->pam1 = SpecialMark::from($learner->pam1)->getLabel();
		if(!empty($learner->pam2) && $learner->pam2<0)
			$learner->pam2 = SpecialMark::from($learner->pam2)->getLabel();
		if(!empty($learner->pam) && $learner->pam<0)
			$learner->pam = SpecialMark::from($learner->pam)->getLabel();
	}
	static public function calculateConclusion($moduleMark, $finalExamMark, $anomaly, $attempt): Conclusion
	{
		if($anomaly == Anomaly::Suspended->value)
			return Conclusion::RetakeCourse;

		if($anomaly == Anomaly::Deferred->value || $anomaly == Anomaly::Retake->value)
			return Conclusion::Postponed;

		//TODO: Tính toán ngưỡng khác nhau cho Đại học và Cao học
		//      Đưa ngưỡng điểm tổng vào cấu hình

		$minFinalExamMark = ConfigHelper::getThresholdForFinalExamMark();

		if ($finalExamMark < $minFinalExamMark || $moduleMark < 4.0)
		{
			$maxAttempts = ConfigHelper::getMaxExamAttempts();
			if($attempt>=$maxAttempts)
				return Conclusion::RetakeCourse;
			return Conclusion::RetakeExam;
		}

		return Conclusion::Passed;
	}

	static public function isValidMark($value):bool
	{
		// Check if the value is numeric
		if (!is_numeric($value)) {
			return false;
		}

		// Convert to float
		$floatValue = (float) $value;

		// Check range
		return $floatValue >= 0 && $floatValue <= 10;
	}
}

