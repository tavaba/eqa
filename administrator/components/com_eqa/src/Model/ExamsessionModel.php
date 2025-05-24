<?php
namespace Kma\Component\Eqa\Administrator\Model;
use Joomla\CMS\Factory;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Object\CMSObject;
use Kma\Component\Eqa\Administrator\Base\EqaAdminModel;
use Kma\Component\Eqa\Administrator\Helper\EmployeeHelper;
use Kma\Component\Eqa\Administrator\Helper\GeneralHelper;

defined('_JEXEC') or die();

class ExamsessionModel extends EqaAdminModel {
    public function getItem($pk = null): CMSObject|bool
    {
        // Get the item using the parent method
        $item = parent::getItem($pk);

        // Check if 'monitor_ids' exists and is not empty
        if (!empty($item->monitor_ids)) {
            // Convert the comma-separated string back into an array
            $item->monitor_ids = explode(',', $item->monitor_ids);
            $item->monitor_ids = array_map('intval',$item->monitor_ids);
        }

        // Check if 'examiner_ids' exists and is not empty
        if (!empty($item->examiner_ids)) {
            // Convert the comma-separated string back into an array
            $item->examiner_ids = explode(',', $item->examiner_ids);
            $item->examiner_ids = array_map('intval',$item->examiner_ids);
        }

        return $item;
    }
    public function save($data): bool
    {
        // Check if 'monitor_ids' is set, and it's an array (for multiple selection)
        if (isset($data['monitor_ids']) && is_array($data['monitor_ids'])) {
            // Convert the array of worker IDs into a comma-separated string
            $data['monitor_ids'] = implode(',', $data['monitor_ids']);
        }

        // Check if 'examiner_ids' is set, and it's an array (for multiple selection)
        if (isset($data['examiner_ids']) && is_array($data['examiner_ids'])) {
            // Convert the array of worker IDs into a comma-separated string
            $data['examiner_ids'] = implode(',', $data['examiner_ids']);
        }

        // Continue with the default save operation
        return parent::save($data);
    }
    public function getAddbatchForm($data = [], $loadData = true)
    {
        $formName = 'com_eqa.examsessions';
        $formSource = 'examsessions';
        $form = $this->loadForm($formName,$formSource,array('control'=>'jform','load_data'=>false));
        if(empty( $form))
            return false;
        return $form;
    }
    public function saveBatch($data):bool
    {
        $app = Factory::getApplication();
        $db = $this->getDatabase();
        $columns = $db->quoteName(array('examseason_id','start','name','flexible'));
        $examseasonId = (int)$data['examseason_id'];
        $query = $db->getQuery(true)
            ->insert('#__eqa_examsessions')
            ->columns($columns);
        $examsessions = $data['examsessions'];
        foreach ($examsessions as $item){
            $values=[
                $examseasonId,
                $db->quote($item['start']),
                $db->quote($item['name']),
                (int)$item['flexible']
            ];
            $query->values(implode(',',$values));
        }
        $db->transactionStart();
        try {
            //Insert
            $db->setQuery($query);
            if(!$db->execute())
                throw new \Exception('COM_EQA_MSG_UPDATE_DATABASE_FAILED');

            //Commit and return
            $db->transactionCommit();
            $msg = Text::sprintf('COM_EQA_MSG_N_ITEMS_INSERTED', sizeof($examsessions));
            $app->enqueueMessage($msg,'success');
        }
        catch (\Exception $e){
            $db->transactionRollback();
            $app->enqueueMessage($e->getMessage(),'error');
            return false;
        }
        return true;
    }
}
