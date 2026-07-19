<?php

namespace Kma\Component\Eqa\Administrator\Table;

defined('_JEXEC') or die();

use Joomla\Database\DatabaseDriver;
use Kma\Library\Kma\Table\Table;

/**
 * Table class của 'cơ sở đào tạo' (campus).
 *
 * Hai điểm khác biệt so với các table thông thường của component:
 *
 * 1. Tên bảng được truyền tường minh cho constructor. Class cha suy ra tên bảng
 *    bằng cách chuyển tên thực thể sang dạng số nhiều ('campus' → ?), vốn không
 *    đáng tin với danh từ tận cùng bằng '-us'.
 *
 * 2. Cột `params` lưu JSON (Joomla Registry) chứa các tham số cấu hình riêng của
 *    cơ sở. Table tự encode khi ghi và decode khi đọc để Model/Form làm việc với
 *    mảng PHP thay vì chuỗi JSON.
 *
 * @since 2.1.6
 */
class CampusTable extends Table
{
    /**
     * @param   DatabaseDriver  $db  Database driver
     *
     * @since 2.1.6
     */
    public function __construct(DatabaseDriver $db)
    {
        parent::__construct($db, '#__eqa_campuses', 'id');
    }

    /**
     * Kiểm tra tính hợp lệ của dữ liệu trước khi ghi xuống CSDL.
     *
     * @return  bool
     * @since   2.1.6
     */
    public function check(): bool
    {
        $this->code = strtoupper(trim((string) $this->code));
        $this->name = trim((string) $this->name);

        if ($this->code === '') {
            $this->setError('Chưa nhập ký hiệu cơ sở đào tạo.');
            return false;
        }

        if (!preg_match('/^[A-Z0-9]{2,10}$/', $this->code)) {
            $this->setError('Ký hiệu cơ sở đào tạo chỉ gồm 2-10 ký tự chữ hoa hoặc chữ số.');
            return false;
        }

        if ($this->name === '') {
            $this->setError('Chưa nhập tên cơ sở đào tạo.');
            return false;
        }

        return parent::check();
    }

    /**
     * Ghi bản ghi xuống CSDL; encode cột `params` sang JSON nếu đang là mảng.
     *
     * @param   bool  $updateNulls
     *
     * @return  bool
     * @since   2.1.6
     */
    public function store($updateNulls = true): bool
    {
        $decodedParams = $this->params;

        if (is_array($this->params) || is_object($this->params)) {
            $this->params = json_encode($this->params, JSON_UNESCAPED_UNICODE);
        }

        $result = parent::store($updateNulls);

        // Trả lại dạng mảng để code gọi sau store() vẫn làm việc nhất quán
        $this->params = $decodedParams;

        return $result;
    }

    /**
     * Đọc bản ghi từ CSDL; decode cột `params` từ JSON sang mảng.
     *
     * @param   mixed  $keys
     * @param   bool   $reset
     *
     * @return  bool
     * @since   2.1.6
     */
    public function load($keys = null, $reset = true): bool
    {
        $result = parent::load($keys, $reset);

        if ($result && is_string($this->params)) {
            $this->params = json_decode($this->params, true) ?: [];
        }

        return $result;
    }
}
