<?php
namespace Kma\Library\Kma\Helper;
defined('_JEXEC') or die();

use Joomla\CMS\Language\Text;

abstract class StateHelper
{
    //States (stick to Joomla's state codes)
    const STATE_UNPUBLISHED = 0;
    const STATE_PUBLISHED = 1;
    const STATE_ARCHIVED = 2;
    const STATE_TRASHED = -2;
    public static function decodeState(int $status): string
    {
        return match ($status) {
            self::STATE_UNPUBLISHED => Text::_('JUNPUBLISHED'),
            self::STATE_PUBLISHED => Text::_('JPUBLISHED'),
            self::STATE_ARCHIVED => Text::_('JARCHIVED'),
            self::STATE_TRASHED => Text::_('JTRASHED'),
        };
    }

}