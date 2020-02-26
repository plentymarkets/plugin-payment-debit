<?php

namespace Debit\Controllers;

use Debit\Helper\DebitHelper;
use Debit\Services\SessionStorageService;
use Plenty\Modules\Account\Contact\Contracts\ContactPaymentRepositoryContract;
use Plenty\Modules\Account\Contact\Contracts\ContactRepositoryContract;
use Plenty\Modules\Account\Contact\Models\ContactBank;
use Plenty\Modules\Authorization\Services\AuthHelper;
use Plenty\Modules\Basket\Contracts\BasketRepositoryContract;
use Plenty\Modules\Frontend\Services\AccountService;
use Plenty\Modules\Payment\Models\Payment;
use Plenty\Plugin\Controller;
use Plenty\Plugin\Templates\Twig;
use Plenty\Plugin\Http\Response;
use Plenty\Plugin\Http\Request;

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
            $accountContactId = $this->accountService->getAccountContactId();
            if($accountContactId>0) {
                /** @var ContactRepositoryContract $contactRepository */
                $contactRepository = pluginApp(ContactRepositoryContract::class);
                $contact = $authHelper->processUnguarded(function () use ($contactRepository, $accountContactId) {
                    return $contactRepository->findContactById($accountContactId);
                });

                $bank = $contact->banks->last();
                if($bank instanceof ContactBank)
                {
                    $bankAccount['bankAccountOwner'] =  $bank->accountOwner;
                    $bankAccount['bankName']         =	$bank->bankName;
                    $bankAccount['bankIban']	     =	$bank->iban;
                    $bankAccount['bankBic']		     =	$bank->bic;
                    $bankAccount['bankId']		     =	0;
                    $bankAccount['debitMandate']	 =	'';
                }
            }
        } else {
            $bankAccount['bankAccountOwner'] =  $contactBank->accountOwner;
            $bankAccount['bankName']         =	$contactBank->bankName;
            $bankAccount['bankIban']	     =	$contactBank->iban;
            $bankAccount['bankBic']		     =	$contactBank->bic;
            $bankAccount['bankId']		     =	$contactBank->id;
            $bankAccount['debitMandate']	 =	($contactBank->directDebitMandateAvailable ? 'checked' : '');
        }

        return $twig->render('Debit::BankDetailsOverlay', [
            "action"            => "/rest/payment/debit/updateBankDetails",
            "bankAccountOwner"  => $bankAccount['bankAccountOwner'],
            "bankName"          => $bankAccount['bankName'],
            "bankIban"          => $bankAccount['bankIban'],
            "bankBic"           => $bankAccount['bankBic'],
            "bankId"            => $bankAccount['bankId'],
            "debitMandate"      => ($bankAccount['debitMandate'] ? 'checked' : ''),
            "orderId"           => $orderId,
        ]);
    }

    /*
     *
     * @return BaseResponse
     */
    public function setBankDetails(Response $response, Request $request)
    {
        /** @var BasketRepositoryContract $basketRepo */
        $basketRepo = pluginApp(BasketRepositoryContract::class);
        $basket = $basketRepo->load();

        $bankData = [
            'contactId'     => $basket->customerId,
            'accountOwner'  => $request->get('bankAccountOwner'),
            'bankName'      => $request->get('bankName'),
            'iban'          => $request->get('bankIban'),
            'bic'           => $request->get('bankBic'),
            'lastUpdateBy'  => 'customer'
        ];

        try
        {
            //check if this contactBank already exist
            $contactBankExists = false;
            if ($bankData['contactId'] != NULL) {
                /** @var \Plenty\Modules\Authorization\Services\AuthHelper $authHelper */
                $authHelper = pluginApp(AuthHelper::class);

                /** @var ContactPaymentRepositoryContract $paymentRepo */
                $paymentRepo = pluginApp(ContactPaymentRepositoryContract::class);
                $contactBankExists = $authHelper->processUnguarded(function () use ($paymentRepo, $bankData) {
                    $contactBanks = $paymentRepo->getBanksOfContact($bankData['contactId'], ['contactId', 'accountOwner', 'bankName', 'iban', 'bic']);
                    foreach ($contactBanks as $contactBank) {
                        if ($contactBank->contactId == $bankData['contactId']
                            && $contactBank->accountOwner == $bankData['accountOwner']
                            && $contactBank->bankName == $bankData['bankName']
                            && $contactBank->iban == $bankData['iban']
                            && $contactBank->bic == $bankData['bic']
                        ) {
                            return true;
                        }
                    }
                    return false;
                });
            }

            $bankData['lastUpdateBy'] = 'customer';
            if (!$contactBankExists) {
                $this->createContactBank($bankData);
            }
            $bankData['contactId'] = null;
            $bankData['directDebitMandateAvailable'] = 1;
            $bankData['directDebitMandateAt'] = date('Y-m-d H:i:s');
            $bankData['paymentMethod'] = 'onOff';
            $contactBank = $this->createContactBank($bankData);

            $this->sessionStorageService->setSessionValue('contactBank', $contactBank);

            return $response->redirectTo($this->sessionStorageService->getLang().'/place-order');
        }
        catch(\Exception $e)
        {
            return $response->redirectTo($this->sessionStorageService->getLang().'/checkout');
        }
    }

    /*
     *
     * @return BaseResponse
     */
    public function updateBankDetails(Response $response, Request $request)
    {
        $orderId = $request->get('orderId');
        $bankData = [
            'accountOwner'  => $request->get('bankAccountOwner'),
            'bankName'      => $request->get('bankName'),
            'iban'          => $request->get('bankIban'),
            'bic'           => $request->get('bankBic'),
            'lastUpdateBy'  => 'customer',
            'orderId'       => $orderId
        ];
        if ($request->has('bankId') && $request->get('bankId') > 0) {
            //update existing bankaccount
            $bankData['bankId'] = $request->get('bankId');
            $contactBank = $this->updateContactBank($bankData);
        } else {
            //create new bank account
            $contactBank = $this->createContactBank($bankData);
        }

        /** @var DebitHelper $debitHelper */
        $debitHelper = pluginApp(DebitHelper::class);
        // Create a plentymarkets payment
        $plentyPayment = $debitHelper->createPlentyPayment($orderId, $contactBank);

        if($plentyPayment instanceof Payment) {
            // Assign the payment to an order in plentymarkets
            $debitHelper->assignPlentyPaymentToPlentyOrder($plentyPayment, $orderId);
        }

        return $response->redirectTo($this->sessionStorageService->getLang().'/confirmation/'.$orderId);
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