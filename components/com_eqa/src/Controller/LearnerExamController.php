<?php
namespace Kma\Component\Eqa\Site\Controller;
defined('_JEXEC') or die();

use Exception;
use Joomla\CMS\Factory;
use Joomla\CMS\MVC\Controller\BaseController;
use Joomla\CMS\Router\Route;
use Kma\Component\Eqa\Administrator\Helper\GeneralHelper;
use Kma\Component\Eqa\Site\Model\LearnerexamModel;

/* The DEFAULT controller for the front end */
class LearnerExamController extends BaseController
{
	public function RequestRegrading(): void
	{
		try
		{
			//Redirect in any case
			$url = Route::_('index.php?option=com_eqa&view=learnerExams', false);
			$this->setRedirect($url);

			//Check token
			$this->checkToken();

			//A student account is required to continue
			$learnerCode = GeneralHelper::getSignedInLearnerCode();
			if($learnerCode === null)
				throw new Exception('Tài khoản không hợp lệ');

			//Get an array of exam IDs
			$app = Factory::getApplication();
			$examIds = $app->input->get('cid', null, 'array');
			if(empty($examIds))
				throw new Exception('Không xác định được môn thi');

			/**
			 * @var LearnerexamModel $model
			 */
			$model = $this->getModel();
			foreach ($examIds as $examId)
				$model->RequestRegrading($examId, $learnerCode);
		}
		catch (Exception $e)
		{
			$this->setMessage($e->getMessage(), 'error');
		}
	}

	public function RequestCorrection():void
	{
		try
		{
			//Redirect in any case
			$url = Route::_('index.php?option=com_eqa&view=learnerExams', false);
			$this->setRedirect($url);

			//Check token
			$this->checkToken();

			//A student account is required to continue
			$learnerCode = GeneralHelper::getSignedInLearnerCode();
			if($learnerCode === null)
				throw new Exception('Tài khoản không hợp lệ');

			//Get and validate
			$app = Factory::getApplication();
			$examId = $app->input->getInt('exam_id');
			$markConstituent = $app->input->getInt('constituent');
			$reason = $app->input->getString('reason');
			if(!is_int($examId) || !is_int($markConstituent) || !is_string($reason))
			{
				$this->setMessage('Yêu cầu không hợp lệ', 'error');
				return;
			}

			/**
			 * Request a correction action
			 * @var LearnerexamModel $model
			 */
			$model = $this->getModel();
			$model->RequestCorrection($examId, $learnerCode, $markConstituent, $reason);
		}
		catch (Exception $e)
		{
			$this->setMessage($e->getMessage(), 'error');
		}
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

			//Redirect to the form
			$url = Route::_('index.php?option=com_eqa&view=learnerExam&layout=requestCorrection&exam_id='. $examId, false);
			$this->setRedirect($url);
		}
		catch (Exception $e)
		{
			$this->setMessage($e->getMessage(), 'error');
			$url = Route::_('index.php?option=com_eqa&view=learnerExams', false);
			$this->setRedirect($url);
		}
	}

}

