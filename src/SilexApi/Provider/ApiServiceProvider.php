<?php
namespace SilexApi\Provider;

use Silex\Application;
use Silex\ServiceProviderInterface;
use Symfony\Component\HttpFoundation\Request;

class ApiServiceProvider implements ServiceProviderInterface
{
    public function register(Application $app)
    {
        $app['api.name']      = 'API';
        $app['api.namespace'] = 'Api';
        
        $app['api'] = $app->share(function () use ($app) {
            return new ApiControllerProvider;
        });

        $app->before(function (Request $request) use ($app) {
            if (0 === strpos($request->headers->get('Content-Type'), 'application/json')) {
                $data = json_decode($request->getContent(), true);
                $request->request->replace(is_array($data) ? $data : array());
            }
        });

        return $app['api'];
    }

    public function boot(Application $app)
    {
    }
}