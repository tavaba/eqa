<?php
namespace Kma\Library\Kma\View;
defined('_JEXEC') or die();

class ListLayoutItemFieldOption
{
    public string $name;        //Field's name = Item's property name

    /**
     * @var string The title of the column heading.
     * @since 1.0.0
     */
    public string $title;

    /**
     * @var string The description of the column heading. Used when the column heading is an abbreviation.
     * @since 1.0.0
     */
    public string $titleDesc;
    public string $cssClass;       //CSS class to format value cell

    /**
     * @var string A field of the current item that provides additional information about this field.
     * This will be displayed in a tooltip when hovering over the field value.
     * @since 1.0.0
     */
    public string $altField;

    public bool $sortable;      //List can be sorted by this field
    public bool $hasEditUrl;      //This field has a link to edit the item

    /**
     * @var string Format string for creating the URL to create a link under the field value.
     * @since 1.0.0
     */
    public string $urlFormatString;

    /**
     * @var string Name of the field whose value is passed as first parameter to sprintf()
     * to form the URL. Default is "id".
     * @since 1.0.0
     */
    public string $urlFormatStringField;

    /**
     * @var boolean If true, the field value should not be escaped before printing it.
     * @since 1.0.0
     */
    public bool $printRaw;

    /**
     * @var boolean A green checkmark (✔) or red cross mark (✘) is printed instead of the field value.
     * @since 1.0.0
     */
    public bool $displayAsBooleanSign;

    /**
     * @var string Field name which contains condition whether to show the link or not.
     * @since 1.0.0
     */
    public string $showLinkConditionField;

    /**
     * @var mixed Value of the field specified with $showLinkConditionField.
     * If the field value equals this one, then the link is shown.
     * @since 1.0.0
     */
    public mixed $showLinkConditionValue;
    public function __construct(string $name, string $title, bool $sort=false, bool $edit=false, string $class='')
    {
        $this->name = $name;
        $this->title = $title;
        $this->sortable = $sort;
        $this->hasEditUrl = $edit;
        $this->cssClass = $class;
        $this->urlFormatString = '';
        $this->urlFormatStringField = 'id';
        $this->titleDesc = '';
        $this->altField='';
        $this->printRaw = false;
        $this->displayAsBooleanSign = false;
        $this->showLinkConditionField = '';
        $this->showLinkConditionValue = true;
    }
}