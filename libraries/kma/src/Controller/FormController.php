<?php
namespace Kma\Library\Kma\Controller;
defined('_JEXEC') or die();

use Exception;
use Joomla\CMS\Language\Text;
use Joomla\CMS\MVC\Controller\FormController as BaseFormController;
use Joomla\CMS\Router\Route;

/**
 * Class 'FormController' sẽ được thừa kế bởi các Item Controllers
 *
 * @since 1.0
 */
class FormController extends BaseFormController
{
    protected function allowAdd($data = [], $specificPermission=null): bool
    {
        if(parent::allowAdd($data))
            return true;
        return $specificPermission && $this->app->getIdentity()->authorise($specificPermission,$this->option);
    }

    protected function allowEdit($data = [], $key = 'id')
    {
        $itemId = $data[$key] ?? null;
        $model = $this->getModel();
        return $model->canEdit($itemId);
    }

    /**
     * Set or Unset the 'default' status of an item
     *
     * @return void
     * @throws Exception
     * @since 1.0
     */
    public function setDefault():void
    {
        $textKeyPrefix = strtoupper($this->option).'_MSG_';

        //Set redirect to list view in any case
        $this->setRedirect(
            Route::_(
                'index.php?option=' . $this->option . '&view=' . $this->view_list
                . $this->getRedirectToListAppend(),
                false
            )
        );

        //The record to set
        $id = $this->app->input->get('id',null,'int');
        if(!is_numeric($id)){
            $textKey = $textKeyPrefix . 'NO_ITEM_SPECIFIED';
            $this->setMessage(Text::_($textKey), 'error');
            return;
        }

        $model = $this->getModel();
        $table = $model->getTable();
        if(!$table->hasField('default'))
        {
            $textKey = $textKeyPrefix . 'ERROR_NO_FIELD_DEFAULT';
            $this->setMessage($textKey, 'error');
            return;
        }

        if(!$model->setDefault($id))
            $this->setMessage(Text::_($textKeyPrefix . 'ERROR_TASK_FAILED'), 'error');
        else
            $this->setMessage(Text::_($textKeyPrefix . 'TASK_SUCCESS'), 'success');
    }
}
