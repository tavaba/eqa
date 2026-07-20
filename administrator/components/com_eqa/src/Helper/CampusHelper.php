<?php

namespace Kma\Component\Eqa\Administrator\Helper;

defined('_JEXEC') or die();

use Joomla\CMS\HTML\HTMLHelper;
use Joomla\CMS\Router\Route;
use Joomla\CMS\Uri\Uri;
use Kma\Component\Eqa\Administrator\Service\CampusService;
use Kma\Library\Kma\Helper\ComponentHelper;

/**
 * Helper hiển thị các thành phần giao diện liên quan đến 'cơ sở đào tạo'.
 *
 * @since 2.1.6
 */
abstract class CampusHelper
{
    /**
     * Sinh HTML của khối chuyển 'cơ sở đào tạo đang làm việc'.
     *
     * Người dùng chỉ thuộc một cơ sở: hiển thị nhãn tĩnh.
     * Người dùng thuộc nhiều cơ sở: hiển thị dropdown, chọn xong tự submit.
     * Người dùng không thuộc cơ sở nào: hiển thị cảnh báo.
     *
     * Dùng Bootstrap sẵn có của Joomla, không phát sinh CSS riêng.
     *
     * @return  string  HTML; chuỗi rỗng nếu hệ thống chỉ có một cơ sở đào tạo.
     * @since   2.1.6
     */
    public static function renderSwitcher(): string
    {
        /** @var CampusService $campusService */
        $campusService = ComponentHelper::getComponent()->getCampusService();

        $allCampuses = $campusService->getAllCampuses();

        // Hệ thống chỉ có một cơ sở đào tạo → không cần hiển thị gì
        if (count($allCampuses) <= 1) {
            return '';
        }

        $allowedIds = $campusService->getUserCampusIds();

        if (empty($allowedIds)) {
            return '<div class="alert alert-warning py-2 mb-3">'
                . '<span class="icon-warning me-1" aria-hidden="true"></span>'
                . 'Tài khoản của bạn chưa được gán vào cơ sở đào tạo nào.'
                . ' Vui lòng liên hệ quản trị viên.'
                . '</div>';
        }

        $activeId   = $campusService->getActiveCampusId();
        $activeName = $campusService->getCampusName($activeId);

        // Chỉ thuộc một cơ sở → nhãn tĩnh
        if (count($allowedIds) === 1) {
            return '<div class="mb-3">'
                . '<span class="badge bg-info">'
                . '<span class="icon-map-marker me-1" aria-hidden="true"></span>'
                . htmlspecialchars($activeName, ENT_QUOTES, 'UTF-8')
                . '</span>'
                . '</div>';
        }

        // Thuộc nhiều cơ sở → dropdown tự submit
        $action = Route::_('index.php?option=com_eqa', false);
        $return = base64_encode(Uri::getInstance()->toString(['path', 'query']));

        $options = [];
        foreach ($allowedIds as $campusId) {
            $campus = $campusService->getCampus($campusId);
            if (!empty($campus)) {
                $options[] = HTMLHelper::_('select.option', $campusId, $campus->name);
            }
        }

        $select = HTMLHelper::_(
            'select.genericlist',
            $options,
            'campus_id',
            'class="form-select form-select-sm" onchange="this.form.submit();"',
            'value',
            'text',
            $activeId,
            'eqa-campus-switcher'
        );

        $html = [];
        $html[] = '<form action="' . $action . '" method="post" class="d-inline-block mb-3">';
        $html[] = '<div class="input-group input-group-sm">';
        $html[] = '<label class="input-group-text" for="eqa-campus-switcher">'
            . '<span class="icon-map-marker me-1" aria-hidden="true"></span>Cơ sở đào tạo'
            . '</label>';
        $html[] = $select;
        $html[] = '</div>';
        $html[] = '<input type="hidden" name="task" value="activecampus.set"/>';
        $html[] = '<input type="hidden" name="return" value="' . $return . '"/>';
        $html[] = HTMLHelper::_('form.token');
        $html[] = '</form>';

        return implode('', $html);
    }
}
