<?php
namespace Kma\Library\Kma\Enum;
defined('_JEXEC') or die();

/**
 * Kết quả trả về của MailService::notify().
 *
 * Tên phản ánh đúng ngữ nghĩa: đây là kết quả của việc khởi tạo
 * một mail campaign từ phía caller.
 *
 * Caller (Controller của component) dùng giá trị này để quyết định
 * hành động tiếp theo — redirect, hiển thị message, v.v.
 * MailService không tự redirect hay hiển thị message.
 *
 * @since 1.0.3
 */
enum MailCampaignResult
{
    /**
     * Đúng 1 template phù hợp → campaign và queue đã được tạo thành công.
     * Caller redirect về return_url với message thành công.
     */
    case Queued;

    /**
     * Có nhiều hơn 1 template phù hợp → caller cần redirect sang
     * layout selecttemplate để người dùng chọn template.
     * context_type (đã có sẵn trong caller) đủ để query lại danh sách template.
     */
    case NeedSelectTemplate;

    /**
     * Không có template nào phù hợp với context_type.
     * Caller redirect về return_url với message lỗi.
     */
    case NoTemplate;
}
