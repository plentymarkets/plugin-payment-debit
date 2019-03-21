<?php

namespace Debit\Controllers;

use Plenty\Modules\Account\Contact\Contracts\ContactRepositoryContract;
use Plenty\Modules\Account\Contact\Models\ContactBank;
use Plenty\Modules\Basket\Contracts\BasketRepositoryContract;
use Plenty\Modules\Frontend\Services\AccountService;
use Plenty\Plugin\Controller;
use Plenty\Plugin\Templates\Twig;

class DebitController extends Controller
{
	public function getBankDetails( Twig            $twig,
                                    AccountService  $accountService)
	{
	    $bankAccount = array();

        /** @var BasketRepositoryContract $basketRepo */
        $basketRepo = pluginApp(BasketRepositoryContract::class);
        $basket = $basketRepo->load();


        if($accountService->getIsAccountLoggedIn() && $basket->customerId > 0) {
            /** @var ContactRepositoryContract $contactRepository */
            $contactRepository = pluginApp(ContactRepositoryContract::class);
            $contact = $contactRepository->findContactById($basket->customerId);

            $bank = $contact->banks->first();
            if($bank instanceof ContactBank)
            {
                $bankAccount['bankAccountOwner'] =  $bank->accountOwner;
                $bankAccount['bankName']         =	$bank->bankName;
                $bankAccount['bankIban']	     =	$bank->iban;
                $bankAccount['bankSwift']		 =	$bank->bic;
            }
        }
        return $twig->render('Debit::BankDetailsOverlay', [
            "bankAccountOwner"  => $bankAccount['bankAccountOwner'],
            "bankName"          => $bankAccount['bankName'],
            "bankIban"          => $bankAccount['bankIban'],
            "bankSwift"         => $bankAccount['bankSwift']
        ]);
	}
}