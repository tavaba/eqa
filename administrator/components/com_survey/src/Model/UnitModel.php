<?php
namespace Kma\Component\Survey\Administrator\Model;

use Kma\Library\Kma\Model\AdminModel;

defined('_JEXEC') or die();

class UnitModel extends AdminModel {
    public function canCreate(?string $specificAction = 'com.create.unit'): bool
    {
        return parent::canCreate($specificAction);
    }
}
