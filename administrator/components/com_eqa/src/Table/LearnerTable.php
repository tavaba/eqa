<?php
namespace Kma\Component\Eqa\Administrator\Table;
defined('_JEXEC') or die();
use Kma\Component\Eqa\Administrator\Base\EqaTable;
use Kma\Component\Eqa\Administrator\Helper\StringHelper;

class LearnerTable extends EqaTable{
    public function check()
    {
        //Parent check
        if(!parent::check())
            return false;

        //Child check
        //1. Mã HVSV chỉ có thể là chữ cái viết hoa và chữ số
        $codePattern = '/^[A-Z0-9]+$/';
        if(!preg_match($codePattern, $this->code))
            return false;

        //2. Họ và tên chỉ được chứa chữ cái (Unicode), dấu cách và dấu gạch ngang
        $fullName='';
        if(!empty($this->lastname))
            $fullName = $this->lastname;
        if(!empty($this->firstname))
            $fullName .= $this->firstname;
        if(!StringHelper::isUnicodeAlphaStringWithSpacesAndHyphens($fullName))
            return false;

        //All checks are passed
        return true;
    }
}