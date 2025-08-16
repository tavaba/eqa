<?php
namespace Kma\Component\Eqa\Administrator\Controller;
use Exception;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Router\Route;
use Kma\Component\Eqa\Administrator\Base\EqaFormController;

defined('_JEXEC') or die();

class CohortController extends  EqaFormController {
	public function addLearners(): void
	{
		try
		{
			//1. Check token
			$this->checkToken();

			//2. Check permission
			if(!$this->app->getIdentity()->authorise('core.create', 'com_eqa'))
				throw new Exception('Bạn không có quyền thực hiện tác vụ này');

			//3. Get the cohort id
			$cohortId = $this->input->getInt('cohort_id');
			if(empty($cohortId))
				throw new Exception('Truy vấn không hợp lệ');

			//4. Try to get the 'jform' data
			$data = $this->input->post->get('jform',[],'array');

			//PHASE 1: Redirect to the view 'Cohort', layout 'Add Learners'
			if(empty($data))
			{
				$this->setRedirect(Route::_('index.php?option=com_eqa&view=cohort&layout=addlearners&cohort_id='.$cohortId, false));
				return;
			}

			//PHASE 2: Add learners into the cohort
			if(!isset($data['learner_ids']))
				throw new Exception('Dữ liệu truy vấn không hợp lệ');
			$learnerIds = $data['learner_ids'];
			$learnerIds = array_filter($learnerIds, 'intval');
			if(count($learnerIds) == 0)
				throw new Exception('Không có HVSV nào được chọn');
			$model = $this->getModel();
			$model->addLearners($cohortId,$learnerIds);

			//5. Redirect back to the view 'Cohortlearners'
			$this->setRedirect(Route::_('index.php?option=com_eqa&view=cohortlearners&cohort_id='.$cohortId, false));
			$this->setMessage('HVSV được thêm thành công', 'success');
			return;
		}
		catch (Exception $e)
		{
			$this->setRedirect(Route::_('index.php?option=com_eqa&view=cohorts', false));
			$this->setMessage($e->getMessage(), 'error');
			return;
		}
	}
	public function removeLearners(): void
	{
		try
		{
			//1. Check token
			$this->checkToken();

			//2. Check permission
			if(!$this->app->getIdentity()->authorise('core.delete', 'com_eqa'))
				throw new Exception('Bạn không có quyền thực hiện tác vụ này');

			//3. Get the cohort id from post data
			$cohortId = $this->input->getInt('cohort_id');
			if(empty($cohortId))
				throw new Exception('Truy vấn không hợp lệ');

			//4. Get the learner ids from post data
			$learnerIds = $this->input->get('cid',[],'array');
			$learnerIds = array_filter($learnerIds,'intval');
			if(empty($learnerIds))
				throw new Exception('Truy vấn không hợp lệ');

			//5. Remove the learners from the cohort
			$model = $this->getModel();
			$model->removeLearners($cohortId,$learnerIds);

			//6. Redirect back to the view 'Cohortlearners'
			$this->setMessage('HVSV đã được xóa thành công khỏi nhóm', 'success');
			$this->setRedirect(Route::_('index.php?option=com_eqa&view=cohortlearners&cohort_id='.$cohortId, false));
		}
		catch(Exception $e)
		{
			$this->setRedirect(Route::_('index.php?option=com_eqa&view=cohorts', false));
			$this->setMessage($e->getMessage(), 'error');
			return;
		}
	}
}