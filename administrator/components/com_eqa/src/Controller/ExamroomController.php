<?php
namespace Kma\Component\Eqa\Administrator\Controller;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Router\Route;
use JRoute;
use Kma\Component\Eqa\Administrator\Base\EqaFormController;

defined('_JEXEC') or die();

class ExamroomController extends  EqaFormController {
    public function removeExaminees(): void
    {
        $this->checkToken();

        $examroomId = $this->input->getInt('examroom_id');
        if(empty($examroomId)){
            $redirect = \JRoute::_('index.php?option=com_eqa&view=examrooms',false);
            $this->setRedirect($redirect);
            return;
        }

        if(!$this->app->getIdentity()->authorise('core.edit', $this->option)){
            $this->app->enqueueMessage(Text::_('COM_EQA_MSG_UNAUTHORISED'), 'error');
            $redirect = \JRoute::_('index.php?option=com_eqa&view=examroom&layout=examinees&examroom_id='.$examroomId, false);
            $this->redirect($redirect);
            return;
        }

        // Get items to remove from the request.
        $learnerIds = (array) $this->input->get('cid', [], 'int');

        // Remove zero values resulting from input filter
        $learnerIds = array_filter($learnerIds);

        if (empty($learnerIds)) {
            $this->app->enqueueMessage('COM_EQA_NO_ITEM_SELECTED','warning');
        } else {
            // Get the model.
            $model = $this->getModel();

            // Remove the items.
            $model->removeExaminees($examroomId, $learnerIds);
        }

        $redirect = JRoute::_('index.php?option=com_eqa&view=examroom&layout=examinees&examroom_id='.$examroomId, false);
        $this->setRedirect($redirect);
    }
    public function addExaminees()
    {
        //Get the id of the exam to add examinees
        $examroomId = $this->app->input->getInt('examroom_id');

        // Access check
        if (!$this->app->getIdentity()->authorise('core.edit',$this->option)) {
            // Set the internal error and also the redirect error.
            $this->setMessage(Text::_('JLIB_APPLICATION_ERROR_BATCH_CANNOT_CREATE'), 'error');
            $this->setRedirect(
                Route::_(
                    'index.php?option=com_eqa&view=examroom&layout=examinees&examroom_id='.$examroomId,
                    false
                )
            );
            return false;
        }

        //Xác định pha của nhiệm vụ
        $phase = $this->app->input->getAlnum('phase','');
        if($phase !== 'getdata')
        {
            // Redirect to the 'add examinees' screen.
            $this->setRedirect(
                Route::_(
                    'index.php?option=com_eqa&view=examroom&layout=addexaminees&examroom_id='.$examroomId,
                    false
                )
            );
        }
        else
        {
            //Pha này thì cần check token
            $this->checkToken();

            //1. Chuẩn bị dữ liệu
            //1.1 Mã môn thi
            $examId = $this->input->getInt('exam_id');

            //1.2. Mã thí sinh
            $inputLearnerCodes = $this->input->getString('learnercodelist');
            $normalizedLearnerCodes = preg_replace('/[\s,;]+/', ' ', $inputLearnerCodes);
            $normalizedLearnerCodes = trim($normalizedLearnerCodes);
            $learnerCodes = explode(' ', $normalizedLearnerCodes);

            //2. Gọi model để thêm thí sinh
            $model = $this->getModel();
            $model->addExaminees($examroomId, $examId, $learnerCodes);

            //Add xong thì redirect về trang xem danh sách lớp học phần
            $this->setRedirect(
                Route::_(
                    'index.php?option=com_eqa&view=examroom&layout=examinees&examroom_id='.$examroomId,
                    false
                )
            );
        }

        return true;

    }
	public function editAnomaly()
	{
		//Check token
		$this->checkToken();

		//Set redirect in any ERROR case
		$examId = $this->app->input->getInt('exam_id');
		$url = 'index.php?option=com_eqa&view=examrooms';
		if(!empty($examId))
			$url .= '&filter[exam_id]= ' . $examId;
		$this->setRedirect(JRoute::_($url, false));

		//Check permission
		if(!$this->app->getIdentity()->authorise('core.edit',$this->option)){
			$this->setMessage(Text::_('COM_EQA_MSG_UNAUTHORISED'), 'error');
			return;
		}

		//Determin examroom id
		$cid = $this->app->input->post->get('cid',[],'array');
		//$cid = array_map('intval', $cid);
		if(empty($cid))
		{
			$this->setMessage(Text::_('COM_EQA_MSG_NO_ITEM_SPECIFIED'),'error');
			return;
		}
		$examroomId = $cid[0];

		//Redirect
		$url = 'index.php?option=com_eqa&view=examroom&layout=anomaly&examroom_id='.$examroomId;
		$this->setRedirect(JRoute::_($url, false));
	}
	public function saveAnomaly(bool $continueEdit=false)
	{
		//Check token
		$this->checkToken();

		//Check permission
		if(!$this->app->getIdentity()->authorise('core.edit',$this->option))
		{
			$this->setMessage(Text::_('COM_EQA_MSG_UNAUTHORISED'),'error');
			$this->setRedirect(JRoute::_('index.php?option=com_eqa&view=examrooms',false));
			return;
		}

		//Get data
		$examroomId = $this->input->post->getInt('examroom_id');
		$data = $this->input->post->get('jform',[],'array');
		if(empty($examroomId) || empty($data)){
			$this->setMessage(Text::_('COM_EQA_MSG_INVALID_DATA'),'error');
			$this->setRedirect(JRoute::_('index.php?option=com_eqa&view=examrooms', false));
			return;
		}

		//Process
		$model = $this->getModel();
		$model->saveAnomaly($examroomId, $data);

		//Redirect
		if($continueEdit)
			$url = 'index.php?option=com_eqa&view=examroom&layout=anomaly&examroom_id='.$examroomId;
		else
			$url = 'index.php?option=com_eqa&view=examrooms';
		$this->setRedirect(JRoute::_($url,false));
	}
	public function applyAnomaly()
	{
		$this->saveAnomaly(true);
	}
}