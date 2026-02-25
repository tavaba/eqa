<?php
defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Kma\Library\Kma\Helper\ViewHelper;

/*
 * If this survey is a member of a campaign, then we need to
 * inject the campaign id into the form.
 */
$hiddenFields=[];
$campaignId = Factory::getApplication()->input->getInt('campaign_id');
if($campaignId)
    $hiddenFields['campaign_id'] = $campaignId;

ViewHelper::printItemEditForm($this->form, $this->item->id,[], $hiddenFields);
