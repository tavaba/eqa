<?php

namespace Kma\Component\Survey\Administrator\Base;

use Exception;
use Kma\Component\Survey\Administrator\Enum\ObjectType;

class AdminModel extends \Kma\Library\Kma\Model\AdminModel
{

	/**
	 * Trả về VALUE của một CASE trong enum ObjectType
	 * Nếu subclass không override phương thức này thì logic sẽ như sau:
	 * - Xác định tên của object chính là tên của model (AdminModel)
	 * - Tìm một CASE trong enum ObjectType có tên tương ứng
	 * - Nếu tìm thấy thì trả về giá trị (int) của CASE đó.
	 * - Nếu không tìm thấy thì kiểm tra xem chế độ ghi log có bật không. Nếu bật
	 *   thì sẽ throw một Exception; nếu không thì trả về 0.
	 */
	protected function getLogObjectType(): int
	{
		$objectName = $this->getName();
		$objectType = ObjectType::tryFromName($objectName);
		if($objectType instanceof ObjectType)
			return $objectType->value;

		//Nếu không tìm thấy
		if($this->loggingEnabled && $this->logService)
			throw new Exception('Chế độ ghi log đang bật nhưng không tìm được 
			ObjectType cho '. $objectName);
		return 0;
	}
}