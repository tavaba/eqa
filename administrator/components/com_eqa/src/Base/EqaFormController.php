<?php
namespace Kma\Component\Eqa\Administrator\Base;
defined('_JEXEC') or die();

use Exception;
use Joomla\CMS\Factory;
use Joomla\CMS\Language\Text;
use Joomla\CMS\MVC\Controller\FormController;
use Joomla\CMS\Router\Route;
use Kma\Component\Eqa\Administrator\Helper\GeneralHelper;

/**
 * Class 'EqaFormController' sẽ được thừa kế bởi các Item Controllers
 *
 * @since 1.0
 */
class EqaFormController extends FormController
{

    /**
     * Rewrite phương thức 'save' của lớp mẹ để tự động cập nhật giá trị thời gian
     * trong dữ liệu của form trước khi lưu trữ vào CSDL
     *
     * @param $key
     * @param $urlVar
     * @return bool
     * @since 1.0
     */
    public function save($key = null, $urlVar = null)
    {
        //Sửa đổi dữ liệu của form trước khi ghi vào CSDL. Cụ thể là
        //  - Trường 'updated_at' sẽ nhận thời gian hiện tại
        //  - Trường 'created_at' nếu đang là null (khi tạo mới bản ghi) cũng sẽ nhận giá trị là thời gian hiện tại
        try {
            $formDataName='jform';
            $postObject = Factory::getApplication()->input->post;
            $formdata = $postObject->getRaw($formDataName);
            date_default_timezone_set('Asia/Ho_Chi_Minh');
            $isNew = $formdata['id']=='0' || $formdata['id']=='';
            $username = GeneralHelper::getCurrentUsername();
            if($isNew) {
                $formdata['created_by'] = $username;
                $formdata['created_at'] = date('Y-m-d H:i:s');
                $formdata['updated_by'] = null;
                $formdata['updated_at'] = null;
            }
            else{
                $formdata['created_by'] = null;
                $formdata['created_at'] = null;
                $formdata['updated_by'] = $username;
                $formdata['updated_at'] = date('Y-m-d H:i:s');
            }
            $postObject->set($formDataName,$formdata);

        } catch (Exception $e) {
        }

        //Call parent method
        return parent::save($key, $urlVar);
    }

    /**
     * Rewrite phương thức 'allowEdit' của lớp mẹ để bổ sung thêm việc kiểm tra
     * phân quyền 'core.edit.own' thay vì chỉ kiểm tra 'core.edit'
     *
     * @param $data
     * @param $key
     * @param $creator_field string Tên của trường trong bảng chứa username của người đã tạo record
     * @return bool
     * @throws Exception
     * @since  1.0
     */
    public function allowEdit($data = [], $key = 'id', $creator_field='created_by'): bool
    {

        //Check 'core.edit'
        if(parent::allowEdit($data, $key))
            return true;

        //Check 'core.edit.own'
        //Nếu cấu hình không cho phép 'core.edit.own' thì trả về false
        $user = $this->app->getIdentity();
        if(!$user->authorise('core.edit.own', $this->option))
            return false;

        //Nếu không có dữ liệu cụ thể thì trả về false
        if(empty($data))
            return false;

        //Nếu không khớp creator thì trả về false
        $creator = null;
        if(isset($data[$creator_field]))
            $creator = $data[$creator_field];
        else{
            $model = $this->getModel();
            $item = $model->getTable();
            $item->load($data[$key]);
            $creator = $item->$creator_field;
        }
        if($creator != $user->username)
            return false;

        //Nếu sau tất cả kểm tra trên mà chưa false thì trả về true (cho 'core.edit.own')
        return true;
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
            $this->setMessage(Text::_('COM_EQA_MSG_NO_ITEM_SPECIFIED'), 'error');
            return;
        }

        $model = $this->getModel();
        $table = $model->getTable();
        if(!$table->hasField('default'))
        {
            $this->setMessage(Text::_('COM_EQA_MSG_ERROR_NO_FIELD_DEFAULT'), 'error');
            return;
        }

        if(!$model->setDefault($id))
            $this->setMessage(Text::_('COM_EQA_MSG_ERROR_TASK_FAILED'), 'error');
        else
            $this->setMessage(Text::_('COM_EQA_MSG_TASK_SUCCESS'), 'success');
    }


    /**
     * Reset giá trị trường 'ordering' của mọi phần tử theo giá trị trường 'id'.
     * Ở đây sử dụng phương thức 'resetOrdering' được định nghĩa trong lớp 'EqaAdminModel'.
     * Do vậy, khi định nghĩa item model thì cần thừa kế lớp này.
     *
     * @return bool
     * @throws Exception
     * @since 1.0
     */
    public function resetorder(): bool
    {
        // Check for request forgeries.
        $this->checkToken();

        //Set redirect in any case
        $this->setRedirect(
            Route::_(
                'index.php?option=' . $this->option . '&view=' . $this->view_list
                . $this->getRedirectToListAppend(),
                false
            )
        );

        //Access Check
        if(!$this->allowEdit())
        {
            $this->setMessage(Text::_('JLIB_APPLICATION_ERROR_EDIT_NOT_PERMITTED'), 'error');
            return false;
        }

        if($this->getModel()->resetOrdering())
            $this->setMessage(Text::_('COM_EQA_MSG_SUCCESS_RESET_ORDER'), 'success');
        else
            $this->setMessage(Text::_('COM_EQA_MSG_ERROR_RESET_ORDER'),'error');

		return true;
    }
}
