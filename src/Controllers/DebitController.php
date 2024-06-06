<?php

namespace Debit\Controllers;

use Debit\Helper\DebitHelper;
use Debit\Helper\PluginConstants;
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
use Plenty\Plugin\Log\Loggable;

class DebitController extends Controller
{
    use Loggable;
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

        $this->getLogger(PluginConstants::PLUGIN_NAME)->critical('Get bank details for order ', $orderId);
        $this->getLogger(PluginConstants::PLUGIN_NAME)->info('Get bank details for order ', $orderId);
        $this->getLogger(PluginConstants::PLUGIN_NAME)->debug('Get bank details for order ', $orderId);
        /** @var ContactPaymentRepositoryContract $paymentRepo */
        $paymentRepo = pluginApp(ContactPaymentRepositoryContract::class);

        /** @var AuthHelper $authHelper */
        $authHelper = pluginApp(AuthHelper::class);

        $contactBank = $authHelper->processUnguarded(function () use ($paymentRepo, $orderId) {
            return $paymentRepo->getBankByOrderId($orderId);
        });

        if (is_null($contactBank)) {
            $this->getLogger(PluginConstants::PLUGIN_NAME)->error('Search for contact bank without orderId');
            $accountContactId = $this->accountService->getAccountContactId();
            if($accountContactId>0) {
                $this->getLogger(PluginConstants::PLUGIN_NAME)->error('Search bank details by contactId ', $accountContactId);
                /** @var ContactRepositoryContract $contactRepository */
                $contactRepository = pluginApp(ContactRepositoryContract::class);
                $contact = $authHelper->processUnguarded(function () use ($contactRepository, $accountContactId) {
                    return $contactRepository->findContactById($accountContactId);
                });

                $bank = $contact->banks->last();
                if($bank instanceof ContactBank)
                {
                    $this->getLogger(PluginConstants::PLUGIN_NAME)->error('Use last bank of found contact ', $bank);
                    $bankAccount['bankAccountOwner'] =  $bank->accountOwner;
                    $bankAccount['bankName']         =	$bank->bankName;
                    $bankAccount['bankIban']	     =	$bank->iban;
                    $bankAccount['bankBic']		     =	$bank->bic;
                    $bankAccount['bankId']		     =	0;
                    $bankAccount['debitMandate']	 =	'';
                }
            }
        } else {
            $this->getLogger(PluginConstants::PLUGIN_NAME)->error('use bank details from order ', $orderId);
            $bankAccount['bankAccountOwner'] =  $contactBank->accountOwner;
            $bankAccount['bankName']         =	$contactBank->bankName;
            $bankAccount['bankIban']	     =	$contactBank->iban;
            $bankAccount['bankBic']		     =	$contactBank->bic;
            $bankAccount['bankId']		     =	$contactBank->id;
            $bankAccount['debitMandate']	 =	($contactBank->directDebitMandateAvailable ? 'checked' : '');
        }

        $this->getLogger(PluginConstants::PLUGIN_NAME)->error('Render successfully BankDetailsOverlay', $bankAccount);
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
        $this->getLogger(PluginConstants::PLUGIN_NAME)->error('Set bank details', $request);
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

        $this->getLogger(PluginConstants::PLUGIN_NAME)->error('Current used bank data ', $bankData);
        try
        {
            //check if this contactBank already exist
            $contactBankExists = false;
            if ($bankData['contactId'] != NULL) {
                $this->getLogger(PluginConstants::PLUGIN_NAME)->error('Check if this contactBank already exist on user ', $bankData['contactId']);
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
                            $this->getLogger(PluginConstants::PLUGIN_NAME)->error('Contact bank was found on contact', $contactBank);
                            return true;
                        }
                    }
                    $this->getLogger(PluginConstants::PLUGIN_NAME)->error('Contact bank was Not found', $bankData);
                    return false;
                });
            }

            $bankData['lastUpdateBy'] = 'customer';
            if (!$contactBankExists) {
                $this->getLogger(PluginConstants::PLUGIN_NAME)->error('Contact bank doesn\'t exist. Contact bank will be created', $bankData);
                /** @var ContactBank $newContactBank */
                $newContactBank = $this->createContactBank($bankData);
                $this->getLogger(PluginConstants::PLUGIN_NAME)->error('New contact bank was created ', $newContactBank);
            }
            $bankData['contactId'] = null;
            $bankData['directDebitMandateAvailable'] = 1;
            $bankData['directDebitMandateAt'] = date('Y-m-d H:i:s');
            $bankData['paymentMethod'] = 'onOff';
            $this->getLogger(PluginConstants::PLUGIN_NAME)->error('Create contact bank with empty contactId ', $bankData);
            $contactBank = $this->createContactBank($bankData);
            $this->getLogger(PluginConstants::PLUGIN_NAME)->error('Contact bank with empty contactId was created', $bankData);

            $this->sessionStorageService->setSessionValue('contactBank', $contactBank);

            $this->getLogger(PluginConstants::PLUGIN_NAME)->error('place-order');
            return $response->redirectTo($this->sessionStorageService->getLang() . '/place-order');
        }
        catch(\Exception $e)
        {
            $this->getLogger(PluginConstants::PLUGIN_NAME)->error('Exception in contact bank processing', $e->getMessage());
            return $response->redirectTo($this->sessionStorageService->getLang().'/checkout');
        }
    }

    /*
     *
     * @return BaseResponse
     */
    public function updateBankDetails(Response $response, Request $request)
    {
        $this->getLogger(PluginConstants::PLUGIN_NAME)->error('UpdateBankDetails', $request);
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
            //update existing bank account
            $bankData['bankId'] = $request->get('bankId');
            $this->getLogger(PluginConstants::PLUGIN_NAME)->error('Update existing bank account', $bankData);
            $contactBank = $this->updateContactBank($bankData);
        } else {
            //create new bank account
            $this->getLogger(PluginConstants::PLUGIN_NAME)->error('Create bank account', $bankData);
            $contactBank = $this->createContactBank($bankData);
        }

        /** @var DebitHelper $debitHelper */
        $debitHelper = pluginApp(DebitHelper::class);
        // Create a plentymarkets payment
        $this->getLogger(PluginConstants::PLUGIN_NAME)->error('Create Plenty payment', $contactBank);
        $plentyPayment = $debitHelper->createPlentyPayment($orderId, $contactBank);



        if($plentyPayment instanceof Payment) {
            $this->getLogger(PluginConstants::PLUGIN_NAME)->error('Created Plenty payment', $plentyPayment);
            // Assign the payment to an order in plentymarkets
            $debitHelper->assignPlentyPaymentToPlentyOrder($plentyPayment, $orderId);
            $this->getLogger(PluginConstants::PLUGIN_NAME)->error('Assigned Plenty payment to orderId', $orderId);
        }

        $this->getLogger(PluginConstants::PLUGIN_NAME)->error('Success, go to confirmation', $orderId);
        return $response->redirectTo($this->sessionStorageService->getLang().'/confirmation/'.$orderId);
    }


    /**
     * @param $bankData
     * @return mixed
     */
    private function createContactBank($bankData) {

        $this->getLogger(PluginConstants::PLUGIN_NAME)->error('Started createContactBank', $bankData);
        /** @var \Plenty\Modules\Authorization\Services\AuthHelper $authHelper */
        $authHelper = pluginApp(AuthHelper::class);

        /** @var ContactPaymentRepositoryContract $paymentRepo */
        $paymentRepo = pluginApp(ContactPaymentRepositoryContract::class);

        $contactBank = $authHelper->processUnguarded(function () use ($paymentRepo, $bankData) {
            $this->getLogger(PluginConstants::PLUGIN_NAME)->error('Create contact bank', $bankData);
            /** @var ContactBank $newContactBank */
            $newContactBank = $paymentRepo->createContactBank($bankData);
            $this->getLogger(PluginConstants::PLUGIN_NAME)->error('Created contact bank', $newContactBank);
            return $newContactBank;
        });

        $this->getLogger(PluginConstants::PLUGIN_NAME)->error('Contact bank not created, return contact bank', $contactBank);
        return $contactBank;
    }

    private function updateContactBank($bankData) {
        $this->getLogger(PluginConstants::PLUGIN_NAME)->error('Started updateContactBank', $bankData);
        /** @var \Plenty\Modules\Authorization\Services\AuthHelper $authHelper */
        $authHelper = pluginApp(AuthHelper::class);

        /** @var ContactPaymentRepositoryContract $paymentRepo */
        $paymentRepo = pluginApp(ContactPaymentRepositoryContract::class);

        $contactBank = $authHelper->processUnguarded(function () use ($paymentRepo, $bankData) {
            return $paymentRepo->updateContactBank($bankData, $bankData['bankId']);
        });
        $this->getLogger(PluginConstants::PLUGIN_NAME)->error('Updated contact bank', $contactBank);
        return $contactBank;
    }
}
