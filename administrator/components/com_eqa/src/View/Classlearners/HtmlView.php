<?php
namespace Kma\Component\Eqa\Administrator\View\Classlearners; //The namespace must end with the VIEW NAME.
defined('_JEXEC') or die();

use Joomla\CMS\Factory;
use Joomla\CMS\Language\Text;
use Kma\Component\Eqa\Administrator\Base\ItemsHtmlView;
use Kma\Library\Kma\View\ListLayoutItemFieldOption;
use Kma\Library\Kma\View\ListLayoutItemFields;
use Kma\Component\Eqa\Administrator\Helper\DatabaseHelper;
use Kma\Component\Eqa\Administrator\Helper\ExamHelper;
use Kma\Component\Eqa\Administrator\Helper\ToolbarHelper;

class HtmlView extends ItemsHtmlView {
    protected $class;
    protected function configureItemFieldsForLayoutDefault():void{
        $fields = $this->itemFields;      //Just shorten the name
        $fields->sequence = ListLayoutItemFields::defaultFieldSequence();
        $fields->check = ListLayoutItemFields::defaultFieldCheck();
        $fields->customFieldset1[] = new ListLayoutItemFieldOption('code','COM_EQA_LEARNER_CODE',true,false,'text-center');
        $fields->customFieldset1[] = new ListLayoutItemFieldOption('group', 'COM_EQA_GROUP',true,false,'text-center');
        $fields->customFieldset1[] = ListLayoutItemFields::defaultFieldLastname();
        $fields->customFieldset1[] = ListLayoutItemFields::defaultFieldFirstname();
        $f = new ListLayoutItemFieldOption('pam1', 'COM_EQA_PAM1_ABBR', false, false, 'text-center');
        $f->titleDesc = Text::_('COM_EQA_PAM1');
        $fields->customFieldset1[] = $f;
        $f = new ListLayoutItemFieldOption('pam2', 'COM_EQA_PAM2_ABBR', false, false, 'text-center');
        $f->titleDesc = Text::_('COM_EQA_PAM2');
        $fields->customFieldset1[] = $f;
        $f = new ListLayoutItemFieldOption('pam', 'COM_EQA_PAM_ABBR', false, false, 'text-center');
        $f->titleDesc = Text::_('COM_EQA_PAM');
        $fields->customFieldset1[] = $f;
        $f = new ListLayoutItemFieldOption('allowed', 'COM_EQA_ALLOWED_TO_TAKE_EXAM_ABBR', true, false, 'text-center');
        $f->titleDesc = Text::_('COM_EQA_ALLOWED_TO_TAKE_EXAM');
        $fields->customFieldset1[] = $f;
        $f = new ListLayoutItemFieldOption('ntaken','COM_EQA_NTAKEN',true,false,'text-center');
        $f->titleDesc = Text::_('COM_EQA_NTAKEN_ALT');
        $fields->customFieldset1[] = $f;
        $f = new ListLayoutItemFieldOption('expired','COM_EQA_EXPIRED',true,false,'text-center');
        $f->titleDesc = Text::_('COM_EQA_EXPIRED_ALT');
        $fields->customFieldset1[] = $f;
        $fields->customFieldset1[] = ListLayoutItemFields::defaultFieldDescription();
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
            'class_id'=>$classId
        ];

        //Class Item
        $this->class = DatabaseHelper::getClassInfo($classId);

        //Layout data preprocessing
        if(!empty($this->layoutData->items))
        {
            foreach ($this->layoutData->items as $item)
            {
				ExamHelper::normalizeMarks($item);
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
        ToolbarHelper::appendButton('core.edit.state','play-circle','COM_EQA_BUTTON_ALLOW_TO_TAKE_EXAM','class.allow',true,'btn btn-success');
        ToolbarHelper::appendButton('core.edit.state','stop-circle','COM_EQA_BUTTON_DENY_TO_TAKE_EXAM','class.deny',true,'btn btn-danger');
        ToolbarHelper::appendDelete('class.remove');
	    ToolbarHelper::addNew('class.addLearners', 'Người học');
	    ToolbarHelper::appendUpload('class.importLearners', 'Người học');
	    ToolbarHelper::appendUpload('class.importPams', 'Điểm QT');
	    ToolbarHelper::appendButton('eqa.edit.mark','edit','Sửa ĐQT','class.editPam',true);
    }
}
