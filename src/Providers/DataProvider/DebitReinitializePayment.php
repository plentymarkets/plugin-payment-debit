<?php

namespace Debit\Providers\DataProvider;

use Plenty\Plugin\Templates\Twig;
use Debit\Helper\DebitHelper;

class DebitReinitializePayment
{
    public function call(Twig $twig, $arg): string
    {
        /** @var DebitHelper $paymentHelper */
        $paymentHelper = pluginApp(DebitHelper::class);
        $paymentId = $paymentHelper->getDebitMopId();
        return $twig->render('Debit::DebitReinitializePayment', ["order" => $arg[0], "paymentMethodId" => $paymentId]);
    }
}