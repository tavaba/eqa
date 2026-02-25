<?php
namespace Kma\Component\Survey\Administrator\Controller;
defined('_JEXEC') or die();

use Exception;
use Joomla\CMS\Router\Route;
use Kma\Component\Survey\Administrator\Model\FormModel;
use Kma\Library\Kma\Controller\FormController as BaseFormController;


class FormController extends  BaseFormController
{
    protected function allowAdd($data = [], $specificPermission = 'com.create.form'): bool
    {
        return parent::allowAdd($data, $specificPermission);
    }

    public function saveModel()
    {
        try {
            //1. Check token
            $this->checkToken();

            /**
             * 2. Get id and check permission
             * @var FormModel $model
             */
            $model = $this->getModel();
            $input = $this->app->input->post;
            $formId = $input->getInt('id');
            if (empty($formId))
                throw new Exception('Truy vấn không hợp lệ');
            if (!$model->canEdit($formId))
                throw new Exception('Bạn không có quyền thực hiện tác vụ này');

            //3. Get data from request, validate and save it
            $jsonString = $this->app->input->post->getRaw('model');
            if(json_decode($jsonString)===false)
                throw new Exception('Dữ liệu nhập vào không đúng định dạng');
            $model->saveJson($formId, $jsonString);

            //4. Set a success message
            $this->setMessage('Phiếu khảo sát được lưu thành công','success');
        }
        catch (Exception $e) {
            $this->setMessage($e->getMessage(), 'error');
        }
        $this->setRedirect(Route::_('index.php?option=com_survey&view=forms',false));
    }
    public function applyModel():void
    {
        try {
            $this->saveModel();
            $id = $this->app->input->post->getInt('id');
            $this->setRedirect(Route::_('index.php?option=com_survey&view=form&layout=design&id='.$id,false));
        }
        catch(Exception $e){
            $this->setMessage($e->getMessage(),'error');
        }
    }
}