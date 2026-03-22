<?php
/**
 * @package     Kma\Library\Kma\Constant
 * @subpackage
 *
 * @copyright   A copyright
 * @license     A "Slug" license name e.g. GPL2
 */

namespace Kma\Library\Kma\Constant;

/**
 * Định nghĩa hằng số để biểu thị các 'action' (hành động của người dùng),
 * trước hết là phục vụ việc ghi log. Lớp cơ sở được định nghĩa trong thư
 * viện lib_kma sử dụng giải giá trị từ 0-1000. Các lớp con cần tránh sử
 * dụng khoảng giá trị này
 * @since       1.0.3
 */
class Action
{
	// CRUD cơ bản (dùng chung, định nghĩa trong thư viện)
	const int CREATE    = 1;
	const int EDIT      = 2;
	const int DELETE    = 3;
	const int PUBLISH   = 4;
	const int UNPUBLISH = 5;
	const int ARCHIVE   = 6;
	const int TRASH     = 7;

	//Một số action thông dụng khác (dùng chung, định nghĩa trong thư viện)
	const int VIEW      = 11;
	const int LIST      = 12;
	const int UPLOAD    = 21;
	const int DOWNLOAD  = 22;
	const int IMPORT    = 23;
	const int EXPORT    = 24;
}