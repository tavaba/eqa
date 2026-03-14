<?php
namespace Kma\Component\Survey\Administrator\View\Respondentgroupmembers;

defined('_JEXEC') or die;

use Joomla\CMS\Application\CMSApplication;
use Joomla\CMS\Factory;
use Joomla\CMS\Router\Route;
use Kma\Component\Survey\Administrator\Base\ItemsHtmlView;
use Kma\Component\Survey\Administrator\Helper\RespondentHelper;
use Kma\Component\Survey\Administrator\Model\RespondentgroupModel;
use Kma\Library\Kma\Helper\ComponentHelper;
use Kma\Library\Kma\Helper\ToolbarHelper;
use Kma\Library\Kma\View\ListLayoutItemFieldOption;
use Kma\Library\Kma\View\ListLayoutItemFields;

class HtmlView extends ItemsHtmlView
{
    protected $respondentGroup;
    protected function configureItemFieldsForLayoutDefault(): void
    {
        $model = $this->getModel();
        $isPersonList = $model->getState('filter.is_person');

        $option = new ListLayoutItemFields();

        $option->sequence = ListLayoutItemFields::defaultFieldSequence();
        $option->check = ListLayoutItemFields::defaultFieldCheck();

        $option->customFieldset1 = array();
        $option->customFieldset1[] = new ListLayoutItemFieldOption('type', 'Phân loại');
        $option->customFieldset1[] = new ListLayoutItemFieldOption('code', 'Mã');
        if($isPersonList)
        {
            $option->customFieldset1[] = new ListLayoutItemFieldOption('lastname', 'Họ đệm');
            $option->customFieldset1[] = new ListLayoutItemFieldOption('firstname', 'Tên');
            $option->customFieldset1[] = new ListLayoutItemFieldOption('gender', 'Giới tính',false, false,'text-center');
        }
        else
        {
            $option->customFieldset1[] = new ListLayoutItemFieldOption('name', 'Tên gọi');
        }
        $option->customFieldset1[] = new ListLayoutItemFieldOption('email', 'E-Mail');

        //Set the option
        $this->itemFields = $option;
    }
    protected function prepareDataForLayoutDefault(): void
    {
        //Determine the group id from request
        $groupId = Factory::getApplication()->input->getInt('group_id');
        if(empty($groupId))
            die('Truy vấn không hợp lệ!');

        /**
         * Set application state.
         * This value is used in the class 'RespondentGroupMemberUnitField'.
         * @var CMSApplication $app
         */
        $app = Factory::getApplication();
        $app->setUserState('com_survey.respondentgroupmember.group_id', $groupId);


        //Set filter to the model
        $model = $this->getModel();
        $model->setState('filter.group_id', $groupId);
        $isPersonList = $model->getState('filter.is_person');

        //Call parent method
        parent::prepareDataForLayoutDefault();

        //Preprocess data
        if(!empty($this->layoutData->items))
            foreach ($this->layoutData->items as &$item) {
                $item->type = RespondentHelper::decodeType($item->type);
                if($item->gender)
                    $item->gender = RespondentHelper::decodeGender($item->gender);
                if(!$isPersonList && $item->isPerson)
                    $item->name = implode(' ',[$item->lastname,$item->firstname]);
            }

        //Steak the group id for layout use
        $this->layoutData->formHiddenFields['group_id'] = $groupId;

        //Clear the filter
        $model->setState('filter.group_id', null);

        //Get information of respondent group
        $model = ComponentHelper::createModel('Respondentgroup', 'Administrator');
        $this->respondentGroup = $model->getItem($groupId);

        //Set form action params to keep the URL not changed
        $this->layoutData->formActionParams=[
            'view'=>$this->getName(),
            'group_id'=>$groupId
        ];
    }
    protected function addToolbarForLayoutDefault(): void
    {
        /**
         * Load model for access permission checking
         * @var RespondentgroupModel $model
         */
        $model = ComponentHelper::createModel('Respondentgroup');

        ToolbarHelper::title('Quản lý thành viên nhóm khảo sát');
        ToolbarHelper::appendGoHome();
        ToolbarHelper::appendGobackLink(Route::_('index.php?option=com_survey&view=respondentgroups'), 'Nhóm khảo sát');
        if($model->canEdit($this->respondentGroup))
        {
            ToolbarHelper::appendButton('plus-circle','Thêm','respondentgroup.addMembers',false,'btn btn-success');
            ToolbarHelper::appendDelete('respondentgroup.removeMembers');
        }
    }
}