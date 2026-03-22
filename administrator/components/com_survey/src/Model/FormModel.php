<?php
namespace Kma\Component\Survey\Administrator\Model;

use Exception;
use Joomla\CMS\Factory;
use Joomla\Database\ParameterType;
use Kma\Component\Survey\Administrator\Base\AdminModel;

defined('_JEXEC') or die();

class FormModel extends AdminModel {
    public function canCreate(?string $specificAction = 'com.create.form'): bool
    {
        return parent::canCreate($specificAction);
    }

    public function getItem($pk = null)
    {
        $item = parent::getItem($pk);

        //Load topics ids for this form from the junction table
        if ($item && $item->id) {
            $db = $this->getDatabase();
            $query = $db->getQuery(true)
                ->select('topic_id')
                ->from($db->quoteName('#__survey_form_topic'))
                ->where($db->quoteName('form_id') . ' = ' . (int) $item->id);
            $db->setQuery($query);
            $item->topic_ids = $db->loadColumn(); // array of IDs
        } else {
            $item->topic_ids = [];
        }

        return $item;
    }
    public function save($data): bool
    {
        /*
         * A form cannot be edited when it is used in a survey.
         * So we'll try to find out whether there are any surveys using this form.
         */
        if(!empty($data['id']))
        {
            $db = $this->getDatabase();
            $query = $db->getQuery(true)
                ->select('COUNT(1)')
                ->from($db->quoteName('#__survey_surveys'))
                ->where($db->quoteName('form_id') . ' = :formId')
                ->bind(':formId', $data['id'], ParameterType::INTEGER)
                ->setLimit(1);
            $db->setQuery($query);
            if($db->loadResult())
            {
                $app = Factory::getApplication();
                $app->enqueueMessage('Không thể sửa form này vì nó đã được sử dụng.', 'error');
                return false;
            }
        }

        $result = parent::save($data);

        //Save topics mapping (to the junction table)
        if ($result && isset($data['topic_ids'])) {
            $formId = (int) $this->getState($this->getName() . '.id');
            $db     = $this->getDatabase();

            // Clear old mappings
            $db->setQuery(
                $db->getQuery(true)
                    ->delete('#__survey_form_topic')
                    ->where('form_id = ' . $formId)
            );
            $db->execute();

            // Insert new mappings
            if (!empty($data['topic_ids'])) {
                $query = $db->getQuery(true);
                foreach ($data['topic_ids'] as $topicId) {
                    $query->clear()
                        ->insert('#__survey_form_topic')
                        ->columns(['form_id', 'topic_id'])
                        ->values((int) $formId . ', ' . (int) $topicId);
                    $db->setQuery($query);
                    $db->execute();
                }
            }
        }

        return $result;
    }
    public function saveJson(int $itemId, string $jsonString):void
    {
        $db = $this->getDatabase();
        $db->setQuery("UPDATE #__survey_forms SET model='" . $db->escape($jsonString) . "' WHERE id=" . $itemId);
        if(!$db->execute())
            throw new Exception('Lỗi truy vấn CSDL');
    }
}
