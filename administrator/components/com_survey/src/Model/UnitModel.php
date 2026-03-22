<?php
namespace Kma\Component\Survey\Administrator\Model;

use Kma\Component\Survey\Administrator\Base\AdminModel;

defined('_JEXEC') or die();

class UnitModel extends AdminModel {
    public function canCreate(?string $specificAction = 'com.create.unit'): bool
    {
        return parent::canCreate($specificAction);
    }
}
