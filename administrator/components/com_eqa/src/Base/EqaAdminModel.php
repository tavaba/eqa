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
use Kma\Component\Eqa\Administrator\Helper\GeneralHelper;
use RuntimeException;

/**
 * Class này sẽ được thừa kế bởi các Item Model
 *
 * @since 1.0
 */
class EqaAdminModel extends AdminModel
{
    protected array $canDo;
    public function __construct($config = [], ?MVCFactoryInterface $factory = null, ?FormFactoryInterface $formFactory = null)
    {
        parent::__construct($config, $factory, $formFactory);
        $this->canDo = GeneralHelper::getActions();
    }
    static public function cast($obj):self
    {
        return $obj;
    }


    /**
     * 'loadFormData' là hàm đã có trong lớp AdminModel nhưng nó rỗng
     * Vì thế, việc định nghĩa lại ở đây sẽ giúp tránh phải định nghĩa lại ở
     * các item model được sử dụng trong component này.
     * @return array|bool|\Joomla\CMS\Object\CMSObject
     * @since 1.0
     */
    public function loadFormData()
    {
        $context = "$this->option.edit.$this->context";
        $stateKey = $this->getName() . '.id';
        $pk    = (int) $this->getState($stateKey);

        return $this->getItem();
    }

    /**
     * Rewrite phương thức 'getForm' của  lớp cha để tự động xác định tên form
     * Cụ thể, tên form được xác định thông qua tên model. Do vậy, cần lưu ý quy tắc
     * đặt tên form và form file để phương thức này không bị lỗi.
     *
     * @param $data
     * @param $loadData
     * @return false|Form
     * @throws Exception
     * @since 1.0
     */
    public function getForm($data = [], $loadData = true)
    {
        $modelName = $this->getName();
        $formName = 'com_eqa.'.$modelName;
        $formSource = $modelName;
        $form = $this->loadForm($formName,$formSource,array('control'=>'jform','load_data'=>$loadData));
        if(empty( $form))
            return false;
        else return $form;
    }

    /**
     * Load an empty custom form that is needed in some case.
     * @param string $name   A unique name for the form
     * @param string $source The XML file's name without extension
     * @return Form
     * @throws Exception
     * @since 1.0.3
     */
    public function getCustomForm(string $name, string $source, array $options=['control'=>'jform']): Form
    {
        return $this->loadForm($name, $source, $options);
    }

    /**
     * Set or Unset the 'default' status of an item.
     *
     * @param int $id
     * @return bool
     * @throws Exception
     * @since  1.0
     */
    public function setDefault(int $id): bool
    {
        //Authorization
        if(!$this->canDo['core.edit']){
            $msg = Text::_('COM_EQA_MSG_UNAUTHORISED');
            throw new Exception($msg);
        }

        $db = $this->getDatabase();
        $table = $this->getTable();
        $table->load($id);
        $tableName = $table->getTableName();
        $currentDefaultStatus = $table->default;

        //Nếu set một phần tử làm 'default' thì unset cả bảng
        if($currentDefaultStatus == false){
            $query = $db->getQuery(true);
            $query->update($db->quoteName($tableName))
                ->set('`default` = 0');
            $db->setQuery($query);
            if(!$db->execute())
                return false;
        }

        //Trong mọi trường hợp, set giá trị cho phần tử được chỉ định
        $table->default = !$currentDefaultStatus;
        return $table->store();
    }

    public function getDefaultItem()
    {
        $db = $this->getDatabase();
        $table = $this->getTable();
        $tableName = $table->getTableName();

        //Kiểm tra xem có phần tử nào được set 'default' hay không
        $query = $db->getQuery(true)
            ->select('*')
            ->from($db->quoteName($tableName))
            ->where($db->quoteName('default').'>0');
        $db->setQuery($query);
        $item = $db->loadObject();
        if(!empty($item))
            return $item;

        //Nếu không có thì lấy item mới tạo gần nhất (theo id)
        $query = $db->getQuery(true)
            ->select('*')                                   // Select all columns
            ->from($db->quoteName($tableName))
            ->order($db->quoteName('id') . ' DESC')     // Order by id in descending order
            ->setLimit(1);
        $db->setQuery($query);
        return $db->loadObject();
    }


    /**
     * Hàm này gán giá trị trường 'ordering' của mỗi phần tử trong bảng
     * về giá trị của trường 'id' tương ứng.
     * @return bool
     *
     * @since 1.0
     */
    public function resetOrdering():bool{
        try {
            // Get the database object
            $dbo = Factory::getContainer()->get('DatabaseDriver');

            // Build the query
            $tableName = $this->getTable()->getTableName();
            $query = $dbo->getQuery(true)
                ->update($dbo->quoteName($tableName))
                ->set('ordering=id');

            // Set the query and execute
            $dbo->setQuery($query);

            $result = $dbo->execute();
        } catch (RuntimeException $e) {
            return false;
        }

        return $result;
    }
}
