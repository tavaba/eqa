<?php
namespace Kma\Component\Eqa\Administrator\Controller;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Response\JsonResponse;
use Joomla\CMS\Router\Route;
use Kma\Component\Eqa\Administrator\Base\EqaFormController;
use Kma\Component\Eqa\Administrator\Helper\DatabaseHelper;

defined('_JEXEC') or die();

class ClassController extends  EqaFormController {

    /**
     * Thêm sinh viên vào một lớp học phần.
     * Gồm 2 pha.
     * - Pha 1 ('showform') sẽ redirect đến layout 'addlearners' để hiển thị form cho người dùng nhập dữ liệu
     * - Pha 2 ('getdata') sẽ nhận và lưu dữ liệu
     * Ghi chú: id của lớp học ('id') và pha ('phase') được truyền qua trường ẩn của form
     * ở các layout 'learners' và 'addlearners'
     * @return bool
     * @since 1.0.2
     */
    public function addLearners()
    {
        $classId = $this->app->input->getInt('class_id');

        // Access check
        if (!$this->app->getIdentity()->authorise('core.create',$this->option)) {
            // Set the internal error and also the redirect error.
            $this->setMessage(Text::_('JLIB_APPLICATION_ERROR_BATCH_CANNOT_CREATE'), 'error');
            $this->setRedirect(
                Route::_(
                    'index.php?option=com_eqa&view=classlearners&class_id='.$classId,
                    false
                )
            );
            return false;
        }

        //Xác định pha của nhiệm vụ
        $phase = $this->app->input->getAlnum('phase','');
        if($phase !== 'getdata')
        {
            // Redirect to the 'add learners' screen.
            $this->setRedirect(
                Route::_(
                    'index.php?option=com_eqa&view=class&layout=addlearners&class_id='.$classId,
                    false
                )
            );
        }
        else
        {
            //Pha này thì cần check token
            $this->checkToken();

            //Gọi model để nhập data
            $model = $this->getModel();
            $inputLearners = $this->app->input->getString('learners');
            $normalizedLearners = preg_replace('/[\s,;]+/', ' ', $inputLearners);
            $normalizedLearners = trim($normalizedLearners);
            $learnerCodes = explode(' ', $normalizedLearners);
            $model->addLearners($classId, $learnerCodes);
			DatabaseHelper::updateClassNPam($classId);


            //Add xong thì redirect về trang xem danh sách lớp học phần
            $this->setRedirect(
                Route::_(
                    'index.php?option=com_eqa&view=classlearners&class_id='.$classId,
                    false
                )
            );

        }

        return true;
    }
    public function allow():void
    {
        // Check for request forgeries
        $this->checkToken();

        $classId = $this->app->input->getInt('class_id');
        if(empty($classId)){
            $url = Route::_('index.php?option=com_eqa&view=classes',false);
            $this->setRedirect($url);
            return;
        }

        //Set redirect in any other case
        $url = Route::_('index.php?option=com_eqa&view=classlearners&class_id='.$classId,false);
        $this->setRedirect($url);


        // Access check
        if (!$this->app->getIdentity()->authorise('core.edit.state',$this->option)) {
            $this->setMessage(Text::_('COM_EQA_MSG_UNAUTHORISED'), 'error');
            return;
        }

        // Get items to remove from the request.
        $learnerIds = (array) $this->input->get('cid', [], 'int');

        // Remove zero values resulting from input filter
        $learnerIds = array_filter($learnerIds);

        if (empty($learnerIds)) {
            $this->app->enqueueMessage(Text::_('COM_EQA_NO_ITEM_SELECTED'));
            return;
        }

        // Get the model and do the job
        $model = $this->getModel();
        $model->setAllowed($classId, $learnerIds,true);
    }
    public function deny():void
    {
        // Check for request forgeries
        $this->checkToken();

        $classId = $this->app->input->getInt('class_id');
        if(empty($classId)){
            $url = Route::_('index.php?option=com_eqa&view=classes',false);
            $this->setRedirect($url);
            return;
        }

        //Set redirect in any other case
        $url = Route::_('index.php?option=com_eqa&view=classlearners&class_id='.$classId,false);
        $this->setRedirect($url);


        // Access check
        if (!$this->app->getIdentity()->authorise('core.edit.state',$this->option)) {
            $this->setMessage(Text::_('COM_EQA_MSG_UNAUTHORISED'), 'error');
            return;
        }

        // Get items to remove from the request.
        $learnerIds = (array) $this->input->get('cid', [], 'int');

        // Remove zero values resulting from input filter
        $learnerIds = array_filter($learnerIds);

        if (empty($learnerIds)) {
            $this->app->enqueueMessage(Text::_('COM_EQA_NO_ITEM_SELECTED'));
            return;
        }

        // Get the model and do the job
        $model = $this->getModel();
        $model->setAllowed($classId, $learnerIds,false);
    }
    public function remove():void
    {
        // Check for request forgeries
        $this->checkToken();

        $classId = $this->app->input->getInt('class_id');
        if(empty($classId)){
            $url = Route::_('index.php?option=com_eqa&view=classes',false);
            $this->setRedirect($url);
            return;
        }

        //Set redirect in any other case
        $url = Route::_('index.php?option=com_eqa&view=classlearners&class_id='.$classId,false);
        $this->setRedirect($url);


        // Access check
        if (!$this->app->getIdentity()->authorise('core.delete',$this->option)) {
            $this->setMessage(Text::_('COM_EQA_MSG_UNAUTHORISED'), 'error');
            return;
        }

        // Get item ids from the request.
        $learnerIds = (array) $this->input->get('cid', [], 'int');

        // Remove zero values resulting from input filter
        $learnerIds = array_filter($learnerIds);

        if (empty($learnerIds)) {
            $this->app->enqueueMessage(Text::_('COM_EQA_NO_ITEM_SELECTED'));
            return;
        }

        // Get the model and do the job
        $model = $this->getModel();
        foreach ($learnerIds as $learnerId) {
            $model->removeLearner($classId, $learnerId);
        }
		DatabaseHelper::updateClassNPam($classId);
    }

	/**
	 * Lấy danh sách sinh viên trong một lớp học phần dưới dạng JSON.
	 * Mỗi phần tử có 2 thuộc tính: 'id', 'name'
	 * @since 1.2.0
	 */
	public function getJsonClassLearners()
	{
		$app = $this->app;

		//Check access
		if (!$app->getIdentity()->authorise('core.manage',$this->option)) {
			echo new JsonResponse([], 'Access denied', true);
			$app->close();
		}

		//Get the class id from the request
		$classId = $app->input->getInt('class_id', 0);
		if (!$classId) {
			echo new JsonResponse([], 'Invalid class ID', true);
			$app->close();
		}

		//Retrieve the list of students
		$model = $this->getModel();
		$students = $model->getClassLearners($classId);
		if (!$students || !count($students))
			echo new JsonResponse([], 'No student found', true);
		else
			echo new JsonResponse($students);
		$app->close();
	}

}