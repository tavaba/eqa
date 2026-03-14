<?php
namespace Kma\Component\Survey\Administrator\View\Respondentgroup;

defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Kma\Component\Survey\Administrator\Base\ItemHtmlView;
use Kma\Component\Survey\Administrator\Helper\RespondentHelper;
use Kma\Component\Survey\Administrator\Model\RespondentgroupModel;
use Kma\Component\Survey\Administrator\Model\RespondentsModel;
use Kma\Library\Kma\Helper\ComponentHelper;
use Kma\Library\Kma\Helper\ToolbarHelper;
use Kma\Library\Kma\View\ListLayoutData;
use Kma\Library\Kma\View\ListLayoutItemFieldOption;
use Kma\Library\Kma\View\ListLayoutItemFields;

class HtmlView extends ItemHtmlView
{
    protected ListLayoutData $listLayoutData;
    protected function prepareDataForLayoutAddmembers():void
    {
        //Determine the group id from the request
        $groupId = Factory::getApplication()->input->getInt('group_id');
        if(empty($groupId))
            die('Truy vấn không hợp lệ');

        /**
         * Load the default model ('Respondentgroup') and load the item object.
         * @var RespondentgroupModel $model
         */
        $model = $this->getModel();
        $this->item = $model->getItem($groupId);

        /**
         * We'll utilize the 'Respondents' model to retrieve all members of this respondent group.
         * @var RespondentsModel $listModel
         */
        $listModel = ComponentHelper::createModel('Respondents');
        $isPersonList = $listModel->getState('filter.is_person');


        //Init list layout item fields
        $option = new ListLayoutItemFields();

        $option->sequence = ListLayoutItemFields::defaultFieldSequence();
        $option->check = ListLayoutItemFields::defaultFieldCheck();

        $option->customFieldset1 = array();
        $option->customFieldset1[] = new ListLayoutItemFieldOption('code', 'Mã', false, true);
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
        $option->customFieldset1[] = new ListLayoutItemFieldOption('typeText', 'Phân loại');
        $option->customFieldset1[] = new ListLayoutItemFieldOption('email', 'E-Mail');

        //Set the option
        $this->itemFields = $option;


        //Call parent method for preparing data for layout
        $this->listLayoutData = new ListLayoutData();
        parent::loadCommonListLayoutData($this->listLayoutData, $listModel);

        //Data preprocessing before rendering the view
        if(!empty($this->listLayoutData->items))
        {
            foreach ($this->listLayoutData->items as $item)
            {
                $item->typeText = RespondentHelper::decodeType($item->type);
                if($item->gender)
                    $item->gender = RespondentHelper::decodeGender($item->gender);
                if(!$isPersonList && $item->is_person)
                    $item->name = implode(' ',[$item->lastname,$item->firstname]);
            }
        }


        $this->listLayoutData->formActionParams = [
            'view'=>'respondentgroup',
            'layout'=>'addmembers',
            'group_id'=>$groupId
        ];
    }
    protected function addToolbarForLayoutAddmembers():void
    {
        /**
         * @var RespondentgroupModel $model
         */
        $model = $this->getModel();

        ToolbarHelper::title('Thêm thành viên vào nhóm khảo sát');

        if($model->canEdit($this->item))
            ToolbarHelper::save('respondentgroup.addMembers');

        $cancelUrl = 'index.php?option=com_survey&view=respondentgroupmembers&group_id='.$this->item->id;
        ToolbarHelper::appendCancelLink($cancelUrl);
    }
}