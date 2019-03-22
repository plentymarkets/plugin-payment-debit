<?php
namespace Debit\Providers;

use Plenty\Plugin\RouteServiceProvider;
use Plenty\Plugin\Routing\Router;
use Plenty\Plugin\Routing\ApiRouter;

class DebitRouteServiceProvider extends RouteServiceProvider
{

    /**
     * @param Router $router
     */
    public function map(Router $router , ApiRouter $apiRouter)
    {
       $apiRouter->version(['v1'], ['middleware' => ['oauth']],
            function ($routerApi)
            {
                /** @var ApiRouter $routerApi*/
                $routerApi->get('payment/debit/settings/{plentyId}/{lang}', ['uses' => 'Debit\Controllers\SettingsController@loadSettings']);
                $routerApi->put('payment/debit/settings', ['uses' => 'Debit\Controllers\SettingsController@saveSettings']);
            });

        $router->get('payment/debit/bankdetails/{orderId}', 'Debit\Controllers\DebitController@getBankDetails');
        $router->post('payment/debit/bankdetails', 'Debit\Controllers\DebitController@setBankDetails');
        $router->post('payment/debit/updateBankDetails', 'Debit\Controllers\DebitController@updateBankDetails');
    }

}