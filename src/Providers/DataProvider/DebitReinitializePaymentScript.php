<?php

namespace Debit\Providers\DataProvider;

use Plenty\Plugin\Templates\Twig;
use Debit\Helper\DebitHelper;

class DebitReinitializePaymentScript
{
	public function call(Twig $twig): string
	{
		/** @var DebitHelper $paymentHelper */
		$paymentHelper = pluginApp(DebitHelper::class);
		$paymentId = $paymentHelper->getDebitMopId();
		return $twig->render('Debit::DebitReinitializePaymentScript', ['paymentMethodId' => $paymentId]);
	}
}