<?php

declare(strict_types=1);

namespace App\Controller\Admin\Api;


use App\Controller\Base\API\Manage;
use App\Entity\Query\Delete;
use App\Entity\Query\Get;
use App\Entity\Query\Save;
use App\Interceptor\ManageSession;
use App\Model\ManageLog;
use App\Service\Query;
use App\Util\Date;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\Relation;
use Kernel\Annotation\Inject;
use Kernel\Annotation\Interceptor;
use Kernel\Exception\JSONException;
use Kernel\Waf\Filter;

#[Interceptor(ManageSession::class, Interceptor::TYPE_API)]
class Order extends Manage
{
    #[Inject]
    private Query $query;

    /**
     * @return array
     */
    public function data(): array
    {
        $map = $_POST;
        $get = new Get(\App\Model\Order::class);
        $get->setPaginate((int)$this->request->post("page"), (int)$this->request->post("limit"));
        $get->setWhere($map);
        $raw = [];
        $data = $this->query->get($get, function (Builder $builder) use (&$raw) {
            $raw['order_amount'] = (clone $builder)->sum("amount");
            $raw['order_cost'] = (clone $builder)->sum("cost");
            return $builder->with([
                'coupon' => function (Relation $relation) {
                    $relation->select(["id", "code"]);
                },
                'owner' => function (Relation $relation) {
                    $relation->select(["id", "username", "avatar", "recharge"]);
                },
                'user' => function (Relation $relation) {
                    $relation->select(["id", "username", "avatar", "recharge"]);
                },
                'commodity' => function (Relation $relation) {
                    $relation->select(["id", "name", "cover", "delivery_way", "contact_type"]);
                },
                'pay' => function (Relation $relation) {
                    $relation->select(["id", "name", "icon"]);
                },
                //推广者
                'promote' => function (Relation $relation) {
                    $relation->select(["id", "username", "avatar", "recharge"]);
                },
                //分站订单
                'substationUser' => function (Relation $relation) {
                    $relation->select(["id", "username", "avatar", "recharge"]);
                },
                'card'
            ]);
        });

        return $this->json(data: array_merge($data, $raw));
    }


    /**
     * @return array
     * @throws JSONException
     */
    public function save(): array
    {
        $map = $this->request->post(flags: Filter::NORMAL);
        if (!$map['secret']) {
            throw new JSONException("请填写要发货的内容");
        }
        $save = new Save(\App\Model\Order::class);
        $save->setMap(['id' => (int)$map['id'], 'secret' => $map['secret'], 'delivery_status' => 1]);
        $save = $this->query->save($save);
        if (!$save) {
            throw new JSONException("发货失败");
        }

        ManageLog::log($this->getManage(), "[手动发货]({$map['id']})修改了发货信息");
        return $this->json(200, '（＾∀＾）发货成功');
    }


    /**
     * @return array
     */
    public function clear(): array
    {
        \App\Model\Order::query()
            ->where("create_time", "<", date("Y-m-d H:i:s", time() - 1800))
            ->where("status", 0)->delete();

        ManageLog::log($this->getManage(), "进行了一键清理无用商品订单操作");
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

        $get = new Get(\App\Model\Order::class);
        $get->setWhere($map);

        if ($exportNum > 0) {
            $get->setPaginate(1, $exportNum);
        }

        $data = $this->query->get($get, function (Builder $builder) {
            return $builder->with([
                'coupon' => function (Relation $relation) {
                    $relation->select(["id", "code"]);
                },
                'owner' => function (Relation $relation) {
                    $relation->select(["id", "username", "avatar", "recharge"]);
                },
                'user' => function (Relation $relation) {
                    $relation->select(["id", "username", "avatar", "recharge"]);
                },
                'commodity' => function (Relation $relation) {
                    $relation->select(["id", "name", "cover", "delivery_way", "contact_type"]);
                },
                'pay' => function (Relation $relation) {
                    $relation->select(["id", "name", "icon"]);
                },
                'promote' => function (Relation $relation) {
                    $relation->select(["id", "username", "avatar", "recharge"]);
                },
                'substationUser' => function (Relation $relation) {
                    $relation->select(["id", "username", "avatar", "recharge"]);
                }
            ]);
        });

        $list = $data['list'] ?? [];
        $ids = [];

        header('Content-Type: text/csv; charset=UTF-8');
        header('Content-Disposition: attachment; filename="订单导出-' . Date::current("YmdHis") . '.csv"');
        header('Pragma: no-cache');
        header('Expires: 0');

        $fp = fopen('php://output', 'w');

        fwrite($fp, "\xEF\xBB\xBF");

        fputcsv($fp, [
            '订单号',
            '金额',
            '商品名称',
            '数量',
            '支付方式',
            '下单时间',
            '下单IP',
            '下单设备',
            '支付时间',
            '订单状态',
            '联系方式',
            '发货状态',
            '优惠券',
            '客户',
            '推广人',
            '分站',
            '分站手续费',
            '接口手续费',
            '推广分成',
            '返利'
        ]);

        foreach ($list as $d) {
            $ids[] = $d['id'];

            $deviceText = match ((int)($d['create_device'] ?? 0)) {
                1 => '安卓',
                2 => 'IOS',
                3 => 'iPad',
                default => 'PC',
            };

            $statusText = match ((int)($d['status'] ?? 0)) {
                0 => '未支付',
                1 => '已支付',
                default => '未知',
            };

            $deliveryStatusText = match ((int)($d['delivery_status'] ?? 0)) {
                0 => '未发货',
                1 => '已发货',
                default => '未知',
            };


            fputcsv($fp, [
                (string)($d['trade_no'] ?? ''),
                (string)($d['amount'] ?? 0),
                (string)($d['commodity']['name'] ?? ''),
                (string)($d['card_num'] ?? 0),
                (string)($d['pay']['name'] ?? ''),
                (string)($d['create_time'] ?? ''),
                (string)($d['create_ip'] ?? ''),
                $deviceText,
                (string)($d['pay_time'] ?? ''),
                $statusText,
                (string)($d['contact'] ?? ''),
                $deliveryStatusText,
                (string)($d['coupon']['code'] ?? ''),
                (string)($d['owner']['username'] ?? ''),
                (string)($d['promote']['username'] ?? ''),
                (string)($d['user']['username'] ?? ''),
                (string)($d['cost'] ?? 0),
                (string)($d['pay_cost'] ?? 0),
                (string)($d['divide_amount'] ?? 0),
                (string)($d['rebate'] ?? 0),
            ]);
        }

        fclose($fp);

        if ($exportStatus === 1 && !empty($ids)) {
            try {
                $deleteBatchEntity = new Delete(\App\Model\Order::class, $ids);
                $this->query->delete($deleteBatchEntity);
            } catch (\Exception $e) {
            }
        }

        ManageLog::log($this->getManage(), '[订单导出]导出订单，共计：' . count($list));
        exit;
    }
}
