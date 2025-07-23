<?php
namespace Kma\Component\Eqa\Site\Controller;
defined('_JEXEC') or die();

use Joomla\CMS\Factory;
use Joomla\CMS\MVC\Controller\BaseController;
use JRoute;
use Kma\Component\Eqa\Administrator\Helper\GeneralHelper;

/* The DEFAULT controller for the front end */
class LearnerexamsController extends BaseController
{
	public function RequestRegrading(): void
	{
		$this->checkToken();
		$learnerCode = GeneralHelper::getCurrentUsername();

		//Redirect in any case
		$url = JRoute::_('index.php?option=com_eqa&view=learnerexams', false);
		$this->setRedirect($url);

		$examIds = $this->app->input->get('cid',null,'array');
		if(empty($examIds))
		{
			$this->setMessage('Không có môn thi nào được chọn', 'error');
			return;
		}

		$model = $this->getModel('Learnerexam');
		foreach ($examIds as $examId)
			$model->RequestRegrading($examId, $learnerCode);
	}

	public function RequestCorrection():void
	{
		$this->checkToken();
		$app = Factory::getApplication();

		$examId = $app->input->getInt('exam_id');
		$markConstituent = $app->input->getInt('constituent');
		$reason = $app->input->getString('reason');
		$learnerCode = GeneralHelper::getCurrentUsername();

		//Redirect in any case
		$url = JRoute::_('index.php?option=com_eqa&view=learnerexams', false);
		$this->setRedirect($url);

		if(!is_int($examId) || !is_int($markConstituent) || !is_string($reason) || !is_string($learnerCode))
		{
			$this->setMessage('Yêu cầu không hợp lệ', 'error');
			return;
		}
		$model = $this->getModel();
		$model->RequestCorrection($examId, $learnerCode, $markConstituent, $reason);
	}
}

