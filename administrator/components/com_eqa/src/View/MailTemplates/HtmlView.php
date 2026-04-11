<?php
namespace Kma\Component\Eqa\Administrator\View\MailTemplates; //The namespace must end with the VIEW NAME.
defined('_JEXEC') or die();

use Kma\Component\Eqa\Administrator\Enum\MailContextType;
use Kma\Library\Kma\View\MailTemplatesHtmlView;

class HtmlView extends MailTemplatesHtmlView
{
	protected function getContextTypeLabel(int $contextType): ?string
	{
		$contextType = MailContextType::tryFrom($contextType);
		if($contextType === null)
			return null;
		return $contextType->getLabel();
	}

}
