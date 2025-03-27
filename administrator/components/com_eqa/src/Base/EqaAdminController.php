<?php
namespace Kma\Component\Eqa\Administrator\Base;
defined('_JEXEC') or die();

use Exception;
use Joomla\CMS\Factory;
use Joomla\CMS\Language\Text;
use Joomla\CMS\MVC\Controller\AdminController;
use Joomla\CMS\MVC\Model\BaseDatabaseModel;
use Joomla\CMS\Router\Route;
use Joomla\CMS\User\CurrentUserInterface;
use Kma\Component\Eqa\Administrator\Helper\StringHelper;

/**
 * This class will be inherited by some Items Controllers
 * @since 1.0
 */

class EqaAdminController extends AdminController
{

    /**
     * EqaAdminController là lớp helper sẽ được thừa kế bởi các Items Controllers trong EQA_COMPONENT.
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
        $modelName = StringHelper::convertPluralToSingle($controllerName);
        if(empty($name))
            $name = $modelName;
        return parent::getModel($name, $prefix, $config);
    }

	/**
     * Đơn giản là chuyển hướng tới items view 'upload' và hiển thị form để upload.
     * Form này sẽ gửi dữ liệu tới task 'import' để xử lý.
     *
     * @return bool
     * @since 1.0
     */
    public function upload(): bool
    {
        $targetLayout = 'upload';

        // Access check
        if (!$this->app->getIdentity()->authorise('core.create',$this->option)) {
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
}