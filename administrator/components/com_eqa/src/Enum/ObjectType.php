<?php
/**
 * @package     Kma\Component\Eqa\Administrator\Enum
 * @subpackage
 *
 * @copyright   A copyright
 * @license     A "Slug" license name e.g. GPL2
 */

namespace Kma\Component\Eqa\Administrator\Enum;

enum ObjectType : int
{
	use EnumHelper;

	//Infrastructor
	case Building = 1001;
	case Room = 1002;
	case Unit = 1050;
	case Employee = 1051;
	case Speciality = 1100;
	case Program = 1101;
	case Subject = 1102;
	case Course = 1201;
	case Group = 1202;
	case Cohort = 1203;
	case Learner = 1301;
	case CreditClass = 1302;        //Using of 'Class' is prohibited

	//Exam
	case Examseason = 2000;
	case Exam = 2001;
	case Examroom = 2002;
	case Examsession = 2003;

	//Operation
	case Paper = 2500;
	case Regrading = 2501;
	case GradeCorrection = 2502;
	case SecondAttempt = 2504;

	//Assessment
	case Assessment = 3000;

	//Juntion
	case ClassLearner = 7001;
	case ExamExaminee = 7002;

	case AssessmentLearner = 7500;

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
