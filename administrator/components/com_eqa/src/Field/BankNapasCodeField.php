<?php
namespace Kma\Component\Eqa\Administrator\Field;
defined('_JEXEC') or die();

use Joomla\CMS\Form\Field\ListField;
use Joomla\CMS\HTML\HTMLHelper;

/**
 * Supports an HTML select list of education degrees
 * Reference: https://www.abdulwaheed.pk/en/blog/41-information-technology/44-joomla/335-how-to-create-custom-form-field-for-custom-component-joomla-4.html
 * @since  1.6
 */
class BankNapasCodeField extends ListField
{
    protected $type = 'banknapascode';
	protected array $napas_banks = [
		970499 => 'Ngân hàng Nông nghiệp và Phát triển Nông thôn Việt Nam (Agribank)',
		970489 => 'Ngân hàng TMCP Công thương Việt Nam (Vietinbank)',
		970406 => 'Ngân hàng TMCP Đông Á (DongABank)',
		161087 => 'Ngân hàng TMCP Sài Gòn Công thương (Saigonbank)',
		970488 => 'Ngân hàng TMCP Đầu tư và Phát triển Việt Nam (BIDV)',
		970468 => 'Ngân hàng TMCP Đông Nam Á (SeABank)',
		970408 => 'Ngân hàng TMCP Dầu khí Toàn cầu (GP.Bank)',
		970430 => 'Ngân hàng TMCP Xăng dầu Petrolimex (PG Bank)',
		970412 => 'Ngân hàng TMCP Đại chúng Việt Nam (PVcomBank)',
		970452 => 'Ngân hàng TMCP Kiên Long (Kienlongbank)',
		970454 => 'Ngân hàng TMCP BảnViệt (Vietcapital Bank)',
		970433 => 'NgânhàngViệtNam Thương Tín(VietBank)',
		970414 => 'Ngân hàng TMCP Đại Dương (OceanBank)',
		970403 => 'Ngân hàng TMCP Sài Gòn Thương Tín (Sacombank)',
		970459 => 'Ngân hàng TMCP An Bình (ABBank)',
		970421 => 'Ngân hàng Liên doanh Việt Nga',
		686868 => 'Ngân hàng TMCP Ngoại Thương Việt Nam (VCB)',
		970416 => 'Ngân hàng TMCP Á Châu (ACB)',
		452999 => 'Ngân hàng TMCP Xuất nhập khẩu Việt Nam (Eximbank)',
		970423 => 'Ngân hàng TMCP Tiên Phong (TPBank)',
		970443 => 'Ngân hàng TMCP Sài Gòn Hà Nội (SHB)',
		970437 => 'Ngân hàng TMCP Phát Triển Thành Phố Hồ Chí Minh (HDBank)',
		970422 => 'Ngân hàng TMCP Quân Đội (MBBank)',
		981957 => 'Ngân hàng TMCP Việt Nam Thịnh Vượng (VPBank)',
		180906 => 'Ngân hàng TMCP Quốc Tế Việt Nam(VIB)',
		166888 => 'Ngân hàng TMCP Việt Á',
		888899 => 'Ngân hàng TMCP Kỹ Thương Việt Nam (Techcombank)',
		970448 => 'Ngân hàng TMCP Phương Đông (OCB)',
		818188 => 'Ngân hàng TMCP Quốc Dân (NCB)',
		970442 => 'Nhân hàng TNHH MTV Hongleong Việt Nam (HLBVN)',
		970449 => 'Ngân hàng TMCP Bưu Điện Liên Việt (LienVietPostBank)',
		970409 => 'Ngân hàng TMCP Bắc Á (BacABank)',
		970438 => 'Ngân hàng TMCP Bảo Việt (BVB)',
		970424 => 'Ngân hàng TNHH MTV Shinhan Việt Nam (ShinhanVN)',
		970439 => 'Ngân hàng Liên doanh VID Public (VID Public)',
		157979 => 'Ngân hàng TMCP Sài Gòn (SCB)',
		970426 => 'Ngân hàng TMCP Hàng Hải Việt nam (MaritimeBank)',
		970428 => 'Ngân hàng TMCP Nam Á',
		970434 => 'Ngân hàng TNHH Indovina',
		970457 => 'Ngân hàng Woori Việt Nam',
		970455 => 'Ngân hàng IBK',
		970446 => 'Ngân hàng Hợp Tác Xã Việt Nam (Co-op Bank)',
		422589 => 'Ngân hàng TNHH MTV CIMB (CIMB)',
		970458 => 'Ngân hàng TNHH MTV United Overseas (UOB)'
	];

    /**
     * Method to get a list of options for a list input.
     *
     * @return	array		An array of JHtml options.
     *
     * @since   1.0
     */
    protected function getOptions()
    {
        $options = [];
	    $options[] = HTMLHelper::_('select.option', null, '- Ngân hàng -');
        foreach ($this->napas_banks as $code=>$name)
        {
            $options[] = HTMLHelper::_('select.option', $code, $name);
        }
        return $options;
    }

}
