<?php
namespace Kma\Component\Eqa\Administrator\Base;
defined('_JEXEC') or die();

use Exception;
use Joomla\CMS\Factory;
use Joomla\CMS\Form\Form;
use Joomla\CMS\Form\FormFactoryInterface;
use Joomla\CMS\Language\Text;
use Joomla\CMS\MVC\Factory\MVCFactoryInterface;
use Joomla\CMS\MVC\Model\AdminModel;
use Joomla\CMS\MVC\Model\ListModel;
use Joomla\CMS\Table\Table;
use Joomla\CMS\User\CurrentUserInterface;
use RuntimeException;

/**
 * Class này sẽ được thừa kế bởi các Items Model
 *
 * @since 1.0
 */
class EqaListModel extends ListModel
{
	/**
	 * Method to autopopulate the model state.
	 * @since 1.2.2
	 */
	protected function populateState($ordering = null, $direction = null)
	{
		$ordering = $this->getUserStateFromRequest($this->context . '.ordercol', 'filter_order', $ordering);
		$direction = $this->getUserStateFromRequest($this->context . '.orderdirn', 'filter_order_Dir', $direction);
		parent::populateState($ordering, $direction);
	}

    /**
     * 'UploadForm' có nghĩa là form để upload file dữ liệu.
     * Cần lưu ý quy tắc đặt tên cho upload form và cả tên file tương ứng
     * để đảm bảo phương thức này hoạt động thông suốt.
     * @param string $source Tên của file xml chứa cấu trúc form. Ví dụ: 'upload_items.xml'
     * @param string $name Tên của form. Ví dụ: 'com_eqa.upload_items'
     * @return Form
     * @throws Exception
     * @since 1.0
     */
    public function getUploadForm(string $source='', string $name=''): Form
    {
        $modelName = $this->getName();
        if(empty($name))
            $name = 'com_eqa.upload_'.$modelName;
        if(empty($source))
            $source = 'upload_'.$modelName;
       return $this->loadForm($name,$source);
   }

}
