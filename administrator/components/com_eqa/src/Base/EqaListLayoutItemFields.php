<?php
namespace Kma\Component\Eqa\Administrator\Base;
defined('_JEXEC') or die();

/**
 * Định nghĩa một danh sách các field có thể có trong một Item, bao gồm những field thường gặp
 * và những field bất kỳ khác. Instance của lớp này được thiết lập bởi View để mô tả các field có trong
 * mỗi item mà nó quản lý; thông tin được sử dụng bởi ViewHelper để hiển thị các Items.
 * ViewHelper sẽ kiểm tra từng property của class, nếu một property không empty thì ViewHelper biết rằng
 * item có field tương ứng và căn cứ vào giá trị của property để xuất item field.
 *
 * @since 1.0.2
 */
class EqaListLayoutItemFields
{
    //The first standard fields
    public EqaListLayoutItemFieldOption $sequence;
    public EqaListLayoutItemFieldOption $id;
    public EqaListLayoutItemFieldOption $check;

    //The first custom fields (of type 'EqaListViewFieldOptioon') that are listed in an array
    //in the order they should appear
    public array $customFieldset1;

    //The next standard fields
    public EqaListLayoutItemFieldOption $default;
    public EqaListLayoutItemFieldOption $completed;
    public EqaListLayoutItemFieldOption $published;
    public EqaListLayoutItemFieldOption $order;

    //The second custom fields (of type 'EqaListViewFieldOptioon') that are listed in an array
    //in the order they should appear
    public array $customFieldset2;

	//Actions on item
	//Referer to the 'EqaItemAction' class
	public array $actions;

    //Some methods to get default popular fields
    static public function defaultFieldSequence(): EqaListLayoutItemFieldOption{
        $field = new EqaListLayoutItemFieldOption('', 'COM_EQA_GENERAL_SEQUENCE_NUMBER');
        $field->cssClass = 'text-center';
        return $field;
    }
    static public function defaultFieldId(): EqaListLayoutItemFieldOption{
        $field = new EqaListLayoutItemFieldOption('id', 'COM_EQA_GENERAL_ID',true,false);
        $field->cssClass = 'text-center';
        return $field;
    }
    static public function defaultFieldCheck(): EqaListLayoutItemFieldOption{
        $field = new EqaListLayoutItemFieldOption('', '');
        $field->cssClass = 'text-center';
        return $field;
    }
    static public function defaultFieldCode(): EqaListLayoutItemFieldOption{
        $field = new EqaListLayoutItemFieldOption('code', 'COM_EQA_GENERAL_CODE',true,true);
        $field->cssClass = 'text-center';
        return $field;
    }
    static public function defaultFieldDefault(): EqaListLayoutItemFieldOption{
        $field = new EqaListLayoutItemFieldOption('default', 'COM_EQA_GENERAL_DEFAULT',false,false);
        $field->cssClass = 'text-center';
        return $field;
    }
    static public function defaultFieldPublished(): EqaListLayoutItemFieldOption{
        $field = new EqaListLayoutItemFieldOption('published', 'JSTATUS',true,false);
        $field->cssClass = 'text-center';
        return $field;
    }
    static public function defaultFieldOrder(): EqaListLayoutItemFieldOption{
        $field = new EqaListLayoutItemFieldOption('ordering', 'COM_EQA_GENERAL_ORDERING',true,false);
        $field->cssClass = 'text-center';
        return $field;
    }
    static public function defaultFieldAction(): EqaListLayoutItemFieldOption{
        $field = new EqaListLayoutItemFieldOption('', 'COM_EQA_GENERAL_ACTION');
        $field->cssClass = 'text-center';
        return $field;
    }
    static public function defaultFieldName(): EqaListLayoutItemFieldOption{
        return new EqaListLayoutItemFieldOption('name', 'COM_EQA_GENERAL_NAME');
    }
    static public function defaultFieldDescription(): EqaListLayoutItemFieldOption{
        return new EqaListLayoutItemFieldOption('description', 'COM_EQA_GENERAL_DESC');
    }
    static public function defaultFieldLastname(): EqaListLayoutItemFieldOption{
        return new EqaListLayoutItemFieldOption('lastname', 'COM_EQA_LASTNAME');
    }
    static public function defaultFieldFirstname(): EqaListLayoutItemFieldOption{
        return new EqaListLayoutItemFieldOption('firstname', 'COM_EQA_FIRSTNAME', true,false);
    }
    static public function defaultFieldEmail(): EqaListLayoutItemFieldOption{
        return new EqaListLayoutItemFieldOption('email', 'COM_EQA_EMAIL');
    }
    static public function defaultFieldMobile(): EqaListLayoutItemFieldOption{
        $field = new EqaListLayoutItemFieldOption('mobile', 'COM_EQA_MOBILE');
        $field->cssClass = 'text-center';
        return $field;
    }
    static public function defaultFieldDelete(string $urlFormatString): EqaListLayoutItemFieldOption{
        $field = new EqaListLayoutItemFieldOption('', '');
        $field->urlFormatString = $urlFormatString;
        $field->cssClass = 'text-center';
        return $field;
    }


}