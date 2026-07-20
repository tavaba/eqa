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
     * Cơ chế submit:
     *  - Nếu trang có 'adminForm' (các view danh sách): gắn campus_id vào chính
     *    adminForm rồi submit form đó. Nhờ vậy request giữ nguyên view hiện tại
     *    (action của adminForm đã chứa &view=...) và không bị đưa về view mặc định.
     *  - Nếu trang không có adminForm (ví dụ dashboard): dùng form dự phòng ẩn.
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
            'class="form-select form-select-sm" onchange="eqaSetActiveCampus(this.value);"',
            'value',
            'text',
            $activeId,
            'eqa-campus-switcher'
        );

        $fallbackAction = Route::_('index.php?option=com_eqa', false);
        $fallbackReturn = base64_encode(Uri::getInstance()->toString(['path', 'query']));

        $html = [];

        // Dropdown (KHÔNG bọc trong <form> để không nuốt mất context của view)
        $html[] = '<div class="d-inline-block mb-3">';
        $html[] = '<div class="input-group input-group-sm">';
        $html[] = '<label class="input-group-text" for="eqa-campus-switcher">'
            . '<span class="icon-map-marker me-1" aria-hidden="true"></span>Cơ sở đào tạo'
            . '</label>';
        $html[] = $select;
        $html[] = '</div>';
        $html[] = '</div>';

        // Form dự phòng, chỉ dùng cho trang không có adminForm (dashboard...)
        $html[] = '<form action="' . $fallbackAction . '" method="post" id="eqa-campus-form" class="d-none">';
        $html[] = '<input type="hidden" name="task" value="activecampus.set"/>';
        $html[] = '<input type="hidden" name="campus_id" value=""/>';
        $html[] = '<input type="hidden" name="return" value="' . $fallbackReturn . '"/>';
        $html[] = HTMLHelper::_('form.token');
        $html[] = '</form>';

        $html[] = <<<'JS'
<script>
function eqaSetActiveCampus(campusId) {
    var adminForm = document.getElementById('adminForm');

    if (adminForm) {
        // Gắn campus_id vào adminForm để request giữ nguyên view hiện tại
        var input = adminForm.querySelector('input[name="campus_id"]');

        if (!input) {
            input = document.createElement('input');
            input.type = 'hidden';
            input.name = 'campus_id';
            adminForm.appendChild(input);
        }

        input.value = campusId;

        if (window.Joomla && typeof Joomla.submitform === 'function') {
            Joomla.submitform('activecampus.set', adminForm);
        } else {
            adminForm.task.value = 'activecampus.set';
            adminForm.submit();
        }

        return;
    }

    // Trang không có adminForm (dashboard...) → dùng form dự phòng
    var fallbackForm = document.getElementById('eqa-campus-form');

    if (fallbackForm) {
        fallbackForm.querySelector('input[name="campus_id"]').value = campusId;
        fallbackForm.submit();
    }
}
</script>
JS;

        return implode('', $html);
    }
}
