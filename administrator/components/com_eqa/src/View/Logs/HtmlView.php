<?php
namespace Kma\Component\Eqa\Administrator\View\Logs;

defined('_JEXEC') or die();

/**
 * @package     Kma\Component\Eqa\Administrator\View\Logs
 * @since       2.0.6
 */


use Kma\Library\Kma\View\LogsHtmlView;

/**
 * View nhật ký hệ thống của com_eqa.
 *
 * @since 2.0.6
 */
class HtmlView extends LogsHtmlView
{
	protected function init(): void
	{
		parent::init();

		//Load scripts and styles
		$this->wa->useScript('select2.script');
		$this->wa->useStyle('select2.style');
		$this->wa->useScript('com_eqa.script');
		$this->wa->useStyle('com_eqa.style');

	}
}
