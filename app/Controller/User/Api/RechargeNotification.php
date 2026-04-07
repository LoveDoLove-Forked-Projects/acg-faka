<?php
declare(strict_types=1);

namespace App\Controller\User\Api;


use App\Controller\Base\API\User;
use App\Interceptor\Waf;
use App\Util\Str;
use Kernel\Annotation\Inject;
use Kernel\Annotation\Interceptor;
use Kernel\Context\Interface\Request;
use Kernel\Exception\JSONException;

#[Interceptor(Waf::class, Interceptor::TYPE_API)]
class RechargeNotification extends User
{
    #[Inject]
    private \App\Service\Recharge $recharge;

    /**
     * @param Request $request
     * @return string
     * @throws JSONException
     */
    public function callback(Request $request): string
    {
        $handle = $_GET['_PARAMETER'][0];
        foreach (['unsafePost', 'unsafeJson', 'unsafeGet'] as $method) {
            $data = $request->$method();
            if (!empty($data)) {
                break;
            }
        }
        if (isset($data['s'])) unset($data['s']);
        if (isset($data['_PARAMETER'])) unset($data['_PARAMETER']);

        if (isset($data['sign']) && Str::isInvalidSign($data['sign'])) {
            throw new JSONException("非法签名");
        }

        if (isset($data['signature']) && Str::isInvalidSign($data['signature'])) {
            throw new JSONException("非法签名");
        }

        return $this->recharge->callback($handle, $data);
    }
}