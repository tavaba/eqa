<?php
defined('_JEXEC') or die();

/**
 * Sub-template: Lịch sử chiến dịch email cho môn thi
 *
 * File: tmpl/examexaminees/default_campaign_history.php
 *
 * Được kích hoạt bởi:  echo $this->loadTemplate('campaign_history');
 * Dữ liệu nguồn:       $this->campaignHistory (CampaignHistoryItem[])
 *                       gán bởi HtmlView::loadCampaignHistory() trong prepareDataForLayoutDefault()
 *
 * Toàn bộ HTML được delegate cho ViewHelper::printCampaignHistory()
 * để tái sử dụng ở các view khác (ExamseasonExams, ...).
 *
 * @package Kma\Component\Eqa\Administrator\View\ExamExaminees
 * @since   2.0.8
 */


use Kma\Library\Kma\Helper\ViewHelper;

/** @var \Kma\Component\Eqa\Administrator\View\ExamExaminees\HtmlView $this */

ViewHelper::printCampaignHistory(
	$this->campaignHistory,   // CampaignHistoryItem[] — gán bởi loadCampaignHistory()
	'com_eqa',                // option — để build URL log
	'mailcampaigns'           // logView — view hiển thị delivery log
);
