<?php
namespace Kma\Component\Eqa\Administrator\Helper;
defined('_JEXEC') or die();

use Joomla\CMS\Factory;
use Joomla\CMS\Language\Text;
use Joomla\Database\DatabaseInterface;
use Matrix\Exception;

/*
 * !!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!
 * !!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!
 * !!!   LƯU Ý QUAN TRỌNG       !!!
 * !!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!
 *
 * Nếu bổ sung thêm "Hình thức khuyến khích" thì cần rà soát lại các thủ tục xử lý
 * điểm khuyến khích. Cụ thể là :
 *      - phương thức updateStimulations() ở model 'exam'
 *      - phương thức getExamInfo() ở DatabaseHelper
 *      - phương thức getExamExaminees() ở DatabaseHelper
 */

abstract class StimulationHelper
{
	public const TYPE_EXEMPT=1;     //Miễn thi, cộng điểm
	public const TYPE_ADD=2;        //Cộng điểm
	public const TYPE_TRANS=3;      //Quy đổi điểm
	static public function getStimulationType(int $bonusCode){
		return match ($bonusCode)
		{
			self::TYPE_EXEMPT  => Text::_('COM_EQA_CONST_STIMULATION_EXEMPT'),
			self::TYPE_ADD  => Text::_('COM_EQA_CONST_STIMULATION_ADD'),
			self::TYPE_TRANS  => Text::_('COM_EQA_CONST_STIMULATION_TRANS')
		};
	}
	static public function getStimulationTypes(){
		$types = array();
		$types[self::TYPE_EXEMPT]  = self::getStimulationType(self::TYPE_EXEMPT);
		$types[self::TYPE_ADD]  = self::getStimulationType(self::TYPE_ADD);
		$types[self::TYPE_TRANS]  = self::getStimulationType(self::TYPE_TRANS);
		return $types;
	}

}

