<?php

namespace Kma\Component\Eqa\Administrator\Controller;

defined('_JEXEC') or die();

use Exception;
use Joomla\CMS\Router\Route;
use Kma\Component\Eqa\Administrator\Enum\Action;
use Kma\Component\Eqa\Administrator\Enum\ObjectType;
use Kma\Component\Eqa\Administrator\Model\ResitModel;
use Kma\Library\Kma\Controller\AdminController;
use Kma\Library\Kma\DataObject\LogEntry;

/**
 * Controller cho danh sách các 'danh sách thi lần 2'.
 *
 * Ngoài các tác vụ chuẩn (delete, publish, unpublish...) còn có tác vụ
 * 'activate' để chọn danh sách đang kích hoạt của cơ sở đào tạo.
 *
 * @since 2.1.8
 */
class ResitsController extends AdminController
{
    /**
     * Tên view danh sách (khai báo tường minh để giữ đúng chữ hoa/thường).
     *
     * @var    string
     * @since  2.1.8
     */
    protected $view_list = 'Resits';

    /**
     * Kích hoạt danh sách được chọn.
     *
     * Mỗi cơ sở đào tạo chỉ có một danh sách đang kích hoạt: đó là danh sách mà
     * thí sinh đăng ký thi lại vào, đồng thời là nguồn dữ liệu để sinh môn thi
     * lần 2 và rà soát việc nộp phí. Nếu người dùng chọn nhiều dòng thì chỉ
     * dòng đầu tiên được xử lý.
     *
     * @return  void
     * @since   2.1.8
     */
    public function activate(): void
    {
        $listUrl = Route::_('index.php?option=com_eqa&view=Resits', false);
        $id      = null;

        try {
            $this->checkToken();

            if (!$this->app->getIdentity()->authorise('core.edit', $this->option)) {
                throw new Exception('Bạn không có quyền thực hiện chức năng này.');
            }

            $ids = array_values(array_filter((array) $this->input->post->get('cid', [], 'int')));

            if (empty($ids)) {
                throw new Exception('Không có danh sách nào được chọn.');
            }

            $id = (int) $ids[0];

            /** @var ResitModel $model */
            $model = $this->getModel();
            $name  = $model->activate($id);

            $this->setMessage(
                sprintf('Đã kích hoạt danh sách <b>%s</b>.', htmlspecialchars($name)),
                'success'
            );

            $this->writeLog(new LogEntry(
                action: Action::ACTIVATE_RESIT,
                objectType: ObjectType::Resit->value,
                isSuccess: true,
                objectId: $id,
                objectTitle: $name,
            ));
        } catch (Exception $e) {
            $this->writeLog(new LogEntry(
                action: Action::ACTIVATE_RESIT,
                objectType: ObjectType::Resit->value,
                isSuccess: false,
                objectId: $id,
                errorMessage: $e->getMessage(),
            ));
            $this->setMessage($e->getMessage(), 'error');
        }

        $this->setRedirect($listUrl);
    }
}
