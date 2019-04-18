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
            });

        $router->get('rest/payment/debit/bankdetails/{orderId}', 'Debit\Controllers\DebitController@getBankDetails');
        $router->post('rest/payment/debit/bankdetails', 'Debit\Controllers\DebitController@setBankDetails');
        $router->post('rest/payment/debit/updateBankDetails', 'Debit\Controllers\DebitController@updateBankDetails');
    }

}