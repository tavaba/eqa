<?php

namespace Kma\Component\Eqa\Site\Helper;
use JForm;
use JHtml;
use Joomla\CMS\HTML\HTMLHelper;
use JRoute;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Layout\LayoutHelper;
use Joomla\CMS\Pagination\Pagination;
use Joomla\CMS\Router\Route;
use Kma\Component\Eqa\Administrator\Base\EqaItemAction;
use Kma\Component\Eqa\Administrator\Base\EqaListLayoutData;
use Kma\Component\Eqa\Administrator\Base\EqaItemsHtmlView;
use Kma\Component\Eqa\Administrator\Base\EqaListLayoutItemFieldOption;
use Kma\Component\Eqa\Administrator\Base\EqaListLayoutItemFields;

defined('_JEXEC') or die();

abstract class ViewHelper
{
    public static function printForm(JForm $form, string $fieldsetName, string $actionUrl, array $hiddenFields, bool $submit=false):void
    {
        HTMLHelper::_('behavior.formvalidator');
        ?>
        <form action="<?php echo JRoute::_($actionUrl);?>" method="POST" name="adminForm" id="adminForm" class="form-validate" >
            <?php
                echo $form->renderFieldset($fieldsetName);
                echo JHtml::_('form.token');
                foreach ($hiddenFields as $field=>$value)
                    echo "<input type=\"hidden\" name=\"$field\" value=\"$value\"/>";
                if(!isset($hiddenFields['task']))
                    echo "<input type=\"hidden\" name=\"task\"/>";
                if($submit)
                    echo "<input type=\"submit\" value=\"Submit\">"
            ?>
        </form>
        <?php
    }

    /**
     * Phương thức này giúp hiển thị edit frorm một cách thống nhất cho các item
     *
     * @param JForm $form Đối tượng chứa form.
     * @param int|null $itemId
     * @param string $fieldsetName
     * @return void
     * @since 1.0
     */
    public static function printItemEditLayout(JForm $form, int|null $itemId, string $fieldsetName):void{
        HTMLHelper::_('behavior.formvalidator');
        ?>
        <form action="<?php echo JRoute::_('index.php?option=com_eqa&layout=edit&id='.$itemId);?>" method="POST" name="adminForm" id="adminForm" class="form-validate" >
            <input type="hidden" name="task" value=""/>
            <input type="hidden" name="boxchecked" value="0"/>
            <?php
            echo $form->renderFieldset($fieldsetName);
            ?>
            <?php echo JHtml::_('form.token');?>
        </form>
        <?php
    }

    protected static function printItemsTableFieldHead(EqaListLayoutItemFieldOption $field, string $listOderingField, string $listOrderingDirection):void
    {
        echo '<th class="text-center" title="'. Text::_($field->titleDesc) .'">';
        if($field->sortable){
            echo HTMLHelper::_('grid.sort',
                $field->title,
                $field->name,
                $listOrderingDirection,
                $listOderingField
            );
        }
        else
            echo Text::_($field->title);
        echo '</th>';
    }
    protected static function printItemsTableFieldValue($item, EqaListLayoutItemFieldOption $field, $editUrl):void {
        $property  = $field->name;
        $value = $item->$property;
        if(empty($field->altField))
            echo '<td class="' . $field->cssClass . '">';
        else {
            $alt = $field->altField;
            if(!empty($item->$alt))
                $title = htmlspecialchars($item->$alt, ENT_QUOTES, 'UTF-8');
            else
                $title ='';
            $tmp = '<td class="' . $field->cssClass . '" title="' . $title.'">';
            echo $tmp;
            //echo '<td class="' . $field->class . '" title="' . $title.'">';
        }
        if($field->hasEditUrl && empty($item->optionIgnoreEditUrl))
            echo '<a href="' . $editUrl . '">' . $value . '</a>';
        else if(!empty($field->urlFormatString)){
            $url = sprintf($field->urlFormatString,$item->id);
            $url = JRoute::_($url, false);
            echo '<a href="' . $url . '">' . $value . '</a>';
        }
        else
            echo $value;
        echo '</td>';
    }
    protected static function printItemsDefaultLayoutItem(EqaListLayoutData $layoutData, int $itemIndex, EqaListLayoutItemFields $itemFields, string|null $editUrl=null, string|null $setDefaultUrl=null):void {
        $item = $layoutData->items[$itemIndex];

        if(!empty($item->optionRowCssClass))
            echo '<tr class="'.$item->optionRowCssClass.'">';
        else
            echo '<tr>';

        //First standard fields
        if(isset($itemFields->sequence)){
            $field = $itemFields->sequence;
            if(empty($field->cssClass))
                $field->cssClass = 'text-center';
            echo '<td class="' . $field->cssClass . '">';
            if(isset($layoutData->pagination))
                echo $layoutData->pagination->limitstart + $itemIndex + 1;
            else
                echo $itemIndex + 1;
            echo '</td>';
        }
        if(isset($itemFields->id)) {
            $field = $itemFields->id;
            if(empty($field->cssClass))
                $field->cssClass = 'text-center';
            self::printItemsTableFieldValue($item, $field, $editUrl);
        }
        if(isset($itemFields->check)){
            $field = $itemFields->check;
            if(empty($field->cssClass))
                $field->cssClass = 'text-center';
            echo '<td class="' . $field->cssClass . '">';
            echo HTMLHelper::_('grid.id',$itemIndex,$item->id);
            echo '</td>';
        }

        //First custom fields
        if(isset($itemFields->customFieldset1)){
            $n = sizeof($itemFields->customFieldset1);
            for($i=0; $i<$n; $i++){
                $field = $itemFields->customFieldset1[$i];
                self::printItemsTableFieldValue($item, $field, $editUrl);
            }
        }

        //Next standard fields: Default, Status, Ordering
        if(isset($itemFields->default)){
            $field = $itemFields->default;
            if(empty($field->cssClass))
                $field->cssClass = 'text-center';
            echo '<td class="' . $field->cssClass . '">';
            if(empty($item->optionIgnoreToggleDefaultButton))
            {
                if($item->default)
                    echo '<a href="'.$setDefaultUrl.'" class="tbody-icon active" ><span class="icon-publish" aria-hidden="true"></span></a>';
                else
                    echo '<a href="'.$setDefaultUrl.'" class="tbody-icon"><span class="icon-unpublish" aria-hidden="true"></span></a>';
            }
            else
                echo '<span class="tbody-icon"><span class="icon-unpublish" aria-hidden="true"></span></span>';
            echo '</td>';
        }

        if(isset($itemFields->order)){
            $field = $itemFields->order;
            if(empty($field->cssClass))
                $field->cssClass = 'text-center';
            echo '<td class="' . $field->cssClass . '">';
            if($layoutData->sortByOrder){
                if($layoutData->listOrderingDirection=='asc'){
                    echo '<span>'.$layoutData->pagination->orderUpIcon($itemIndex, true, $layoutData->taskPrefixItems.'.orderup', 'JLIB_HTML_MOVE_UP',true);
                    echo '<span>'.$layoutData->pagination->orderDownIcon($itemIndex, $layoutData->pagination->total, true, $layoutData->taskPrefixItems.'.orderdown', 'JLIB_HTML_MOVE_DOWN',true);
                }
                else{
                    echo '<span>'.$layoutData->pagination->orderUpIcon($itemIndex, true, $layoutData->taskPrefixItems.'.orderdown', 'JLIB_HTML_MOVE_UP',true);
                    echo '<span>'.$layoutData->pagination->orderDownIcon($itemIndex, $layoutData->pagination->total, true, $layoutData->taskPrefixItems.'.orderup', 'JLIB_HTML_MOVE_DOWN',true);
                }
            }
            else
                echo $item->ordering;
            echo '</td>';
        }

        if(isset($itemFields->published)){
            $field = $itemFields->published;
            if(empty($field->cssClass))
                $field->cssClass = 'text-center';
            echo '<td class="' . $field->cssClass . '">';
            $taskprefix = $layoutData->taskPrefixItems.'.';
            echo HTMLHelper::_('jgrid.published',$item->published, $itemIndex, $taskprefix);
            echo '</td>';
        }

        //Last custom fields
        if(isset($itemFields->customFieldset2)){
            $n = sizeof($itemFields->customFieldset2);
            for($i=0; $i<$n; $i++){
                $field = $itemFields->customFieldset2[$i];
                self::printItemsTableFieldValue($item, $field, $editUrl);
            }
        }

        //Actions on item
        if(!empty($itemFields->actions)){
            echo '<td>';
            $isFirstAction=true;
            foreach ($itemFields->actions as $action)
            {
                if(!$isFirstAction)
                    echo ' ';
                $action = EqaItemAction::cast($action);
                echo $action->getHtml($item);
                $isFirstAction = false;
            }
            echo '</td>';
        }

        //end
        echo '</tr>';
    }
    protected static function printTableOfItems(EqaListLayoutData $layoutData, EqaListLayoutItemFields $itemFields):void
    {
        echo '<table class="table table-bordered table-hover">';
        {
            echo '<thead>';
            {
                //First standard fields
                if(isset($itemFields->sequence)) {
                    $field = $itemFields->sequence;
                    echo '<th class="text-center" title="'. Text::_($field->titleDesc) .'">';
                    echo Text::_($itemFields->sequence->title);
                    echo '</th>';
                }

                if(isset($itemFields->id))
                    self::printItemsTableFieldHead($itemFields->id, $layoutData->listOrderingField, $layoutData->listOrderingDirection);

                if(isset($itemFields->check)){
                    $field = $itemFields->check;
                    echo '<th class="text-center" title="'. Text::_($field->titleDesc) .'">';
                    echo HTMLHelper::_('grid.checkall');
                    echo '</th>';
                }

                //First custom fields
                if(isset($itemFields->customFieldset1))
                {
                    $n = sizeof($itemFields->customFieldset1);
                    for($i=0; $i<$n; $i++){
                        $field = $itemFields->customFieldset1[$i];
                        self::printItemsTableFieldHead($field, $layoutData->listOrderingField, $layoutData->listOrderingDirection);
                    }
                }

                //Next standard fields: 'status', 'ordering', 'action'
                if(isset($itemFields->default))
                    self::printItemsTableFieldHead($itemFields->default, $layoutData->listOrderingField, $layoutData->listOrderingDirection);

                if(isset($itemFields->published))
                    self::printItemsTableFieldHead($itemFields->published, $layoutData->listOrderingField, $layoutData->listOrderingDirection);

                if(isset($itemFields->order))
                    self::printItemsTableFieldHead($itemFields->order, $layoutData->listOrderingField, $layoutData->listOrderingDirection);

                //Next custom fields
                if(isset($itemFields->customFieldset2)){
                    $n = sizeof($itemFields->customFieldset2);
                    for($i=0; $i<$n; $i++){
                        $field = $itemFields->customFieldset2[$i];
                        self::printItemsTableFieldHead($field, $layoutData->listOrderingField, $layoutData->listOrderingDirection);
                    }
                }

                //Actions on item
                if(!empty($itemFields->actions)){
                    echo '<th>&nbsp;</th>';
                }
            }
            echo '</thead>';
            echo '<tbody>';
            {
                if(!empty($layoutData->items)) {
                    $i = 0;
                    foreach ($layoutData->items as $key => $item) {
                        if(isset($item->id))
                        {
                            $urlEdit = Route::_('index.php?option=com_eqa&task=' . $layoutData->taskPrefixItem . '.edit&id=' . $item->id);
                            $urlSetDefault = Route::_('index.php?option=com_eqa&task=' . $layoutData->taskPrefixItem . '.setDefault&id=' . $item->id);
                        }
                        else
                        {
                            $urlEdit='';
                            $urlSetDefault='';
                        }
                        self::printItemsDefaultLayoutItem($layoutData, $i, $itemFields, $urlEdit, $urlSetDefault);
                        $i++;
                    }
                }
            }
            echo '</tbody>';
        }
        echo '</table>';
    }
    protected static function printPaginationFooter(Pagination $pagination, bool $showLimitBox=true):void
    {
        echo '<div class="row">';
        {
            echo '<div class="col-9">';
            echo '<div class="float-start">' . $pagination->getListFooter(). '</div>';
            echo '</div>';
        }
        if($showLimitBox)
        {
            echo '<div class="col-3">';
            echo '<div class="float-end">' . $pagination->getLimitBox() . '</div>';
            echo '</div>';
        }
        echo '</div>';
    }

    public static function printItemsDefaultLayout(EqaListLayoutData $layoutData, EqaListLayoutItemFields $itemFields):void {

        $url = 'index.php?option=com_eqa';
        if(is_array($layoutData->formActionParams))
        {
            foreach ($layoutData->formActionParams as $param => $value)
                $url .= '&' . $param . '=' . $value;
        }

        $url = Route::_($url, false);

        echo '<form action="'. $url .'" method="POST" name="adminForm" id="adminForm">';
        {
            echo '<input type="hidden" name="task" value=""/>';
            echo '<input type="hidden" name="boxchecked" value="0"/>';
            echo '<input type="hidden" name="filter_order" value="'.$layoutData->listOrderingField.'"/>';
            echo '<input type="hidden" name="filter_order_Dir" value="'.$layoutData->listOrderingDirection.'"/>';
            if(!empty($layoutData->formHiddenFields)){
                foreach ($layoutData->formHiddenFields as $name => $value){
                    echo '<input type="hidden" name="'.$name.'" value="'.$value.'"/>';
                }
            }

            if(!empty($layoutData->filterForm))
                echo LayoutHelper::render('joomla.searchtools.default', array('view'=>$layoutData));

            self::printTableOfItems($layoutData, $itemFields);

            if(isset($layoutData->pagination))
                self::printPaginationFooter($layoutData->pagination, $layoutData->showPaginationLimitBox);
            echo HTMLHelper::_('form.token');
            echo '</form>';
        }
    }
    public static function printItemsUploadForm(EqaItemsHtmlView $view, string $task='import'):void{
        $form = $view->uploadForm;
        $controllerName = StringHelper::convertPluralToSingle($view->getName());
        HTMLHelper::_('behavior.formvalidator');
        ?>
        <form name="adminForm" id="adminForm" method="POST" enctype="multipart/form-data" action="index.php?option=com_eqa">
            <input type="hidden" name="task" value="<?php echo $controllerName . '.' . $task;?>">
            <?php echo JHtml::_('form.token'); ?>
            <?php echo $form->renderFieldset('upload'); ?>
        </form>
        <div class="container">

        </div>
        <?php
    }
}