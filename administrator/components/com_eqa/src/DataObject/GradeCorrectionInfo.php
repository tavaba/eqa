<?php
namespace Kma\Component\Eqa\Administrator\DataObject;
defined('_JEXEC') or die();
class GradeCorrectionInfo extends ObjectInfo
{
	public int $id;
	public int $examId;
	public string|null $exam;
	public int $learnerId;
	public string|null $learnerCode;
	public string|null $learnerLastname;
	public string|null $learnerFirstname;
	public int $constituent;
	public string|null $reason;
	public int $status;
	public string|null $description;
}