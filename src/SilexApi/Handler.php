<?php
namespace SilexApi;

use ReflectionClass;
use ReflectionMethod;
use Silex\Application;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Doctrine\Common\Inflector\Inflector;

/**
 * The API request handler that instantiates the
 * requested API controller and calls the requested
 * method.
 */
class Handler
{
    /** @var  $app  \Silex\Application  The current application */
    protected $app;

    /** @var  $request  \Symfony\Component\HttpFoundation\Request  The current request */
    public $request;

    /** @var $query  arr  Modified query with some API specific values removed */
    protected $query;

    /** @var $pagination  arr  Pagination query parameters with defaults */
    protected $pagination = array('page' => 1, 'count' => 10);

    /** @var  $route  arr  Contains the current route pieces */
    private $route = array('controller' => null, 'method' => null);

    /** @var  $args  arr  Any arguments passed as a URI segment */
    private $args = array();

    /** @var  $default_response  arr  The default response array */
    private $default_response = array(
        'data' => null,
        'meta' => array(
            'error'   => false,
            'message' => 'OK',
            'status'  => 200
        ),
    );

    /**
     * Parses the request path and returns an
     * instance of the correct API controller.
     */
    public function __construct(Application $app, Request $request, $path = null)
    {
        $this->app     = $app;
        $this->request = $request;
        $this->path    = $path;

        // Remove non-query keys
        $this->query = array_diff_key($this->request->query->all(), array(
            'key'   => 1,
            'page'  => 1,
            'count' => 1
        ));

        // Set pagination parameters
        $this->pagination = array_map('intval', array_merge($this->pagination, array_intersect_key($this->request->query->all(), $this->pagination)));
        $this->pagination['offset'] = $this->pagination['count']*$this->pagination['page'] ?: 0;
    }

    /**
     * Routes the request to the correct API
     * controller and method, handling API exceptions
     * and returning valid response arrays.
     */
    public function processRequest()
    {
        try {

            // Authenticate request with user defined callback
            if ( isset($this->app['api.authentication'])
                 && is_callable($this->app['api.authentication'])
            ) {
                $this->app['api.authentication']($this->app, $this->request);
            }

            // Controller must exist
            if (empty($this->path)) {
                return array_replace_recursive($this->default_response, array(
                    'meta' => array('message' => $this->app['api.name'])
                ));
            }

            // Build controller
            $bits = explode('/', $this->path);
            for ($i = 0, $l = count($bits); $i < $l; $i++) {
                $this->route['controller'].= ($i > 0 ? '\\' : '').Inflector::classify($bits[$i]);
                if (class_exists($this->route['controller'])) {
                    $this->args = array_slice($bits, $i + 1, $l);
                    $this->route['method'] = array_shift($this->args);
                    break;
                }
            }

            // Verify that a controller was found
            if (!class_exists($this->route['controller'])) {
                throw new Exception("That's an invalid endpoint!", 403);
            }

            $controller = new $this->route['controller']($this->app, $this->request);

            // Get an array of valid method names from controller class,
            // removing any methods inherited from the base API class.
            $refl_parent        = new ReflectionClass($this);
            $refl_controller    = new ReflectionClass($controller);
            $http_methods       = array('get', 'post', 'put', 'delete');
            $parent_methods     = array_map(function ($v) { return $v->name; }, $refl_parent->getMethods());
            $controller_methods = array_map(function ($v) { return $v->name; }, $refl_controller->getMethods());
            $valid_methods      = array_diff($controller_methods, $parent_methods, $http_methods);

            // Check for method by name, default to HTTP method if
            // it exists in the controller.
            if (!$this->route['method']) {
                $this->route['method'] = strtolower($this->request->getMethod());
            } else if (!in_array($this->route['method'], $valid_methods)) {
                $original_method       = $this->route['method'];
                $this->route['method'] = strtolower($this->request->getMethod()).ucfirst($this->route['method']);
                if (!in_array($this->route['method'], $valid_methods)) {
                    array_unshift($this->args, $original_method);
                    $this->route['method'] = strtolower($this->request->getMethod());
                }
            }

            // Sub-Method
            if (isset($this->args[1])) {
                $http_method = strtolower($this->request->getMethod());
                $sub_method  = $http_method.Inflector::classify($this->args[1]);

                // Sub-Method must exist
                if (!method_exists($controller, $sub_method)) {
                    throw new Exception("Invalid Request", 404);
                }

                $this->route['method'] = $sub_method;
                $this->args = array_values(array_diff($this->args, array($this->args[1])));
            }

            // Method must exist
            if (!method_exists($controller, $this->route['method'])) {
                throw new Exception('Invalid Request', 404);
            }

            // Verify Argument Count
            $reflection = new ReflectionMethod($controller, $this->route['method']);
            if ($reflection->getNumberOfRequiredParameters() > count($this->args)) {
                throw new Exception('missing required arguments', 400);
            }

            // Call controller method, pass args
            $data = array('data' => call_user_func_array(
                array($controller, $this->route['method']),
                $this->args
            ));

        } catch (Exception $e) {
            $data = array('meta' => array(
                'error'   => true,
                'message' => $e->getMessage(),
                'status'  => $e->getCode()
            ));
        }

        return array_replace_recursive($this->default_response, $data);
    }

    /**
     * Provides method access to the query array
     * with an optional default value.
     * 
     * @param   $key      string  The query string key name
     * @param   $default  mixed   A default value to return if the query key doesn't exist
     * @return  mixed  Query string value or default value
     */
    public function query($key, $default = null)
    {
        return isset($this->query[$key]) ? $this->query[$key] : $default;
    }

    /**
     * Allows for mapping of custom application status codes to valid HTTP
     * status codes (e.g. Facebook's 803 => 404).
     * 
     * @param   $code  mixed   The status code
     * @return  int  A valid HTTP status code
     */
    public function mapStatus($code)
    {
        if (array_key_exists($code, $this->app['api.http_status_codes'])) {
            $code = (int) $this->app['api.http_status_codes'][$code];
        }

        return array_key_exists($code, Response::$statusTexts) ? $code : 500;
    }
}

/* End of file Handler.php */