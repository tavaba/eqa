<?php

use Joomla\CMS\HTML\HTMLHelper;
use Joomla\CMS\Router\Route;
use Kma\Component\Eqa\Administrator\DataObject\ExamroomInfo;
use Kma\Library\Kma\Helper\ViewHelper;

defined('_JEXEC') or die();
HTMLHelper::_('behavior.formvalidator');

/**
 * @var \Kma\Component\Eqa\Administrator\View\Examroom\HtmlView $this
 */
$examroom = $this->examroom;
$form = $this->form;
?>
<div>
    <?php echo $examroom->getHtml(); ?>
    <hr/>
</div>
<?php
$hiddenFields = [
        'examroom_id' => $examroom->id,
        'phase' => 'getdata',
];
ViewHelper::printForm($form, 'addexamroomexaminees', $hiddenFields);
