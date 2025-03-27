<?php
namespace Kma\Component\Eqa\Administrator\View\Classlearners; //The namespace must end with the VIEW NAME.
defined('_JEXEC') or die();

use Joomla\CMS\Factory;
use Joomla\CMS\Language\Text;
use Kma\Component\Eqa\Administrator\Base\EqaItemsHtmlView;
use Kma\Component\Eqa\Administrator\Base\EqaListLayoutItemFieldOption;
use Kma\Component\Eqa\Administrator\Base\EqaListLayoutItemFields;
use Kma\Component\Eqa\Administrator\Helper\DatabaseHelper;
use Kma\Component\Eqa\Administrator\Helper\ToolbarHelper;

class HtmlView extends EqaItemsHtmlView {
    protected $class;
    protected function configureItemFieldsForLayoutDefault():void{
        $fields = $this->itemFields;      //Just shorten the name
        $fields->sequence = EqaListLayoutItemFields::defaultFieldSequence();
        $fields->check = EqaListLayoutItemFields::defaultFieldCheck();
        $fields->customFieldset1[] = new EqaListLayoutItemFieldOption('code','COM_EQA_LEARNER_CODE',true,false,'text-center');
        $fields->customFieldset1[] = new EqaListLayoutItemFieldOption('group', 'COM_EQA_GROUP',true,false,'text-center');
        $fields->customFieldset1[] = EqaListLayoutItemFields::defaultFieldLastname();
        $fields->customFieldset1[] = EqaListLayoutItemFields::defaultFieldFirstname();
        $f = new EqaListLayoutItemFieldOption('pam1', 'COM_EQA_PAM1_ABBR', false, false, 'text-center');
        $f->titleDesc = Text::_('COM_EQA_PAM1');
        $fields->customFieldset1[] = $f;
        $f = new EqaListLayoutItemFieldOption('pam2', 'COM_EQA_PAM2_ABBR', false, false, 'text-center');
        $f->titleDesc = Text::_('COM_EQA_PAM2');
        $fields->customFieldset1[] = $f;
        $f = new EqaListLayoutItemFieldOption('pam', 'COM_EQA_PAM_ABBR', false, false, 'text-center');
        $f->titleDesc = Text::_('COM_EQA_PAM');
        $fields->customFieldset1[] = $f;
        $f = new EqaListLayoutItemFieldOption('allowed', 'COM_EQA_ALLOWED_TO_TAKE_EXAM_ABBR', true, false, 'text-center');
        $f->titleDesc = Text::_('COM_EQA_ALLOWED_TO_TAKE_EXAM');
        $fields->customFieldset1[] = $f;
        $f = new EqaListLayoutItemFieldOption('ntaken','COM_EQA_NTAKEN',true,false,'text-center');
        $f->titleDesc = Text::_('COM_EQA_NTAKEN_ALT');
        $fields->customFieldset1[] = $f;
        $f = new EqaListLayoutItemFieldOption('expired','COM_EQA_EXPIRED',true,false,'text-center');
        $f->titleDesc = Text::_('COM_EQA_EXPIRED_ALT');
        $fields->customFieldset1[] = $f;
        $fields->customFieldset1[] = EqaListLayoutItemFields::defaultFieldDescription();
    }
    protected function prepareDataForLayoutDefault(): void
    {
        //Prepare the model before calling parent
        $classId = Factory::getApplication()->input->get('class_id');
        $model = $this->getModel();
        $model->setState('filter.class_id',$classId);
        parent::prepareDataForLayoutDefault();

        //Tham số dưới đây sẽ khiến DisplayController luôn redirect tới view và layout mong muốn
        //giúp cố định 'class_id'
        $this->layoutData->formActionParams = [
            'view'=>'classlearners',
            'layout'=>'default',
            'class_id'=>$classId
        ];

        //Class Item
        $this->class = DatabaseHelper::getClassInfo($classId);

        //Layout data preprocessing
        if(!empty($this->layoutData->items))
        {
            foreach ($this->layoutData->items as $item)
            {
                $item->expired = $item->expired ? Text::_('JYES') : Text::_('JNO');
                if($item->allowed)
                    $item->allowed = Text::_('JYES');
                else {
                    $item->allowed = Text::_('JNO');
                    $item->optionRowCssClass='table-danger';
                }
            }
        }

    }
    protected function addToolbarForLayoutDefault(): void
    {
		ToolbarHelper::title('Danh sách HVSV của lớp học phần');
        ToolbarHelper::appendGoHome();
        ToolbarHelper::appendGoBack('class.cancel','COM_EQA_EDUCLASS');
        ToolbarHelper::addNew('class.addLearners', Text::_('COM_EQA_BUTTON_ADD_LEARNERS_LABEL') );
        ToolbarHelper::appenddButton('core.edit.state','play-circle','COM_EQA_BUTTON_ALLOW_TO_TAKE_EXAM','class.allow',true,'btn btn-success');
        ToolbarHelper::appenddButton('core.edit.state','stop-circle','COM_EQA_BUTTON_DENY_TO_TAKE_EXAM','class.deny',true,'btn btn-danger');
        ToolbarHelper::appendDelete('class.remove');
    }
}
