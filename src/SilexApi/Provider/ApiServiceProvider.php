<?php
namespace SilexApi\Provider;

use Silex\Application;
use Silex\ServiceProviderInterface;
use Symfony\Component\HttpFoundation\Request;

class ApiServiceProvider implements ServiceProviderInterface
{
    public function register(Application $app)
    {
        $app['api.name']              = 'API';
        $app['api.namespace']         = 'Api';
        $app['api.http_status_codes'] = array(
            // 803 => 404
        );

        // 
        $app['api'] = $app->share(function () use ($app) {
            return new ApiControllerProvider;
        });

        // 
        $app['api.routes'] = $app->share(function () use ($app) {
            if (!is_dir($app['api.source_path'])) {
                throw new RuntimeException("api.source_path must be defined to use api.routes!");
            }

            $classes = array_filter(array_map(function ($v) use ($app) {
                preg_match('#^'.$app['api.namespace'].'#', $v, $matches);
                return count($matches) ? $v : null;
            }, array_keys(Composer\Autoload\ClassMapGenerator::createMap($app['api.source_path']))));

            $routes = array();
            foreach ($classes as $v) {
                $class = new ReflectionClass($v);
                $class_routes = array_filter(array_map(function ($v) use ($class) {
                    $http_methods = array('get', 'post', 'put', 'delete');
                    preg_match('#^'.implode('|', $http_methods).'#', $v->name, $matches);
                    if (in_array($v->name, $http_methods)) {
                        return strtoupper($matches[0]).' /'.strtolower(str_replace('\\', '/', $class->name));
                    } else if (count($matches)) {
                        $segment = str_replace($matches[0], '', $v->name);
                        $base_route = str_replace('\\', '/', $class->name);
                        return strtoupper($matches[0]).' /'.strtolower($base_route.'/'.$segment);
                    }
                }, $class->getMethods(ReflectionMethod::IS_PUBLIC)));
                $routes = array_merge($routes, $class_routes);
            }

            return $routes ?: array();
        });

        // 
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

/* End of file ApiServiceProvider.php */