<?php
namespace Kma\Component\Kmail\Administrator\View\Logs;
defined('_JEXEC') or die();


use Kma\Library\Kma\View\LogsHtmlView;

/**
 * View nhật ký hệ thống của com_kmail
 *
 * @since 1.0.0
 */
class HtmlView extends LogsHtmlView
{
	protected function init(): void
	{
		parent::init();

		//Load scripts and styles
		$this->wa->useScript('select2.script');
		$this->wa->useStyle('select2.style');
		$this->wa->useScript('com_kmail.script');
	}
}
