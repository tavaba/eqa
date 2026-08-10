<?php

/**
 * @package     Kma.Component.Eqa
 * @subpackage  Administrator.Traits
 *
 * @copyright   (C) 2026 KMA. All rights reserved.
 * @license     GNU General Public License version 2 or later
 */

namespace Kma\Component\Eqa\Administrator\Traits;

defined('_JEXEC') or die;

use Joomla\CMS\Form\Form;
use Kma\Component\Eqa\Administrator\Service\CampusService;
use Kma\Library\Kma\Helper\ComponentHelper;

/**
 * Xử lý hiển thị field 'campus_id' trên edit form — bản NHẸ, chỉ lo phần form.
 *
 * Dùng cho các thực thể "gắn nhãn cơ sở" (đơn vị, lớp hành chính, nhóm) — vốn
 * KHÔNG chốt chặn quyền ghi theo cơ sở nên không kế thừa CampusAdminModel, do
 * đó không có sẵn applyCampusFieldVisibility() của CampusScopedItem.
 *
 * Hành vi giống hệt CampusScopedItem::applyCampusFieldVisibility(): field
 * campus_id LUÔN readonly và LUÔN mang giá trị cơ sở đang làm việc (bản ghi mới)
 * hoặc giữ nguyên cơ sở của bản ghi (khi sửa), cho MỌI người dùng.
 *
 * Cách dùng trong Item Model:
 *
 *     use CampusFormField;
 *
 *     public function getForm($data = [], $loadData = true)
 *     {
 *         return $this->applyCampusFieldVisibility(parent::getForm($data, $loadData));
 *     }
 *
 * @since 2.1.6
 */
trait CampusFormField
{
    /**
     * @var CampusService|null
     * @since 2.1.6
     */
    private ?CampusService $campusServiceForFormField = null;

    /**
     * @return  CampusService
     * @since   2.1.6
     */
    private function getCampusServiceForFormField(): CampusService
    {
        if ($this->campusServiceForFormField === null) {
            $this->campusServiceForFormField = ComponentHelper::getComponent()->getCampusService();
        }

        return $this->campusServiceForFormField;
    }

    /**
     * Đặt field campus_id sang readonly và gán cơ sở đang làm việc.
     *
     * @param   Form|bool  $form
     *
     * @return  Form|bool
     * @since   2.1.6
     */
    protected function applyCampusFieldVisibility($form)
    {
        if (!($form instanceof Form) || $form->getField('campus_id') === false) {
            return $form;
        }

        $activeCampusId = $this->getCampusServiceForFormField()->getActiveCampusId();

        // Bản ghi mới (chưa có giá trị): gán cơ sở đang làm việc.
        // Bản ghi đã tồn tại: giữ nguyên cơ sở của bản ghi.
        $currentValue = $form->getValue('campus_id');
        if (empty($currentValue) && $activeCampusId > 0) {
            $form->setValue('campus_id', null, $activeCampusId);
        }

        $form->setFieldAttribute('campus_id', 'readonly', 'true');
        $form->setFieldAttribute('campus_id', 'required', 'false');

        return $form;
    }
}
