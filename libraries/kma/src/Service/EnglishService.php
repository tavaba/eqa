<?php
/**
 * @package     Kma\Library\Kma\Service
 * @subpackage
 *
 * @copyright   A copyright
 * @license     A "Slug" license name e.g. GPL2
 */

namespace Kma\Library\Kma\Service;

use Kma\Library\Kma\Helper\EnglishHelper;

class EnglishService
{
	protected array $manualSingularToPluralMap = [];
	protected array $manualPluralToSingularMap = [];

	/**
	 * @param   array  $manualSingularToPluralMap  An associative array where $key is a
	 *                                          single-form noun and the corresponding $value
	 *                                          is the the same nound in plural form.
	 */
	public function __construct(array $manualSingularToPluralMap=[])
	{
		if (!empty($manualSingularToPluralMap))
		{
			$this->manualSingularToPluralMap = $manualSingularToPluralMap;
			$this->manualPluralToSingularMap = array_flip($manualSingularToPluralMap);
		}
	}

	public function singularToPlural(string $singular):string
	{
		if (array_key_exists($singular, $this->manualSingularToPluralMap))
			return $this->manualSingularToPluralMap[$singular];
		return EnglishHelper::singularToPlural($singular);
	}
	public function pluralToSingular(string $plural):string
	{
		if (array_key_exists($plural, $this->manualPluralToSingularMap))
			return $this->manualPluralToSingularMap[$plural];
		return EnglishHelper::pluralToSingular($plural);
	}
}