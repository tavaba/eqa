<?php
namespace Kma\Component\Survey\Administrator\View\Topics;

defined('_JEXEC') or die;

use Joomla\CMS\User\User;
use Kma\Component\Survey\Administrator\Base\ItemsHtmlView;
use Kma\Component\Survey\Administrator\Model\TopicModel;
use Kma\Library\Kma\Helper\ComponentHelper;
use Kma\Library\Kma\View\ListLayoutItemFieldOption;
use Kma\Library\Kma\View\ListLayoutItemFields;

class HtmlView extends ItemsHtmlView
{
    protected function configureItemFieldsForLayoutDefault(): void
    {
        $option = new ListLayoutItemFields();

        $option->sequence = ListLayoutItemFields::defaultFieldSequence();
        $option->check = ListLayoutItemFields::defaultFieldCheck();

        $option->customFieldset1 = array();
        $f = new ListLayoutItemFieldOption('title', 'Chủ đề', true, true);
        $f->showLinkConditionField = 'canEdit';
        $option->customFieldset1[] = $f;
        $option->customFieldset1[] = new ListLayoutItemFieldOption('description','Mô tả');
        $option->customFieldset1[] = new ListLayoutItemFieldOption('creatorName', 'Người tạo');
        $option->customFieldset1[] = new ListLayoutItemFieldOption('modified', 'Cập nhật lần cuối');

        $option->published = ListLayoutItemFields::defaultFieldPublished();

        //Set the option
        $this->itemFields = $option;
    }
    protected function prepareDataForLayoutDefault(): void
    {
        parent::prepareDataForLayoutDefault();
        $user = new User();

        //Data preprocessing
        if(!empty($this->layoutData->items))
        {
            /**
             * Load admin (item) model for access control
             * @var TopicModel $model
             */
            $model = ComponentHelper::getMVCFactory()->createModel('Topic');

            foreach ($this->layoutData->items as &$item)
            {
                $user->load($item->created_by);
                $item->creatorName = $user->name;
                $user->load($item->modified_by);
                $item->modifierName = $user->name;
                $item->canEdit = $model->canEdit($item);
            }
        }
    }
}