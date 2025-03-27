<?php
namespace Kma\Component\Eqa\Administrator\Interface;
use Kma\Component\Eqa\Administrator\Helper\ExamHelper;

defined('_JEXEC') or die();
class LearnerInfo extends ObjectInfo
{
	public int $id;
	public string $code;
	public string $lastname;
	public string $firstname;
	public function getFullName():string|null
	{
		if(empty($this->lastname) && empty($this->firstname))
			return null;

		if(empty($this->lastname) && !empty($this->firstname))
			return $this->firstname;

		if(!empty($this->lastname) && empty($this->firstname))
			return $this->lastname;

		return $this->lastname . ' ' . $this->firstname;
	}

	static public function cast($obj): LearnerInfo
	{
		return $obj;
	}
}