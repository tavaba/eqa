<?php
namespace Kma\Library\Kma\View;
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
class ListLayoutItemFields
{
    //The first standard fields
    public ListLayoutItemFieldOption $sequence;
    public ListLayoutItemFieldOption $id;
    public ListLayoutItemFieldOption $check;

    //The first custom fields (of type 'ListViewFieldOptioon') that are listed in an array
    //in the order they should appear
    public array $customFieldset1;

    //The next standard fields
    public ListLayoutItemFieldOption $default;
    public ListLayoutItemFieldOption $completed;
    public ListLayoutItemFieldOption $published;
    public ListLayoutItemFieldOption $order;

    //The second custom fields (of type 'ListViewFieldOptioon') that are listed in an array
    //in the order they should appear
    public array $customFieldset2;

    //Actions on item
    //Referer to the 'EqaItemAction' class
    public array $actions;

    //Some methods to get default popular fields
    public static function defaultFieldSequence(): ListLayoutItemFieldOption{
        $field = new ListLayoutItemFieldOption('', '#');
        $field->cssClass = 'text-center';
        return $field;
    }
    public static function defaultFieldId(): ListLayoutItemFieldOption{
        $field = new ListLayoutItemFieldOption('id', 'ID',true,false);
        $field->cssClass = 'text-center';
        return $field;
    }
    public static function defaultFieldCheck(): ListLayoutItemFieldOption{
        $field = new ListLayoutItemFieldOption('', '');
        $field->cssClass = 'text-center';
        return $field;
    }
    public static function defaultFieldDefault(): ListLayoutItemFieldOption{
        $field = new ListLayoutItemFieldOption('default', 'JDEFAULT',true,false);
        $field->cssClass = 'text-center';
        return $field;
    }
    public static function defaultFieldPublished(): ListLayoutItemFieldOption{
        $field = new ListLayoutItemFieldOption('published', 'JSTATUS',true,false);
        $field->cssClass = 'text-center';
        return $field;
    }
    public static function defaultFieldDescription(): ListLayoutItemFieldOption{
        return new ListLayoutItemFieldOption('description', 'JGLOBAL_DESCRIPTION');
    }
    public static function defaultFieldEmail(): ListLayoutItemFieldOption{
        return new ListLayoutItemFieldOption('email', 'JGLOBAL_EMAIL');
    }
    public static function defaultFieldDelete(string $urlFormatString): ListLayoutItemFieldOption{
        $field = new ListLayoutItemFieldOption('', '');
        $field->urlFormatString = $urlFormatString;
        $field->cssClass = 'text-center';
        return $field;
    }

	//TODO: Remove the following methods
	public static function defaultFieldCode(): ListLayoutItemFieldOption{
		return new ListLayoutItemFieldOption('code', 'Mã',false,true, 'text-center');
	}
	public static function defaultFieldLastname(): ListLayoutItemFieldOption{
		return new ListLayoutItemFieldOption('lastname', 'Họ đệm');
	}
	public static function defaultFieldName(): ListLayoutItemFieldOption{
		return new ListLayoutItemFieldOption('name', 'Tên');
	}
	public static function defaultFieldFirstname(): ListLayoutItemFieldOption{
		return new ListLayoutItemFieldOption('firstname', 'Tên');
	}
	public static function defaultFieldMobile(): ListLayoutItemFieldOption{
		return new ListLayoutItemFieldOption('mobile', 'Di động');
	}
}