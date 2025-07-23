<?php
namespace Kma\Component\Eqa\Site\Controller;
defined('_JEXEC') or die();

use Exception;
use Joomla\CMS\Factory;
use Joomla\CMS\MVC\Controller\BaseController;
use JRoute;
use Kma\Component\Eqa\Administrator\Helper\GeneralHelper;

/* The DEFAULT controller for the front end */
class LearnerexamController extends BaseController
{
	public function RequestRegrading(): void
	{
		$app = Factory::getApplication();
		$examId = $app->input->getInt('exam_id');
		$learnerCode = GeneralHelper::getCurrentUsername();

		//Redirect in any case
		$url = JRoute::_('index.php?option=com_eqa&view=learnerexams', false);
		$this->setRedirect($url);

		if(!is_integer($examId) || !is_string($learnerCode))
		{
			$this->setMessage('Yêu cầu thất bại', 'error');
			return;
		}
		$model = $this->getModel();
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
	public function ShowCorrectionRequestForm(): void
	{
		try
		{
			$cid = $this->app->input->get('cid',null,'array');
			$cid = array_filter($cid, 'intval');
			if (empty($cid))
				throw new Exception('Không có môn thi nào được chỉ định');
			$examId = $cid[0];

			//Redirect in any case
			$url = JRoute::_('index.php?option=com_eqa&view=learnerexam&layout=requestcorrection&exam_id='. $examId, false);
			$this->setRedirect($url);
		}
		catch (Exception $e)
		{
			$this->setMessage($e->getMessage(), 'error');
			$url = JRoute::_('index.php?option=com_eqa&view=learnerexams', false);
			$this->setRedirect($url);
		}
	}

}

