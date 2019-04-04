<?php

namespace Debit\Providers\DataProvider;

use Plenty\Modules\Payment\Contracts\PaymentRepositoryContract;
use Plenty\Plugin\Templates\Twig;
use Debit\Helper\DebitHelper;

class DebitReinitializePayment
{
    public function call(Twig $twig, $arg): string
    {
        /** @var DebitHelper $paymentHelper */
        $paymentHelper = pluginApp(DebitHelper::class);
        $paymentId = $paymentHelper->getDebitMopId();

        $order = $arg[0];

        /** @var PaymentRepositoryContract $paymentRepo */
        $paymentRepo = pluginApp(PaymentRepositoryContract::class);
        $orderPayments = $paymentRepo->getPaymentsByOrderId($order['id']);

        $hasNoPayments = false;
        if ($orderPayments == null) {
            $hasNoPayments = true;
        }

        return $twig->render('Debit::DebitReinitializePayment', ["order" => $order, "paymentMethodId" => $paymentId, "paymentExists" => !$hasNoPayments]);
    }
}