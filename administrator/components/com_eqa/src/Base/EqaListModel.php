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
     * 'UploadForm' có nghĩa là form để upload file dữ liệu.
     * Cần lưu ý quy tắc đặt tên cho upload form và cả tên file tương ứng
     * để đảm bảo phương thức này hoạt động thông suốt.
     *
     * @return false|Form
     * @throws Exception
     * @since 1.0
     */
    public function getUploadForm(string $name='', string $source=''): bool|Form
    {
        $modelName = $this->getName();
        if(empty($name))
            $name = 'com_eqa.upload_'.$modelName;
        if(empty($source))
            $source = 'upload_'.$modelName;
        $form = $this->loadForm($name,$source);
        if(empty( $form))
            return false;
        else return $form;
    }

}
