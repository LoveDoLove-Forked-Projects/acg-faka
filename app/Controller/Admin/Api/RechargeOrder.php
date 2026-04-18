<?php
declare(strict_types=1);

namespace App\Controller\Admin\Api;

use App\Controller\Base\API\Manage;
use App\Entity\Query\Delete;
use App\Entity\Query\Get;
use App\Interceptor\ManageSession;
use App\Model\ManageLog;
use App\Model\UserRecharge;
use App\Service\Query;
use App\Service\Recharge;
use App\Util\Date;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\Relation;
use Kernel\Annotation\Inject;
use Kernel\Annotation\Interceptor;
use Kernel\Exception\JSONException;

#[Interceptor(ManageSession::class, Interceptor::TYPE_API)]
class RechargeOrder extends Manage
{
    #[Inject]
    private Query $query;

    #[Inject]
    private Recharge $recharge;

    /**
     * @return array
     */
    public function data(): array
    {
        $map = $_POST;
        $get = new Get(UserRecharge::class);
        $get->setPaginate((int)$this->request->post("page"), (int)$this->request->post("limit"));
        $get->setWhere($map);
        $raw = [];

        $data = $this->query->get($get, function (Builder $builder) use (&$raw) {
            $raw['order_amount'] = (clone $builder)->sum("amount");

            return $builder->with([
                'user' => function (Relation $relation) {
                    $relation->select(["id", "username", "avatar"]);
                },
                'pay' => function (Relation $relation) {
                    $relation->select(["id", "name", "icon"]);
                }
            ]);
        });

        return $this->json(data: array_merge($raw, $data));
    }


    /**
     * @return array
     * @throws JSONException
     */
    public function success(): array
    {
        $id = (int)$_POST['id'];
        $order = UserRecharge::query()->find($id);
        if (!$order) {
            throw new JSONException("订单不存在");
        }

        if ($order->status != 0) {
            throw new JSONException("该订单已支付，无法再进行操作了。");
        }

        $this->recharge->orderSuccess($order);

        ManageLog::log($this->getManage(), "充值订单->手动补单，订单号：{$order->trade_no}");
        return $this->json(200, "已手动确认");
    }


    /**
     * @return array
     */
    public function clear(): array
    {
        UserRecharge::query()
            ->where("create_time", "<", date("Y-m-d H:i:s", time() - 1800))
            ->where("status", 0)->delete();

        ManageLog::log($this->getManage(), "充值订单->一键清理无用订单");
        return $this->json(200, '（＾∀＾）清理完成');
    }


    /**
     * @return void
     */
    public function export(): void
    {
        ignore_user_abort(true);
        set_time_limit(0);

        $map = $_GET;
        $exportStatus = (int)($map['export_status'] ?? 0);
        $exportNum = (int)($map['export_num'] ?? 0);

        unset($map['export_status'], $map['export_num']);

        $get = new Get(UserRecharge::class);
        $get->setWhere($map);

        if ($exportNum > 0) {
            $get->setPaginate(1, $exportNum);
        }

        $data = $this->query->get($get, function (Builder $builder) {
            return $builder->with([
                'user' => function (Relation $relation) {
                    $relation->select(["id", "username"]);
                },
                'pay' => function (Relation $relation) {
                    $relation->select(["id", "name"]);
                }
            ]);
        });

        $list = $data['list'] ?? [];
        $ids = [];

        header('Content-Type: text/csv; charset=UTF-8');
        header('Content-Disposition: attachment; filename="充值订单导出-' . Date::current("YmdHis") . '.csv"');
        header('Pragma: no-cache');
        header('Expires: 0');

        $fp = fopen('php://output', 'w');

        fwrite($fp, "\xEF\xBB\xBF");

        fputcsv($fp, [
            '订单号',
            '金额',
            '会员',
            '支付方式',
            '下单时间',
            '下单IP',
            '支付时间',
            '支付状态',
        ]);

        foreach ($list as $d) {
            $ids[] = $d['id'];


            $statusText = match ((int)($d['status'] ?? 0)) {
                0 => '未支付',
                1 => '已支付',
                default => '未知',
            };


            fputcsv($fp, [
                (string)($d['trade_no'] ?? ''),
                (string)($d['amount'] ?? 0),
                (string)($d['user']['username'] ?? ''),
                (string)($d['pay']['name'] ?? ''),
                (string)($d['create_time'] ?? ''),
                (string)($d['create_ip'] ?? ''),
                (string)($d['pay_time'] ?? ''),
                $statusText
            ]);
        }

        fclose($fp);

        if ($exportStatus === 1 && !empty($ids)) {
            try {
                $deleteBatchEntity = new Delete(UserRecharge::class, $ids);
                $this->query->delete($deleteBatchEntity);
            } catch (\Exception $e) {
            }
        }

        ManageLog::log($this->getManage(), '[充值订单导出]导出订单，共计：' . count($list));
        exit;
    }

}