<?php
namespace SilexApi\Provider;

use SilexApi\Handler;
use Silex\Application;
use Silex\ControllerProviderInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\JsonResponse;
use Doctrine\Common\Inflector\Inflector;

class ApiControllerProvider implements ControllerProviderInterface
{
    public function connect(Application $app)
    {
        $controllers = $app['controllers_factory'];

        /**
         * 
         */
        $controllers->match('/{version}/{path}', array($this, 'requestHandler'))->assert('path', '.*');
        $controllers->match('/{version}',        array($this, 'requestHandler'));
        $controllers->match('/',                 array($this, 'requestHandler'));

        return $controllers;
    }

    /**
     * 
     */
    public function requestHandler(Application $app, Request $request, $version = null, $path = null)
    {
        // 
        // Build API Handler
        // 

        $api  = new Handler($app, $request, $version, $path);
        $data = $api->processRequest();

        // 
        // Build Response
        // 

        $response = new JsonResponse($data, $api->mapStatus($data['meta']['status']));

        // Check for JSONP
        if ($request->get('callback')) {
            $response->setCallback($request->get('callback'));
        }

        return $response;
    }
}

/* End of file ApiControllerProvider.php */