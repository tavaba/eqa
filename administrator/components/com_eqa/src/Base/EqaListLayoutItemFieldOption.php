<?php

namespace Kma\Component\Eqa\Administrator\Base;
defined('_JEXEC') or die();

class EqaListLayoutItemFieldOption
{
    public string $name;        //Field's name = Item's property name
    public string $title;       //Column heading text
    public string $titleDesc; //Description (alter text, title) for the column heading
    public string $cssClass;       //CSS class to format value cell
    public string $altField;         //A field of the current item that is the hint (title) for this field
    public bool $sortable;      //List can be sorted by this field
    public bool $hasEditUrl;      //This field has a link to edit the item
    public string $urlFormatString;     //When $edit is False, this string is used by sprintf() with $item->id to create a URL
    public function __construct(string $name, string $title, bool $sort=false, bool $edit=false, string $class='')
    {
        $this->name = $name;
        $this->title = $title;
        $this->sortable = $sort;
        $this->hasEditUrl = $edit;
        $this->cssClass = $class;
        $this->urlFormatString = '';
        $this->titleDesc = '';
        $this->altField='';
    }
}