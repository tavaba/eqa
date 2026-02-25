<?php
namespace Kma\Library\Kma\Helper;
defined('_JEXEC') or die();

use http\Client;
use JForm;
use JHtml;
use Joomla\CMS\Form\Form;
use Joomla\CMS\HTML\HTMLHelper;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Layout\LayoutHelper;
use Joomla\CMS\Pagination\Pagination;
use Joomla\CMS\Router\Route;
use Kma\Library\Kma\View\ItemAction;
use Kma\Library\Kma\View\ListLayoutData;
use Kma\Library\Kma\View\ListLayoutItemFieldOption;
use Kma\Library\Kma\View\ListLayoutItemFields;

abstract class ViewHelper
{
	/**
	 * Phương thức này giúp hiển thị edit form một cách thống nhất cho các item
	 *
	 * @param   JForm     $form  Đối tượng chứa form.
	 * @param   int|null  $itemId
	 * @param   array     $tabs
	 * @param   array     $additionalHiddenFields
	 *
	 * @return void
	 * @throws \Exception
	 * @since 1.0
	 */
    public static function printItemEditForm(Form $form, int|null $itemId, array $tabs=[], array $additionalHiddenFields=[]):void
    {
        if(empty($tabs))
        {
            foreach ($form->getFieldsets() as $fieldset)
            {
                $fieldsetName = $fieldset->name;
                if(!empty($fieldset->label))
                    $title = Text::_($fieldset->label);
                else
                    $title = match ($fieldsetName) {
                        'basic' => Text::_('JGLOBAL_FIELDSET_BASIC'),
                        'options' => Text::_('JGLOBAL_FIELDSET_OPTIONS'),
                        'advanced' => Text::_('JGLOBAL_FIELDSET_ADVANCED'),
                        'metadata' => Text::_('JGLOBAL_FIELDSET_METADATA_OPTIONS'),
                        'permissions' => Text::_('JGLOBAL_ACTION_PERMISSIONS_LABEL'),
                        default => StringHelper::CapitalizeFirstLetter($fieldsetName)
                    };
                $tabs[] = [
                    'fieldset_name' => $fieldsetName,
                    'title'=>$title,
                ];
            }
        }

        $option = ComponentHelper::getName();
        $action = Route::_("index.php?option={$option}&layout=edit&id={$itemId}", false);
        HTMLHelper::_('behavior.formvalidator');
        ?>
        <form action="<?php echo $action;?>" method="POST" name="adminForm" id="adminForm" class="form-validate" >
            <input type="hidden" name="task" value=""/>
            <?php
            /*
             * Print additional hidden fields
             */
            if(!empty($additionalHiddenFields))
                foreach ($additionalHiddenFields as $fieldName => $fieldValue)
                    echo '<input type="hidden" name="'.$fieldName.'" value="'.$fieldValue.'" />';

            /*
             * Create a tabset and render each $fieldset in a tab
             * The fieldset name will be the tab title
             */
            echo HTMLHelper::_('uitab.startTabSet', 'myTab', ['active' => $tabs[0]['fieldset_name']]);
            foreach ($tabs as $tab)
            {
                echo HTMLHelper::_('uitab.addTab', 'myTab', $tab['fieldset_name'], $tab['title']);
                echo $form->renderFieldset($tab['fieldset_name']);
                echo HTMLHelper::_('uitab.endTab');
            }

            echo HTMLHelper::_('uitab.endTabSet');
            ?>
            <?php echo HTMLHelper::_('form.token');?>
        </form>
        <?php
    }

    protected static function printItemsTableFieldHead(ListLayoutItemFieldOption $field, string $listOderingField, string $listOrderingDirection):void
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
    protected static function printItemsTableFieldValue($item, ListLayoutItemFieldOption $field, $editUrl):void {
        //Print the opening tag
        $openingTag = '<td';
        if(!empty($field->cssClass))
            $openingTag .= ' class="'.$field->cssClass.'"';
        if(!empty($field->altField))
        {
            $alt = $field->altField;
            if(!empty($item->$alt))
                $title = htmlspecialchars($item->$alt, ENT_QUOTES, 'UTF-8');
            else
                $title ='';
            $openingTag .= ' title="' . $title . '"';
        }
        $openingTag .= '>';
        echo $openingTag;

        //Check whether to show the link or not inside the inner HTML
        if(empty($field->showLinkConditionField))
            $showLink=true;
        else
            $showLink = !empty($item->{$field->showLinkConditionField}) &&
                ($item->{$field->showLinkConditionField} == $field->showLinkConditionValue);

        //Print the inner HTML
        $property  = $field->name;
        $value = $item->$property;
        if(!empty($value) && !$field->printRaw)
            $value = htmlspecialchars($value);
        if($field->displayAsBooleanSign){
            if(is_null($value))
                $value='';
            elseif ($value)
                $value = '<span class="tbody-icon active" ><span class="icon-publish" aria-hidden="true"></span></span>';
            else
                $value = '<span class="tbody-icon"><span class="icon-unpublish" aria-hidden="true"></span></span>';
        }

        if($field->hasEditUrl && $showLink)
            echo '<a href="' . $editUrl . '">' . $value . '</a>';
        elseif(!empty($field->urlFormatString) && $showLink)
        {
            $url = sprintf($field->urlFormatString,$item->{$field->urlFormatStringField});
            $url = Route::_($url, false);
            echo '<a href="' . $url . '">' . $value . '</a>';
        }
        else
            echo $value;

        //Pring the closeing tag
        echo '</td>';
    }
    protected static function printItemsDefaultLayoutItem(ListLayoutData $layoutData, int $itemIndex, ListLayoutItemFields $itemFields, string|null $editUrl=null, string|null $setDefaultUrl=null):void {
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
                    echo '&nbsp;&nbsp;&nbsp;';
                $action = ItemAction::cast($action);
                echo $action->getHtml($item);
                $isFirstAction = false;
            }
            echo '</td>';
        }

        //end
        echo '</tr>';
    }
    public static function printTableOfItems(ListLayoutData $layoutData, ListLayoutItemFields $itemFields):void
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
                            $componentName = ComponentHelper::getName();
                            $urlEdit = Route::_("index.php?option={$componentName}&task={$layoutData->taskPrefixItem}.edit&id={$item->id}");
                            $urlSetDefault = Route::_("index.php?option={$componentName}&task={$layoutData->taskPrefixItem}.setDefault&id={$item->id}");
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
    public static function printPaginationFooter(Pagination $pagination, bool $showLimitBox=true):void
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

    public static function printItemsDefaultLayout(ListLayoutData $layoutData, ListLayoutItemFields $itemFields):void
    {
        $componentName = ComponentHelper::getName();
        $url = "index.php?option={$componentName}";
        if(is_array($layoutData->formActionParams))
        {
            foreach ($layoutData->formActionParams as $param => $value)
                $url .= '&' . $param . '=' . $value;
        }

        echo '<form action="'. Route::_($url).'" method="POST" name="adminForm" id="adminForm">';
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
            echo HTMLHelper::_('form.token');

            if(!empty($layoutData->filterForm))
                echo LayoutHelper::render('joomla.searchtools.default', array('view'=>$layoutData));

            self::printTableOfItems($layoutData, $itemFields);

            if(isset($layoutData->pagination))
                self::printPaginationFooter($layoutData->pagination, $layoutData->showPaginationLimitBox);

            echo '</form>';
        }
    }

	/**
	 * Hàm này dùng để in ra form trong các trường hợp khác nhau, kể cả khi không có toolbar (ở front end).
	 * Giới hạn là chỉ render một fieldset nào đó trong form.
	 *
	 * @param   JForm   $form
	 * @param   string  $fieldsetName  Tên của group fieldset cần hiển thị trên form.
	 * @param   array   $hiddenFields  Mảng chứa tên và giá trị của các hidden fields cần thêm vào form [$name => $value].
	 * @param   bool    $multiPart
	 * @param   string  $actionUrl
	 * @param   string  $name
	 * @param   string  $id
	 * @param   bool    $submit         Hiển thị nút 'Submit' ở cuối form hay không.
	 *
	 * @return void
	 * @throws \Exception
	 * @since 1.0.0     *
	 */
	public static function printForm(Form $form, string $fieldsetName, array $hiddenFields=[], bool $multiPart=false,
                                     string $actionUrl='', string $name='adminForm', string $id='adminForm', bool $submit=false):void
    {
        HTMLHelper::_('behavior.formvalidator');
        if(empty($actionUrl))
            $actionUrl = Route::_('index.php?option='.ComponentHelper::getName(),false);
        $enctype = $multiPart ? ' enctype="multipart/form-data" ' : ' ';        //spaces are important here!
        echo "<form action=\"{$actionUrl}\" method=\"post\" {$enctype} name=\"{$name}\" id=\"{$id}\" class=\"form-validate\">";
        {
            echo $form->renderFieldset($fieldsetName);
            echo HTMLHelper::_('form.token');
            foreach ($hiddenFields as $field=>$value)
                echo "<input type=\"hidden\" name=\"$field\" value=\"$value\"/>";
            if(!isset($hiddenFields['task']))                       //'task' field must always be present
                echo "<input type=\"hidden\" name=\"task\"/>";
            if($submit)
                echo "<input type=\"submit\" value=\"Submit\">";
        }
        echo "</form>";
    }

    /**
     * Hàm này dùng để in ra layout upload file. Ý nghĩa của nó là
     * đảm bảo form được thiết lập thuộc tính "enctype=multipart/form-data".
     * Nếu không có thuộc tính này thì việc upload file sẽ không thành công.
     *
     * @param Form $form
     * @param string $task
     * @param string $fieldsetName
     * @since 1.2.0
     */
    public static function printUploadForm(Form $form, string $task='', string $fieldsetName='upload'): void
    {
        HTMLHelper::_('behavior.formvalidator');
        $componentName = ComponentHelper::getName();
        $actionUrl = Route::_("index.php?option={$componentName}", false);
        self::printForm($form, $fieldsetName, ['task'=>$task], true, $actionUrl);
    }
}