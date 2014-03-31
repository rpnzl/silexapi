<?php
namespace SilexApi\Provider;

use Silex\Application;
use Silex\ServiceProviderInterface;
use Symfony\Component\HttpFoundation\Request;
use Composer\Autoload\ClassMapGenerator;
use ReflectionClass;
use ReflectionMethod;
use RuntimeException;

class ApiServiceProvider implements ServiceProviderInterface
{
    public function register(Application $app)
    {
        // API name
        $app['api.name']              = 'API';

        // The base namespace, is not included in route paths
        $app['api.namespace']         = 'Api';

        // The base route for the API
        $app['api.mount_point']       = '/'.strtolower($app['api.namespace']);

        // Application-specific status codes that map to valid HTTP codes
        $app['api.http_status_codes'] = array(
            // 803 => 404
        );

        // API Controller
        $app['api'] = $app->share(function () use ($app) {
            return new ApiControllerProvider;
        });

        // Routes Array
        $app['api.routes'] = $app->share(function () use ($app) {
            if (!is_dir($app['api.source_path'])) {
                throw new RuntimeException("api.source_path must be defined to use api.routes!");
            }

            $classes = array_filter(array_map(function ($v) use ($app) {
                preg_match('#^'.$app['api.namespace'].'#', $v, $matches);
                return count($matches) ? $v : null;
            }, array_keys(ClassMapGenerator::createMap($app['api.source_path']))));

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

            // Alter routes array if the mountpoint is different from the namespace
            $normalized_namespace = strtolower(str_replace('\\', '', $app['api.namespace']));
            $normalized_mount_point = strtolower(str_replace('/', '', $app['api.mount_point']));
            if ($normalized_namespace !== $normalized_mount_point) {
                foreach ($routes as $k => $v) {
                    $routes[$k] = preg_replace('#\/{2,4}#', '/', str_replace($normalized_namespace, $normalized_mount_point, $v));
                }
            }

            return $routes ?: array();
        });

        // Handle JSON Request Bodies
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
        // Mount at the given point
        $app->mount($app['api.mount_point'], $app['api']);
    }
}

/* End of file ApiServiceProvider.php */