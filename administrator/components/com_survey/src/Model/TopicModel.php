<?php
namespace Kma\Component\Survey\Administrator\Model;

use Kma\Library\Kma\Model\AdminModel;

defined('_JEXEC') or die();

class TopicModel extends AdminModel {
    public function canCreate(?string $specificAction = 'com.create.topic'): bool
    {
        return parent::canCreate($specificAction);
    }
}
