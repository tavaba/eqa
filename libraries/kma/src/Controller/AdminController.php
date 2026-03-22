<?php
namespace Kma\Library\Kma\Controller;
defined('_JEXEC') or die();

use Exception;
use Joomla\CMS\Application\CMSWebApplicationInterface;
use Joomla\CMS\Language\Text;
use Joomla\CMS\MVC\Controller\AdminController as BaseAdminController;
use Joomla\CMS\MVC\Factory\MVCFactoryInterface;
use Joomla\CMS\MVC\Model\BaseDatabaseModel;
use Joomla\CMS\Router\Route;
use Joomla\CMS\User\CurrentUserInterface;
use Joomla\Input\Input;
use Kma\Library\Kma\Helper\ComponentHelper;
use Kma\Library\Kma\Helper\EnglishHelper;
use Kma\Library\Kma\Service\EnglishService;
use Kma\Library\Kma\Service\LogService;

/**
 * This class will be inherited by some Items Controllers
 * @since 1.0
 */

class AdminController extends BaseAdminController
{
	/** An instance of LogService that is retrived from DIC by default */
	protected ?LogService $logService=null;
	protected ?EnglishService $englishService=null;
	public function __construct($config = [], ?MVCFactoryInterface $factory = null, ?CMSWebApplicationInterface $app = null, ?Input $input = null)
	{
		//Call parent constructor
		parent::__construct($config, $factory, $app, $input);

		//Resolve the LogService instance
		$this->logService = ComponentHelper::getLogService();
		$this->englishService = ComponentHelper::getEnglishService();
	}
	/**
	 * Thiêt lập LogService thay cho instance được khởi tạo mặc định trong constructor
	 * @param   LogService  $logService
	 * @since 1.0.3
	 */
	public function setLogService(LogService $logService)
	{
		$this->logService = $logService;
	}

    /**
     * AdminController là lớp helper sẽ được thừa kế bởi các Items Controllers.
     * Phương thức getModel mặc định sẽ trả về Items Model, vốn là ListModel (được sử dụng bởi EntryPoint Display Controller)
     * Model đó sẽ không hỗ trợ các tác vụ quản trị cần thiết: delete, publish...
     * Do vậy, cần rewrite phương thức này để trả về model khác, cụ thể là Item Model, vốn là AdminModel
     * @param $name
     * @param $prefix
     * @param $config
     * @return bool|BaseDatabaseModel|CurrentUserInterface
     * @throws Exception
     * @since 1.0
     */
    public function getModel($name = '', $prefix = '', $config = [])
    {
        $controllerName = $this->getName();
        $modelName = $this->englishService
	        ? $this->englishService->pluralToSingular($controllerName)
	        : EnglishHelper::pluralToSingular($controllerName);
        if(empty($name))
            $name = $modelName;
        return parent::getModel($name, $prefix, $config);
    }

    public function delete()
    {
        try {
            parent::delete();
        }
        catch (Exception $e){
            $msg = $e->getMessage();
            $this->app->enqueueMessage(htmlspecialchars($msg),'error');
            $this->setRedirect(
                Route::_(
                    'index.php?option=' . $this->option . '&view=' . $this->view_list
                    . $this->getRedirectToListAppend(),
                    false
                )
            );
        }
    }


	/**
	 * Đơn giản là chuyển hướng tới items view 'upload' và hiển thị form để upload.
	 * Form này sẽ gửi dữ liệu tới task 'import' để xử lý.
	 *
	 * @return bool
	 * @since 1.0.2
	 */
	public function upload(): bool
	{
		$targetLayout = 'upload';

		// Access check
		if (!$this->allowImport()) {
			// Set the internal error and also the redirect error.
			$this->setMessage(Text::_('JLIB_APPLICATION_ERROR_BATCH_CANNOT_CREATE'), 'error');

			$this->setRedirect(
				Route::_(
					'index.php?option=' . $this->option . '&view=' . $this->view_list
					. $this->getRedirectToListAppend(),
					false
				)
			);
			return false;
		}

		// Redirect to the edit screen.
		$this->setRedirect(
			Route::_(
				'index.php?option=' . $this->option . '&view=' . $this->view_list
				. '&layout='.$targetLayout,
				false
			)
		);
		return true;
	}

	//TODO: Replace the following method
	protected function allowImport():bool
	{
		$user = $this->app->getIdentity();
		return $user->authorise('core.create', $this->option);
	}


}