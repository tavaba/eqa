<?php
/**
 * @package     Kma\Component\Eqa\Administrator\DataObject
 * @subpackage
 *
 * @copyright   A copyright
 * @license     A "Slug" license name e.g. GPL2
 */

namespace Kma\Component\Eqa\Administrator\DataObject;

class CreditClassObject
{
	//Table fields
	public int|null $id;
	public string $code;
	public string $name;
	public int $size;
	public int $subject_id;
	public int|null $lecturer_id;
	public int $academicyear;
	public int $term;
	public int $created_by;
	public string $created_at;

	//Extra fields
	public string $coursegroup;
}