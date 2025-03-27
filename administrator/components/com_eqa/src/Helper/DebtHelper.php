<?php
namespace Kma\Component\Eqa\Administrator\Helper;
defined('_JEXEC') or die();

use Joomla\CMS\Factory;
use Joomla\CMS\Language\Text;
use Joomla\Database\DatabaseInterface;
use Matrix\Exception;

abstract class DebtHelper{
    public const DEBTOR_PENALTY_NONE = 0;
    public const DEBTOR_PENALTY_REVOKE_ONE_TURN = 1;
    public const DEBTOR_PENALTY_REVOKE_ALL_TURNS = 2;
	static public function getPenalty(int $code)
	{
		return match ($code){
			self::DEBTOR_PENALTY_NONE => 'Không bị phạt',
			self::DEBTOR_PENALTY_REVOKE_ONE_TURN => 'Mất 1 lượt thi',
			self::DEBTOR_PENALTY_REVOKE_ALL_TURNS => 'Cấm thi, học lại'
		};
	}
	static public function getPenalties()
	{
		$penalties = [];
		$penalties[self::DEBTOR_PENALTY_NONE] = self::getPenalty(self::DEBTOR_PENALTY_NONE);
		$penalties[self::DEBTOR_PENALTY_REVOKE_ONE_TURN] = self::getPenalty(self::DEBTOR_PENALTY_REVOKE_ONE_TURN);
		$penalties[self::DEBTOR_PENALTY_REVOKE_ALL_TURNS] = self::getPenalty(self::DEBTOR_PENALTY_REVOKE_ALL_TURNS);
		return $penalties;
	}
}

