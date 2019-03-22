<?php

namespace Debit\Controllers;

use Debit\Services\SessionStorageService;
use Plenty\Modules\Account\Contact\Contracts\ContactPaymentRepositoryContract;
use Plenty\Modules\Account\Contact\Contracts\ContactRepositoryContract;
use Plenty\Modules\Account\Contact\Models\ContactBank;
use Plenty\Modules\Authorization\Services\AuthHelper;
use Plenty\Modules\Basket\Contracts\BasketRepositoryContract;
use Plenty\Modules\Frontend\Services\AccountService;
use Plenty\Plugin\Controller;
use Plenty\Plugin\Templates\Twig;
use Plenty\Plugin\Http\Response;

class DebitController extends Controller
{
    /**
     * @var SessionStorageService $sessionStorageService
     */
    private $sessionStorageService;
    /**
     * @var AccountService $accountService
     */
    private $accountService;

    public function __construct(AccountService $accountService, SessionStorageService $sessionStorageService)
    {
        $this->sessionStorageService = $sessionStorageService;
        $this->accountService = $accountService;
    }

    public function getBankDetails( Twig $twig, $orderId)
    {
        /** @var ContactPaymentRepositoryContract $paymentRepo */
        $paymentRepo = pluginApp(ContactPaymentRepositoryContract::class);

        /** @var AuthHelper $authHelper */
        $authHelper = pluginApp(AuthHelper::class);

        $contactBank = $authHelper->processUnguarded(function () use ($paymentRepo, $orderId) {
            return $paymentRepo->getBankByOrderId($orderId);
        });

        if (is_null($contactBank)) {
            /** @var ContactRepositoryContract $contactRepository */
            $contactRepository = pluginApp(ContactRepositoryContract::class);
            $contact = $authHelper->processUnguarded(function () use ($contactRepository) {
                return $contactRepository->findContactById($this->accountService->getAccountContactId());
            });

            $bank = $contact->banks->first();
            if($bank instanceof ContactBank)
            {
                $bankAccount['bankAccountOwner'] =  $bank->accountOwner;
                $bankAccount['bankName']         =	$bank->bankName;
                $bankAccount['bankIban']	     =	$bank->iban;
                $bankAccount['bankSwift']		 =	$bank->bic;
                $bankAccount['bankId']		     =	0;
            }
        } else {
            $bankAccount['bankAccountOwner'] =  $contactBank->accountOwner;
            $bankAccount['bankName']         =	$contactBank->bankName;
            $bankAccount['bankIban']	     =	$contactBank->iban;
            $bankAccount['bankSwift']		 =	$contactBank->bic;
            $bankAccount['bankId']		     =	$contactBank->id;
        }

        return $twig->render('Debit::BankDetailsOverlay', [
            "action"            => "payment/debit/updateBankDetails",
            "bankAccountOwner"  => $bankAccount['bankAccountOwner'],
            "bankName"          => $bankAccount['bankName'],
            "bankIban"          => $bankAccount['bankIban'],
            "bankSwift"         => $bankAccount['bankSwift'],
            "bankId"            => $bankAccount['bankId'],
            "orderId"           => $orderId,
        ]);
    }

    /*
     *
     * @return BaseResponse
     */
    public function setBankDetails(Response $response)
    {
        /** @var BasketRepositoryContract $basketRepo */
        $basketRepo = pluginApp(BasketRepositoryContract::class);
        $basket = $basketRepo->load();

        $bankData = [
            'contactId'     => $basket->customerId,
            'accountOwner'  => $_REQUEST['bankAccountOwner'],
            'bankName'      => $_REQUEST['bankName'],
            'iban'          => $_REQUEST['bankIban'],
            'bic'           => $_REQUEST['bankSwift'],
            'lastUpdateBy'  => 'customer'
        ];

        try
        {
            $contactBank = $this->createContactBank($bankData);

            $this->sessionStorageService->setSessionValue('contactBank', $contactBank);

            return $response->redirectTo('place-order');
        }
        catch(\Exception $e)
        {
            return $response->redirectTo('checkout');
        }
    }

    /*
     *
     * @return BaseResponse
     */
    public function updateBankDetails(Response $response)
    {
        $bankData = [
            'contactId'     => $this->accountService->getAccountContactId(),
            'accountOwner'  => $_REQUEST['bankAccountOwner'],
            'bankName'      => $_REQUEST['bankName'],
            'iban'          => $_REQUEST['bankIban'],
            'bic'           => $_REQUEST['bankSwift'],
            'lastUpdateBy'  => 'customer',
            'orderId'       => $_REQUEST['orderId']
        ];

        if (isset($_REQUEST['bankId']) && $_REQUEST['bankId'] > 0) {
            //update existing bankaccount
            $bankData['bankId'] = $_REQUEST['bankId'];
            $this->updateContactBank($bankData);
        } else {
            //create new bank account
            $this->createContactBank($bankData);
        }

        return $response->redirectTo('my-account');
    }

    private function createContactBank($bankData) {
        /** @var \Plenty\Modules\Authorization\Services\AuthHelper $authHelper */
        $authHelper = pluginApp(AuthHelper::class);

        /** @var ContactPaymentRepositoryContract $paymentRepo */
        $paymentRepo = pluginApp(ContactPaymentRepositoryContract::class);

        $contactBank = $authHelper->processUnguarded(function () use ($paymentRepo, $bankData) {
            return $paymentRepo->createContactBank($bankData);
        });

        return $contactBank;
    }

    private function updateContactBank($bankData) {
        /** @var \Plenty\Modules\Authorization\Services\AuthHelper $authHelper */
        $authHelper = pluginApp(AuthHelper::class);

        /** @var ContactPaymentRepositoryContract $paymentRepo */
        $paymentRepo = pluginApp(ContactPaymentRepositoryContract::class);

        $contactBank = $authHelper->processUnguarded(function () use ($paymentRepo, $bankData) {
            return $paymentRepo->updateContactBank($bankData, $bankData['bankId']);
        });

        return $contactBank;
    }
}