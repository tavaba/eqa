<?php
namespace Kma\Component\Eqa\Administrator\Interface;
use Kma\Component\Eqa\Administrator\Helper\ExamHelper;

defined('_JEXEC') or die();
class Regradingrequest extends ObjectInfo
{
	public int $examId;
	public string $examName;
	public ?int $credits;
	public int $learnerId;
	public string $learnerCode;
	public ?string $learnerLastname;
	public ?string $learnerFirstname;
	public string $groupCode;
	public string $courseCode;
	public int $statusCode;
	public float $orginalMark;

	static public function cast($instance): LearnerInfo
	{
		return $instance;
	}
}