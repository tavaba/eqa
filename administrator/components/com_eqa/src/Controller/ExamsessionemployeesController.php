<?php
namespace Kma\Component\Eqa\Administrator\Controller;
defined('_JEXEC') or die();

use Joomla\CMS\Router\Route;
use Kma\Library\Kma\Controller\AdminController;

class ExamsessionemployeesController extends AdminController {
	public function save(string $task='save')
	{
		//Check token
		$this->checkToken();
		$app = $this->app;

		//Check if an exam session is specified
		$examsessionId = $app->input->getInt('examsession_id');
		if(!is_numeric($examsessionId)){
			$this->setMessage('COM_EQA_MSG_NO_EXAMSESSION_SPECIFIED', 'error');
			$this->setRedirect(Route::_('index.php?option=com_eqa&view=examsessions',false));
			return;
		}

		//Check permissions
		if(!$app->getIdentity()->authorise('core.edit', $this->option))
		{
			$this->setMessage('COM_EQA_MSG_UNAUTHORISED', 'error');
			$this->setRedirect(Route::_('index.php?option=com_eqa&view=examsessions',false));
			return;
		}

		//Set redirect in the rest cases
		if($task==='apply')
			$this->setRedirect(Route::_('index.php?option=com_eqa&view=examsessionemployees&examsession_id='.$examsessionId,false));
		else
			$this->setRedirect(Route::_('index.php?option=com_eqa&view=examsessions',false));

		//Prepare data
		$data = $app->input->get('jform',[],'array');
		if(empty($data)){
			$this->setMessage('COM_EQA_MSG_INVALID_DATA', 'error');
			return;
		}

		//Gọi model để thực thi
		$model = $this->createModel('examsessionemployees');
		$model->save($examsessionId, $data);
	}
	public function apply()
	{
		$this->save('apply');
	}
}
