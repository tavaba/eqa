<?php
namespace Kma\Component\Survey\Administrator\Table;

use Joomla\Database\DatabaseDriver;
use Kma\Library\Kma\Table\Table;

defined('_JEXEC') or die();
class CampaignTable extends Table{
    public function __construct(DatabaseDriver $db){
        parent::__construct($db,'','');
        $this->setColumnAlias('published', 'state');
    }
}