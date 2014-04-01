<?php
namespace SilexApi\Provider;

use SilexApi\Handler;
use Silex\Application;
use Silex\ControllerProviderInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\EventDispatcher\GenericEvent;
use Doctrine\Common\Inflector\Inflector;

class ApiControllerProvider implements ControllerProviderInterface
{
    public function connect(Application $app)
    {
        $controllers = $app['controllers_factory'];

        /**
         * 
         */
        $controllers->match('/{path}', array($this, 'requestHandler'))->assert('path', '.*');
        $controllers->match('/',       array($this, 'requestHandler'));

        return $controllers;
    }

    /**
     * 
     */
    public function requestHandler(Application $app, Request $request, $path = null)
    {
        // 
        // Build API Handler
        // 

        $app['dispatcher']->dispatch('api.after_request', new GenericEvent($request));
        $api  = new Handler($app, $request, $path);
        $data = $api->processRequest();

        // 
        // Build Response
        // 

        $response = new JsonResponse($data, $api->mapStatus($data['meta']['status']));
        $app['dispatcher']->dispatch('api.before_response', new GenericEvent($response));

        // Check for JSONP
        if ($request->get('callback')) {
            $response->setCallback($request->get('callback'));
        }

        return $response;
    }
}

/* End of file ApiControllerProvider.php */