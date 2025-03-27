<?php
namespace Kma\Component\Eqa\Administrator\Field;
defined('_JEXEC') or die();

use Collator;
use Joomla\CMS\Factory;
use Joomla\CMS\Form\Field\ListField;
use Joomla\CMS\HTML\HTMLHelper;
use Kma\Component\Eqa\Administrator\Helper\UnitHelper;

class ExamclassField extends ListField
{
    protected $type = 'examclass';

    /**
     * Tạo một list box (inputbox) để lựa chọn lớp học phần có trong một môn thi
     * mà cụ thể là chia theo Kỳ thi
     *
     * @return	array		An array of JHtml options.
     *
     * @since   1.0
     */
	protected function getOptions()
	{
		$db = $this->getDatabase();
		$examId = Factory::getApplication()->getInput()->getInt('exam_id');
		if(empty($examId))
			return parent::getOptions();

		//1. Đếm số thí sinh của từng lớp học phần thuộc môn thi
		$db->setQuery('SELECT class_id FROM #__eqa_exam_learner WHERE exam_id='.$examId);
		$elements = $db->loadColumn();
		$classIds = array_unique($elements);
		if(empty($classIds))
			return parent::getOptions();
		$count=[];
		foreach ($classIds as $classId)
			$count[$classId]=0;
		foreach ($elements as $e)
			$count[$e]++;

		//2. Lấy mã của các lớp học phần
		$classIdSet = '(' . implode(',', $classIds) . ')';
		$db->setQuery('SELECT id, code FROM #__eqa_classes WHERE id IN ' . $classIdSet);
		$classes = $db->loadAssocList('id','code');

		//3. Sinh kết quả
		$options = parent::getOptions();
		foreach($classes as $id=>$code)
		{
			$text = $code .  ' ~ [' . $count[$id] . ']';
			$options[] = HTMLHelper::_('select.option', $id, $text);
		}
		return $options;
	}

}
