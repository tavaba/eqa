<?php
namespace Kma\Component\Survey\Administrator\View\Forms;

defined('_JEXEC') or die;

use Joomla\CMS\User\User;
use Kma\Component\Survey\Administrator\Base\ItemsHtmlView;
use Kma\Component\Survey\Administrator\Model\FormModel;
use Kma\Library\Kma\Helper\ComponentHelper;
use Kma\Library\Kma\View\ItemAction;
use Kma\Library\Kma\View\ListLayoutItemFieldOption;
use Kma\Library\Kma\View\ListLayoutItemFields;

class HtmlView extends ItemsHtmlView
{
    protected function configureItemFieldsForLayoutDefault(): void
    {
        $option = new ListLayoutItemFields();

        $option->sequence = ListLayoutItemFields::defaultFieldSequence();
        $option->id = ListLayoutItemFields::defaultFieldId();
        $option->check = ListLayoutItemFields::defaultFieldCheck();

        $option->customFieldset1 = array();
        $f = new ListLayoutItemFieldOption('title', 'Tiêu đề',false,true);
        $f->altField = 'description';
        $f->showLinkConditionField = 'allowEdit';
        $option->customFieldset1[] = $f;
        $f = new ListLayoutItemFieldOption('topics', 'Chủ đề');
        $f->printRaw = true;
        $option->customFieldset1[] = $f;
        $option->customFieldset1[] = new ListLayoutItemFieldOption('creator', 'Người tạo');
        $option->customFieldset1[] = new ListLayoutItemFieldOption('modified', 'Sửa lần cuối');

        $option->published = ListLayoutItemFields::defaultFieldPublished();

        //Action - Design
        $option->actions = [];
        $actionDesign = new ItemAction();
        $actionDesign->icon = 'fa-solid fa-wand-magic-sparkles';
        $actionDesign->text = 'Thiết kế phiếu khảo sát';
        $actionDesign->class = '';
        $actionDesign->urlFormatString = 'index.php?option=com_survey&view=form&layout=design&id=%s';
        $actionDesign->displayConditionField = 'allowEdit';
        $option->actions[] = $actionDesign;

        //Action - Preview
        $actionPreview = new ItemAction();
        $actionPreview->icon = 'fa-solid fa-eye';
        $actionPreview->text = 'Xem trước phiếu khảo sát';
        $actionPreview->class = '';
        $actionPreview->urlFormatString = 'index.php?option=com_survey&view=form&layout=preview&id=%s';
        $option->actions[] = $actionPreview;


        //Set the option
        $this->itemFields = $option;
    }
    protected function prepareDataForLayoutDefault(): void
    {
        parent::prepareDataForLayoutDefault();

        //Data preprocessing
        if(!empty($this->layoutData->items))
        {
            /**
             * @var FormModel $formModel
             */
            $formModel = ComponentHelper::createModel('Form');
            $user = new User();

            foreach ($this->layoutData->items as $item)
            {
                $user->load($item->created_by);
                $item->creator = $user->name;
                $user->load($item->modified_by);
                $item->modifier = $user->name;
                if(!empty($item->topics))
                {
                    //$item->topics looks like: "Teaching Evaluation::#ff6666||Exam Feedback::#66ccff"
                    $topics = explode('||', $item->topics);
                    $badges = [];
                    foreach ($topics as $topic)
                    {
                        list($title, $bgColor) = explode('::', $topic);
                        $badges[] = '<span class="badge" style="background-color:' . htmlspecialchars($bgColor) . '; margin-right:4px;">'
                            . htmlspecialchars($title)
                            . '</span>';
                    }
                    $item->topics = implode('<br/>', $badges);
                }

                $item->allowEdit = $formModel->canEdit($item);
            }
        }
    }
}