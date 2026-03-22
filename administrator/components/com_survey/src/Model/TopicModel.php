<?php
namespace Kma\Component\Survey\Administrator\Model;

use Kma\Component\Survey\Administrator\Base\AdminModel;

defined('_JEXEC') or die();

class TopicModel extends AdminModel {
    public function canCreate(?string $specificAction = 'com.create.topic'): bool
    {
        return parent::canCreate($specificAction);
    }
}
