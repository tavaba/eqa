<?php
namespace Kma\Component\Survey\Administrator\Controller;
defined('_JEXEC') or die();

use Exception;
use Joomla\CMS\Router\Route;
use Kma\Component\Survey\Administrator\Model\RespondentgroupModel;
use Kma\Library\Kma\Controller\FormController;


class RespondentgroupController extends  FormController {
    protected function allowAdd($data = [], $specificPermission = 'com.create.rgroup'): bool
    {
        return parent::allowAdd($data, $specificPermission);
    }
    public function addMembers(): void
    {
        try {
            //1. Check form token
            $this->checkToken();

            //2. Retreive the group id from request data
            $groupId = $this->input->getInt('group_id');
            if(empty($groupId))
                throw new Exception('Truy vấn không hợp lệ');

            /*
             * 3. Check if the user is allowed to add members.
             * A user is allowed to add members to a group if
             * he/she has permission to edit this group
             */
            if(!$this->allowEdit(['id'=>$groupId]))
                throw new Exception('Bạn không có quyền thực hiện tác vụ này');

            //4. Try to retreive the list of respondents to be added into this group
            $respondentIds = $this->input->get('cid',[],'array');
            $respondentIds = array_filter($respondentIds,'intval');
            $respondentIds = array_unique($respondentIds);

            //PHASE 1: THE ARRAY IS EMPTY ==> Show form to select respondents to be added into this group
            if(empty($respondentIds))
            {
                $redirect = Route::_('index.php?option=com_survey&view=respondentgroup&layout=addmembers&group_id='.$groupId, false);
                $this->setRedirect($redirect);
                return;
            }

            //PHASE 2: THE ARRAY IS NOT EMPTY ==> Add respondents into this group and redirect back to the edit page for this group
            $model = $this->getModel();
            $countAdded = $model->addMembers($groupId,$respondentIds);
            $msg = sprintf('%d thành viên đã tồn tại, %d thành viên đã được thêm vào nhóm',
                count($respondentIds)-$countAdded, $countAdded);
            $this->setMessage($msg);
            $redirect = Route::_('index.php?option=com_survey&view=respondentgroupmembers&group_id='.$groupId, false);
            $this->setRedirect($redirect);

        }
        catch (Exception $e) {
            $this->setMessage($e->getMessage(), 'error');
            $redirect = Route::_('index.php?option=com_survey&view=respondentgroups', false);
            $this->setRedirect($redirect);
        }
    }
    public function removeMembers(): void
    {
        try {
            //1. Check form token
            $this->checkToken();

            //2. Retreive the group id from request data
            $groupId = $this->input->getInt('group_id');
            if(empty($groupId))
                throw new Exception('Truy vấn không hợp lệ');

            /*
             * 3. Check if the user is allowed to remove members.
             * A user is allowed to remove members from a group if
             * he/she has permission to edit this group
             */
            if(!$this->allowEdit(['id'=>$groupId]))
                throw new Exception('Bạn không có quyền thực hiện tác vụ này');

            //4. Try to retreive the list of respondents to be added into this group
            $respondentIds = $this->input->get('cid',[],'array');
            $respondentIds = array_filter($respondentIds,'intval');
            $respondentIds = array_unique($respondentIds);

            //PHASE 1: THE ARRAY IS EMPTY ==> Redirect to the respondentgroup members page
            if(empty($respondentIds))
            {
                $redirect = Route::_('index.php?option=com_survey&view=respondentgroupmembers&group_id='.$groupId, false);
                $this->setRedirect($redirect);
                return;
            }

            /**
             * PHASE 2: THE ARRAY IS NOT EMPTY ==> Remove respondents from this group and redirect back
             * @var RespondentgroupModel $model
             */
            $model = $this->getModel();
            $countRemoved = $model->removeMembers($groupId,$respondentIds);
            $msg = sprintf('%d thành viên đã được xóa khỏi nhóm', $countRemoved);
            $this->setMessage($msg);
            $redirect = Route::_('index.php?option=com_survey&view=respondentgroupmembers&group_id='.$groupId, false);
            $this->setRedirect($redirect);

        }
        catch (Exception $e) {
            $this->setMessage($e->getMessage(), 'error');
            $redirect = Route::_('index.php?option=com_survey&view=respondentgroups', false);
            $this->setRedirect($redirect);
        }
    }
}