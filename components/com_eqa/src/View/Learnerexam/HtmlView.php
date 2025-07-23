<?php
namespace Kma\Component\Eqa\Site\View\Learnerexam;   //Must end with the View Name
defined('_JEXEC') or die();

use Joomla\CMS\Factory;
use Kma\Component\Eqa\Administrator\Base\EqaItemHtmlView;
use Kma\Component\Eqa\Administrator\Helper\DatabaseHelper;
use Kma\Component\Eqa\Administrator\Helper\FormHelper;
use Kma\Component\Eqa\Administrator\Helper\GeneralHelper;

class HtmlView extends EqaItemHtmlView
{
	protected $examInfo;
	protected $learnerInfo;
	protected function prepareDataForLayoutRequestcorrection()
	{
		$examId = Factory::getApplication()->input->getInt('exam_id');
		$learnerCode = GeneralHelper::getCurrentUsername();
		$this->learnerInfo = DatabaseHelper::getLearnerInfo($learnerCode);
		$this->examInfo = DatabaseHelper::getExamInfo($examId);
		if(empty($this->learnerInfo) || empty($this->examInfo))
			return;
		$this->form = FormHelper::getFrontendForm('com_eqa.requestcorrection','requestcorrection.xml',[]);
		if(isset($this->form))
		{
			$this->form->setValue('learner',null, $this->learnerInfo->code . ' - '  . $this->learnerInfo->getFullName());
			$this->form->setValue('exam', null, $this->examInfo->name);
		}
	}

}