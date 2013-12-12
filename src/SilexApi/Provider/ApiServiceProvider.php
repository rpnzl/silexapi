<?php
namespace SilexApi\Provider;

use Silex\Application;
use Silex\ServiceProviderInterface;

class ApiServiceProvider implements ServiceProviderInterface
{
    public function register(Application $app)
    {
        $app['api.name']      = 'API';
        $app['api.namespace'] = 'Api';
        
        $app['api'] = $app->share(function () use ($app) {
            return new ApiControllerProvider;
        });

        return $app['api'];
    }

    public function boot(Application $app)
    {
    }
}