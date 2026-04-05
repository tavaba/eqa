<?php
namespace Kma\Component\Eqa\Site\Controller;
defined('_JEXEC') or die();

use Exception;
use Joomla\CMS\Factory;
use Joomla\CMS\MVC\Controller\BaseController;
use Joomla\CMS\Router\Route;
use JRoute;
use Kma\Component\Eqa\Administrator\Helper\GeneralHelper;
use Kma\Component\Eqa\Site\Model\LearnerexamModel;

/* The DEFAULT controller for the front end */
class LearnerexamsController extends BaseController
{
	public function RequestRegrading(): void
	{
		try
		{
			$this->checkToken();

			$examIds = $this->app->input->get('cid',null,'array');
			if(empty($examIds))
				throw new Exception('Không có môn thi nào được chọn');

			/**
			 * @var LearnerexamModel $model
			 */
			$learnerCode = GeneralHelper::getSignedInLearnerCode();
			$model = $this->getModel('Learnerexam');
			foreach ($examIds as $examId)
				$model->RequestRegrading($examId, $learnerCode);

			//Redirecto to regradings view
			$this->setRedirect(Route::_('index.php?option=com_eqa&view=learnerRegradings', false));
		}
		catch (Exception $e)
		{
			$this->app->enqueueMessage($e->getMessage(),'error');
			$this->setRedirect(Route::_('index.php?option=com_eqa&view=learnerexams', false));
		}
	}

	public function RequestCorrection():void
	{
		try
		{
			$this->checkToken();
			$app = $this->app;

			$examId = $app->input->getInt('exam_id');
			$markConstituent = $app->input->getInt('constituent');
			$reason = $app->input->getString('reason');
			$learnerCode = GeneralHelper::getSignedInLearnerCode();

			//Redirect in any case
			$url = Route::_('index.php?option=com_eqa&view=learnerexams', false);
			$this->setRedirect($url);

			/**
			 * @var LearnerexamModel $model
			 */
			if(!is_int($examId) || !is_int($markConstituent) || !is_string($reason) || !is_string($learnerCode))
				throw  new Exception('Yêu cầu không hợp lệ');
			$model = $this->getModel();
			$model->RequestCorrection($examId, $learnerCode, $markConstituent, $reason);
		}
		catch (Exception $e)
		{
			$this->app->enqueueMessage($e->getMessage(),'error');
		}
	}
}

