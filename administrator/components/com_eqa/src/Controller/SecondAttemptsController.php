<?php
namespace Kma\Component\Eqa\Administrator\Controller;
defined('_JEXEC') or die();

use Exception;
use Joomla\CMS\Router\Route;
use Kma\Library\Kma\Controller\AdminController;
use Kma\Component\Eqa\Administrator\Model\SecondAttemptsModel;
use Kma\Library\Kma\Helper\ComponentHelper;

class SecondAttemptsController extends AdminController {
	public function refresh():void
	{
		try
		{
			//Check for request forgeries.
			$this->checkToken();

			//Check rights and if not authorised return to home page.
			if(!$this->app->getIdentity()->authorise('core.edit',$this->option))
				throw new Exception('Bạn không có quyền thực hiện chức năng này');

			/**
			 * Call model to refresh the list of second attempts
			 * @var $model SecondAttemptsModel
			 */
			$model = ComponentHelper::getMVCFactory()->createModel('SecondAttempts');
			$countRemoved = $model->cleanup();
			$countAdded = $model->load();

			//Set a success message
			$msg = sprintf('Thành công! Có %d trường hợp được thêm mới', $countAdded);
			$this->setMessage($msg,'success');
		}
		catch (Exception $e)
		{
			$this->setMessage($e->getMessage(), 'error');
		}

		//Redirect to the list view in any case.
		$this->setRedirect(Route::_('index.php?option=com_eqa&view=secondattempts',false));
	}
}
