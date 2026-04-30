<?php
namespace Kma\Component\Kmail\Administrator\View\Templates;
defined('_JEXEC') or die();

use Exception;
use Joomla\CMS\Factory;
use Joomla\CMS\Router\Route;
use Kma\Library\Kma\Enum\MailContextType;
use Kma\Library\Kma\Helper\ComponentHelper;
use Kma\Library\Kma\Helper\ToolbarHelper;
use Kma\Library\Kma\Service\MailService;
use Kma\Library\Kma\View\ItemsHtmlView;
use Kma\Library\Kma\View\ListLayoutItemFieldOption;
use Kma\Library\Kma\View\ListLayoutItemFields;
class HtmlView extends ItemsHtmlView
{
	protected object $selectTemplateLayoutData;
	protected function configureItemFieldsForLayoutDefault(): void
	{
		$fields = new ListLayoutItemFields();

		$fields->sequence = ListLayoutItemFields::defaultFieldSequence();
		$fields->check    = ListLayoutItemFields::defaultFieldCheck();
		$fields->published = ListLayoutItemFields::defaultFieldPublished();

		$fields->customFieldset1 = [];

		$fields->customFieldset1[] = new ListLayoutItemFieldOption('title', 'Tên template', true, true, '');

		// Ngữ cảnh
		$fields->customFieldset1[] = new ListLayoutItemFieldOption('contextLabel', 'Ngữ cảnh', true, false, 'text-center');

		// Tiêu đề email
		$fields->customFieldset1[] = new ListLayoutItemFieldOption(
			'subject', 'Tiêu đề email', false, false, 'text-muted small'
		);

		// Người tạo
		$fields->customFieldset1[] = new ListLayoutItemFieldOption(
			'creator_name', 'Người tạo', false, false, 'text-center small'
		);

		$this->itemFields = $fields;
	}

	// =========================================================================
	// Chuẩn bị dữ liệu
	// =========================================================================

	/**
	 * @throws Exception
	 * @since  1.0.0
	 */
	protected function prepareDataForLayoutDefault(): void
	{
		// Kiểm tra quyền
		$user = Factory::getApplication()->getIdentity();
		if (!$user->authorise('core.manage', 'com_eqa')) {
			throw new Exception('Bạn không có quyền truy cập chức năng này.', 403);
		}

		parent::prepareDataForLayoutDefault();

		//Preprocess
		if(!empty($this->layoutData->items))
		{
			foreach ($this->layoutData->items as $item)
			{
				$item->contextLabel = MailContextType::tryFrom($item->context_type)?->getLabel();
			}
		}
	}

	/**
	 * Chuẩn bị dữ liệu cho layout selecttemplate.
	 *
	 * Đọc tham số từ request, query danh sách template phù hợp,
	 * gán vào $this->layoutData để template PHP sử dụng.
	 *
	 * Request params (GET):
	 *   - context_type (int)    : giá trị MailContextType enum
	 *   - context_id   (int)    : ID đối tượng ngữ cảnh
	 *   - notify_url   (string) : URL base64 của task notify tại component gốc
	 *   - return       (string) : URL base64 để redirect về sau khi hoàn tất
	 *
	 * @return void
	 * @throws Exception
	 * @since  1.0.0
	 */
	protected function prepareDataForLayoutSelecttemplate(): void
	{
		$user = Factory::getApplication()->getIdentity();
		if (!$user->authorise('core.manage', 'com_kmail')) {
			throw new Exception('Bạn không có quyền thực hiện chức năng này.', 403);
		}

		$input       = Factory::getApplication()->input;
		$contextType = $input->getInt('context_type');
		$contextId   = $input->getInt('context_id');
		$notifyUrlB64 = $input->getString('notify_url', '');
		$returnB64    = $input->getString('return', '');

		if ($contextType <= 0) {
			throw new Exception('Tham số context_type không hợp lệ.');
		}

		// Lấy nhãn context type từ enum
		$contextTypeEnum  = MailContextType::tryFrom($contextType);
		$contextTypeLabel = $contextTypeEnum?->getLabel() ?? ('Ngữ cảnh #' . $contextType);

		// Lấy danh sách template phù hợp
		/** @var MailService $mailService */
		$mailService = ComponentHelper::getMailService();
		$templates   = $mailService->getTemplatesByContextType($contextType);

		if (empty($templates)) {
			throw new Exception(
				'Không có template email nào phù hợp với ngữ cảnh "' . $contextTypeLabel . '". '
				. 'Vui lòng tạo template trước.'
			);
		}

		//Tính toán notifyUrl và cancelUrl
		// Decode notify_url làm form action — POST thẳng về component gốc.
		// Tránh vòng vòng qua NotifyController::create() và vấn đề checkToken() qua GET.
		// Token nằm trong POST body → checkToken('request') hoặc checkToken() đều hoạt động.
		$notifyUrl = $notifyUrlB64 !== '' ? base64_decode($notifyUrlB64) : '';
		if (empty($notifyUrl) || !str_starts_with($notifyUrl, 'index.php')) {
			// Fallback an toàn nếu notify_url không hợp lệ
			$notifyUrl = 'index.php?option=com_kmail';
		}
		// URL hủy bỏ — decode return URL nếu có, fallback về com_kmail
		$cancelUrl = Route::_('index.php?option=com_kmail&view=campaigns', false);
		if ($returnB64 !== '') {
			$decoded = base64_decode($returnB64);
			if ($decoded !== false && str_starts_with($decoded, 'index.php')) {
				$cancelUrl = Route::_($decoded, false);
			}
		}

		// Gán dữ liệu cho template PHP
		$layoutData = new \stdClass();
		$layoutData->templates        = $templates;
		$layoutData->contextType      = $contextType;
		$layoutData->contextId        = $contextId;
		$layoutData->contextTypeLabel = $contextTypeLabel;
		$layoutData->notifyUrl        = $notifyUrl;
		$layoutData->returnB64        = $returnB64;
		$layoutData->cancelUrl        = $cancelUrl;
		$this->selectTemplateLayoutData = $layoutData;
	}
	protected function addToolbarForLayoutSelecttemplate(): void
	{
		ToolbarHelper::title('Chọn mẫu email thông báo');
		ToolbarHelper::appendButton('envelope','Gửi thông báo','');
		ToolbarHelper::appendCancelLink($this->selectTemplateLayoutData->cancelUrl);
	}


}
