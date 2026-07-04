<?php
namespace Kma\Component\Eqa\Administrator\Controller;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Router\Route;
use Kma\Library\Kma\Controller\FormController;

defined('_JEXEC') or die();

class ExamsessionController extends  FormController {
    public function addBatch()
    {
        $context = "$this->option.addbatch.$this->context";

        // Access check.
        if (!$this->allowAdd()) {
            // Set the internal error and also the redirect error.
            $this->setMessage(Text::_('JLIB_APPLICATION_ERROR_CREATE_RECORD_NOT_PERMITTED'), 'error');

            $this->setRedirect(
                Route::_(
                    'index.php?option=' . $this->option . '&view=' . $this->view_list
                    . $this->getRedirectToListAppend(),
                    false
                )
            );

            return false;
        }

        // Clear the record edit information from the session.
        $this->app->setUserState($context . '.data', null);

        // Redirect to the edit screen.
        $this->setRedirect(
            Route::_(
                'index.php?option=' . $this->option . '&view=' . $this->view_item . '&layout=addbatch',
                false
            )
        );

        return true;
    }
    public function saveBatch(){
        //Check for forgeries
        $this->checkToken();

        // Access check.
        if (!$this->allowAdd()) {
            // Set the internal error and also the redirect error.
            $this->setMessage(Text::_('JLIB_APPLICATION_ERROR_CREATE_RECORD_NOT_PERMITTED'), 'error');

            $this->setRedirect(
                Route::_(
                    'index.php?option=' . $this->option . '&view=' . $this->view_list
                    . $this->getRedirectToListAppend(),
                    false
                )
            );
            return;
        }

        //Do the thing
        $context = "$this->option.addbatch.$this->context";
        $app = $this->app;
        $model = $this->getModel();
        $data    = $this->input->post->get('jform', [], 'array');
        $form = $model->getAddbatchForm();

//Filter and Validate $data.
	    //This returns filtered valid data or FALSE.
	    //Với subform multiple, Joomla đệ quy filter từng dòng => $validData['examsessions'][N]['start']
	    //đã được filter="user_utc" chuyển sang UTC.
	    $validData = $model->validate($form, $data);
	    if ($validData === false) {
		    // Lưu dữ liệu THÔ (local, như người dùng đã gõ) vào session để repopulate form
		    $app->setUserState($context . '.data', $data);
		    $this->setRedirect(Route::_('index.php?option=com_eqa&view=examsession&layout=addbatch', false));
		    return;
	    }

	    // Lưu dữ liệu ĐÃ FILTER (start đã là UTC), KHÔNG dùng $data thô.
	    if (!$model->saveBatch($validData)) {
		    $app->setUserState($context . '.data', $data);
		    $this->setRedirect(Route::_('index.php?option=com_eqa&view=examsession&layout=addbatch', false));
	    } else {
		    $app->setUserState($context . '.data', null);
		    $this->setRedirect(Route::_('index.php?option=com_eqa&view=examsessions', false));
	    }
	}
}