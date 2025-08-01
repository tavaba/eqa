<?php
namespace Kma\Component\Eqa\Administrator\Controller;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Router\Route;
use JRoute;
use Kma\Component\Eqa\Administrator\Base\EqaFormController;

defined('_JEXEC') or die();

class ExamsessionController extends  EqaFormController {
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
        //This returns filtered valid data or FALSE
        $validData = $model->validate($form, $data);
        if($validData === false)
        {
            //Save the data in the session and Redirect to edit screen
            $app->setUserState($context . '.data', $data);
            $this->setRedirect(JRoute::_('index.php?option=com_eqa&view=examsession&layout=addbatch',false));
            return;
        }

        if(!$model->saveBatch($data)){
            //Save the data in the session and Redirect to edit screen
            $app->setUserState($context . '.data', $data);
            $this->setRedirect(JRoute::_('index.php?option=com_eqa&view=examsession&layout=addbatch',false));
        }
        else{
            //Clear data and Redirect to list view
            $app->setUserState($context . '.data', null);
            $this->setRedirect(JRoute::_('index.php?option=com_eqa&view=examsessions',false));
        }
    }
}