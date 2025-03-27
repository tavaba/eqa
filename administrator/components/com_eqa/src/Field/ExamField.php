<?php
namespace Kma\Component\Eqa\Administrator\Field;
defined('_JEXEC') or die();

use Collator;
use Joomla\CMS\Form\Field\GroupedlistField;
use Joomla\CMS\Form\Field\ListField;
use Joomla\CMS\HTML\HTMLHelper;
use Kma\Component\Eqa\Administrator\Helper\CourseHelper;
use Kma\Component\Eqa\Administrator\Helper\UnitHelper;

class ExamField extends ListField
{
    protected $type = 'exam';

    /**
     * Tạo một list box (inputbox) để lựa chọn môn thi
     * Vì danh sách tương đối lớn nên môn thi sẽ được chia thành các groups
     * mà cụ thể là chia theo Kỳ thi
     *
     * @return	array		An array of JHtml options.
     *
     * @since   1.0
     */
    protected function getOptions()
    {
		$db = $this->getDatabase();

        //Lấy danh sách môn
	    $columns = $db->quoteName(
			array('a.id', 'a.name', 'a.examseason_id'),
		    array('id', 'name', 'examseason_id')
	    );
        $query = $db->getQuery(true)
            ->select($columns)
            ->from('#__eqa_exams AS a')
	        ->leftJoin('#__eqa_examseasons AS b', 'a.examseason_id=b.id')
            ->where('b.completed=0')
	        ->order('name ASC');
        $db->setQuery($query);
        $exams = $db->loadObjectList();

		$options = parent::getOptions();
		foreach ($exams as  $exam)
		{
			$options[] = HTMLHelper::_('select.option', $exam->id, $exam->name);
		}
		return $options;
    }

}
