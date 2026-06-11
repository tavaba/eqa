<?php

namespace Kma\Component\Eqa\Site\Dispatcher;

defined('_JEXEC') or die;

use Joomla\CMS\Dispatcher\ComponentDispatcher;

/**
 * Custom Dispatcher cho front-end com_eqa.
 *
 * Mục đích: chuẩn hóa tên view từ URL (toàn chữ thường) về đúng PascalCase
 * trước khi MVCFactory tìm class View tương ứng.
 *
 * Lý do cần thiết: Joomla 5's MVCFactory chỉ gọi ucfirst() — tức là chỉ viết
 * hoa chữ cái đầu tiên — nên "assessmentportal" được resolve thành
 * "Assessmentportal" thay vì "AssessmentPortal".
 *
 * @since 2.1.0
 */
class Dispatcher extends ComponentDispatcher
{
	/**
	 * Bảng ánh xạ: tên view viết thường => đúng PascalCase.
	 * Chỉ cần khai báo những view có tên gồm nhiều từ ghép (multi-word).
	 * Các view đơn từ như "learnerexams" không cần vì ucfirst() đã đủ.
	 *
	 * @var array<string, string>
	 */
	private const VIEW_MAP = [
		'assessmentportal' => 'AssessmentPortal',
		// Thêm vào đây nếu sau này có view multi-word khác:
		// 'myotherview' => 'MyOtherView',
	];

	/**
	 * Dispatch request, sau khi đã normalize tên view.
	 *
	 * @return void
	 * @since 2.1.0
	 */
	public function dispatch(): void
	{
		$this->normalizeViewName();
		parent::dispatch();
	}

	/**
	 * Chuyển tên view từ URL về đúng PascalCase theo VIEW_MAP.
	 *
	 * @return void
	 * @since 2.1.0
	 */
	private function normalizeViewName(): void
	{
		$input    = $this->app->input;
		$viewName = strtolower((string) $input->get('view', '', 'cmd'));

		if (isset(self::VIEW_MAP[$viewName])) {
			$input->set('view', self::VIEW_MAP[$viewName]);
		}
	}
}