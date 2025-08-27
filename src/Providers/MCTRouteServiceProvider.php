<?php
namespace MCT\Providers;

use Plenty\Plugin\Routing\ApiRouter;
use Plenty\Plugin\RouteServiceProvider;

/**
 * Class MCTRouteServiceProvider
 * @package MCT\Providers
 */
class MCTRouteServiceProvider extends RouteServiceProvider
{
    /**
     * @param  ApiRouter  $apiRouter
     */
    public function map(ApiRouter $apiRouter)
    {
        $apiRouter->version(['v1'], ['namespace' => 'MCT\Controllers', 'middleware' => 'oauth'],
            function ($apiRouter) {
                $apiRouter->get('MCT/test/', 'TestController@testMethod');
                $apiRouter->get('MCT/cleartable/', 'TestController@clearDataTable');
                $apiRouter->put('MCT/deleteOneOrderFromDataTable/{productCode}', 'TestController@clearOneOrderFromDataTable');
            }
        );
    }
}
