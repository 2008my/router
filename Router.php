<?php
namespace GoodText\Router;
//路由器类
class Router
{
    // @var array 前置路由模式及其处理
    private $beforeRoutes = [];
    // @var array 后置路由模式及其处理
    private $afterRoutes = [];
    // @var object|callable 无匹配路由时执行函数
    protected $notFoundCallback;
    // @var string 当前基本路径，用于（子）路径安装
    private $baseRoute = '';
    // @var string 需要处理的请求方法
    private $requestedMethod = '';
    // @var string 服务器基本路径
    private $serverBasePath;
    // @var string 默认控制器命名空间
    private $namespace = '';
    // @var string 控制器路径
    private $controllerPath = '';

    /**
     * 存储访问时要执行的前置路由和处理函数.
     *
     * @param string          $methods 允许用|分隔
     * @param string          $pattern 路由模式，如/about/system
     * @param object|callable $fn      要执行的处理函数
     */
    public function before($methods, $pattern, $fn)
    {
        $pattern = $this->baseRoute . '/' . trim($pattern, '/');
        $pattern = $this->baseRoute ? rtrim($pattern, '/') : $pattern;
        //分割request methods
        $arraymethods = explode('|', $methods);
        //如果有多个则foreach
        if (count($arraymethods) === 1) {
            $this->beforeRoutes[$arraymethods[0]][] = [
                'pattern' => $pattern,
                'fn' => $fn,
            ];
        } else {
            foreach ($arraymethods as $method) {
                $this->beforeRoutes[$method][] = [
                    'pattern' => $pattern,
                    'fn' => $fn,
                ];
            }
        }
    }

    /**
     * 存储访问时要执行的路由和处理函数.
     *
     * @param string          $methods 允许用|分隔
     * @param string          $pattern 路由模式，如/about/system
     * @param object|callable $fn      要执行的处理函数
     */
    public function match($methods, $pattern, $fn)
    {
        $pattern = $this->baseRoute . '/' . trim($pattern, '/');
        $pattern = $this->baseRoute ? rtrim($pattern, '/') : $pattern;
        //分割request methods
        $arraymethods = explode('|', $methods);
        //如果有多个则foreach
        if (count($arraymethods) === 1) {
            $this->afterRoutes[$arraymethods[0]][] = [
                'pattern' => $pattern,
                'fn' => $fn,
            ];
        } else {
            foreach (explode('|', $methods) as $method) {
                $this->afterRoutes[$method][] = [
                    'pattern' => $pattern,
                    'fn' => $fn,
                ];
            }
        }
    }

    /**
     *访问路由简写
     */

    /**
     *
     * @param string          $pattern 路由模式，如/about/system
     * @param object|callable $fn      要执行的处理函数
     */
    public function all($pattern, $fn)
    {
        $this->match('GET|POST|PUT|DELETE|OPTIONS|PATCH|HEAD', $pattern, $fn);
    }

    /**
     * @param string          $pattern 路由模式
     * @param object|callable $fn      要执行的处理函数
     */
    public function get($pattern, $fn)
    {
        $this->match('GET', $pattern, $fn);
    }

    /**
     * @param string          $pattern 路由模式
     * @param object|callable $fn      要执行的处理函数
     */
    public function post($pattern, $fn)
    {
        $this->match('POST', $pattern, $fn);
    }

    /**
     * @param string          $pattern 路由模式
     * @param object|callable $fn      要执行的处理函数
     */
    public function patch($pattern, $fn)
    {
        $this->match('PATCH', $pattern, $fn);
    }

    /**
     * @param string          $pattern 路由模式
     * @param object|callable $fn      要执行的处理函数
     */
    public function delete($pattern, $fn)
    {
        $this->match('DELETE', $pattern, $fn);
    }

    /**
     * @param string          $pattern 路由模式
     * @param object|callable $fn      要执行的处理函数
     */
    public function put($pattern, $fn)
    {
        $this->match('PUT', $pattern, $fn);
    }

    /**
     * @param string          $pattern 路由模式
     * @param object|callable $fn      要执行的处理函数
     */
    public function options($pattern, $fn)
    {
        $this->match('OPTIONS', $pattern, $fn);
    }

    /**
     * 将回调集合装载到基路由上
     *
     * @param string   $baseRoute 回调的路由子模式
     * @param callable $fn        回调方法
     */
    public function mount($baseRoute, $fn)
    {
        // 跟踪当前基本路径
        $curBaseRoute = $this->baseRoute;
        // 生成新基本路径
        $this->baseRoute .= $baseRoute;
        // 调用可调用的
        call_user_func($fn);
        // 恢复原始基本路由
        $this->baseRoute = $curBaseRoute;
    }

    /**
     * 获取全部 HTTP 请求头信息
     *
     * @return array  HTTP 请求头信息
     */
    public function getRequestHeaders()
    {
        $headers = [];
        // 如果getAllHeaders()可用, 则使用
        if (function_exists('getallheaders')) {
            $headers = getallheaders();
            // 如果出现错误，getAllHeaders()返回false
            if ($headers !== false) {
                return $headers;
            }
        }
        // getAllHeaders()不可用或出错,则提取
        foreach ($_SERVER as $name => $value) {
            if ((substr($name, 0, 5) == 'HTTP_') || ($name == 'CONTENT_TYPE') || ($name == 'CONTENT_LENGTH')) {
                $headers[str_replace([' ', 'Http'], ['-', 'HTTP'], ucwords(strtolower(str_replace('_', ' ', substr($name, 5)))))] = $value;
            }
        }
        return $headers;
    }

    /**
     * 获取使用的请求方法，同时考虑重写。
     *
     * @return string 要处理的请求方法
     */
    public function getRequestMethod()
    {
        // 使用$_SERVER中的方法
        $method = $_SERVER['REQUEST_METHOD'];
        // 如果是head请求，则根据http规范重写它以获取并阻止任何输出
        // @url http://www.w3.org/Protocols/rfc2616/rfc2616-sec9.html#sec9.4
        if ($_SERVER['REQUEST_METHOD'] == 'HEAD') {
            ob_start();
            $method = 'GET';
        }
        // 如果是post请求，检查方法重写头
        elseif ($_SERVER['REQUEST_METHOD'] == 'POST') {
            $headers = $this->getRequestHeaders();
            if (isset($headers['X-HTTP-Method-Override']) && in_array($headers['X-HTTP-Method-Override'], ['PUT', 'DELETE', 'PATCH'])) {
                $method = $headers['X-HTTP-Method-Override'];
            }
        }
        return $method;
    }

    /**
     * 为可调用方法设置默认命名空间。
     *
     * @param string $namespace 命名空间
     */
    public function setNamespace($namespace)
    {
        if (is_string($namespace)) {
            $this->namespace = $namespace;
        }
    }

    /**
     * 获取命名空间。
     *
     * @return string 命名空间（如果存在）
     */
    public function getNamespace()
    {
        return $this->namespace;
    }

    /**
     * 执行在中间件和路由之前定义的router:loop all，如果找到匹配，则执行处理函数。
     *
     * @param object|callable $callback 处理匹配路由后要执行的函数(= after router middleware)
     *
     * @return bool
     */
    public function run($callback = null)
    {
        // 定义需要处理的方法
        $this->requestedMethod = $this->getRequestMethod();
        // 前置路由模式处理
        if (isset($this->beforeRoutes[$this->requestedMethod])) {
            $this->handle($this->beforeRoutes[$this->requestedMethod]);
        }
        // 处理所有路由
        $numHandled = 0;
        if (isset($this->afterRoutes[$this->requestedMethod])) {
            $numHandled = $this->handle($this->afterRoutes[$this->requestedMethod], true);
        }
        // 如果没有处理路由，则触发404（如果有）
        if ($numHandled === 0) {
            if ($this->notFoundCallback) {
                $this->invoke($this->notFoundCallback);
            } else {
                header($_SERVER['SERVER_PROTOCOL'] . ' 404 Not Found');
                echo "<h1 style='width: 60%; margin: 5% auto;'>:( 404<br>Page Not Found <code style='font-weight: normal;'></code></h1>";
            }
        } // 如果处理了路由，则执行完成 callback（如果有）
        else {
            if ($callback && is_callable($callback)) {
                $callback();
            }
        }
        // 如果最初是一个HEAD请求，则清空输出缓冲区
        if ($_SERVER['REQUEST_METHOD'] == 'HEAD') {
            ob_end_clean();
        }
        // 如果处理了路由，则返回true，否则返回false
        return $numHandled !== 0;
    }

    /**
     * 设置404处理功能.
     *
     * @param object|callable $fn 要执行的函数
     */
    public function set404($fn)
    {
        $this->notFoundCallback = $fn;
    }

    /**
     * 处理一组路由：如果找到匹配，则执行相关的处理函数。
     *
     * @param array $routes       路由模式的集合及其处理功能
     * @param bool  $quitAfterRun 匹配一个路由后是否需要退出handle函数？
     *
     * @return int 处理的路由数
     */
    private function handle($routes, $quitAfterRun = false)
    {
        // 记录处理的路由数量
        $numHandled = 0;
        // 当前页面URL
        $uri = $this->getCurrentUri();
        // 循环所有路由
        foreach ($routes as $route) {
            // 将所有匹配的 {} 替换为字符串
            $route['pattern'] = preg_replace('/\/{(.*?)}/', '/(.*?)', $route['pattern']);
            // have a match!
            if (preg_match_all('#^' . $route['pattern'] . '$#', $uri, $matches, PREG_OFFSET_CAPTURE)) {

                // 重新处理匹配项以仅包含匹配项，而不包含原始字符串
                $matches = array_slice($matches, 1);
                /*#
            	switch(count($matches)){
            		case 1:
            		$params = array($matches[0][0][0]);
            		break;
            		case 2:
            		$params = array($matches[0][0][0],$matches[1][0][0]);
            		break;
            		case 3:
            		$params = array($matches[0][0][0],$matches[1][0][0],$matches[2][0][0]);
            		break;
            		case 4:
            		$params = array($matches[0][0][0],$matches[1][0][0],$matches[2][0][0],$matches[3][0][0]);
            		break;
            		default:
            		break;
            	}
            	*/
                // 提取匹配的url参数（并且仅提取参数）
                $params = array_map(function ($match, $index) use ($matches) {
                    // PREG_OFFSET_CAPTURE：如果设定本标记，对每个出现的匹配结果也同时返回其附属的字符串偏移量（PREG_OFFSET_CAPTURE)
                    if (isset($matches[$index + 1]) && isset($matches[$index + 1][0]) && is_array($matches[$index + 1][0])) {
                        return trim(substr($match[0][0], 0, $matches[$index + 1][0][1] - $match[0][1]), '/');
                    } // We have no following parameters: 返回整个批次
                    return isset($match[0][0]) ? trim($match[0][0], '/') : null;
                }, $matches, array_keys($matches));

                // 如果所需的输入是可调用的，则使用url参数调用handling函数
                $this->invoke($route['fn'], $params);
                ++$numHandled;
                // 退出
                if ($quitAfterRun) {
                    break;
                }
            }
        }
        // 返回已处理的路由数
        return $numHandled;
    }

    private function invoke($fn, $params = [])
    {
        //正则匹配的url，增加自动加载
        if ($fn == '@auto') {
            // 分解路径
            if (isset($params[0]) && isset($params[1])) {
                $controller = 'My' . $params[0] . 'Controller';
                $method = 'My' . $params[1] . 'Action';
                array_splice($params, 0, 2);
                // 是否为字符串
                if (!empty($controller) && !empty($method)) {
                	// 如果已设置命名空间，则调整控制器类
                	if ($this->getNamespace() !== '') {
                		$controller = $this->getNamespace() . '\\' . $controller;
                	}
                        if ($this->getControllerPath() !== '') {
                            $fileName = '.' . $this->getControllerPath() . str_replace('\\', '/', $controller) . '.php';//替换符号
                        } else {
                            $fileName = '.' . str_replace('\\', '/', $controller) . '.php';//替换符号
                        }
                        //$fileName = $controller . '.php';
                        if (is_file($fileName)) {
                        //判断文件是否存在
                            require $fileName;
                        } else {
                            //echo $fileName . 'is not exist';
                            echo '文件不存在';
                            return;
                        }

                        //require_once dirname(FILE).DIRECTORY_SEPARATOR.'inc/options.php';
                        //return method_exists($controller, $method) && is_callable([$controller, $method])
                        //? call_user_func_array([$controller,$method], $params)
                        //: false;
                        if (class_exists($controller, false)) {
                            $controller_object = new $controller;
                        }else{
                            //echo $controller.'控制器不存在';
                            echo '控制器不存在';
                            return;
                        }

                        if(method_exists($controller_object, $method)) {
                            if ($params) {
                                $controller_object->$method($params);
                            } else {
                                $controller_object->$method();
                            }
                        }else{
                            //echo $method.'调用的方法不存在';
                            echo '调用的方法不存在';
                            return;
                        }
                }
            }else{
                echo '参数未定义';
            }
        }

        elseif (is_callable($fn)) {
            call_user_func_array($fn, $params);
        }

        // If not, 检查特殊参数是否存在
        elseif (stripos($fn, '@') !== false) {
            // 分解路径
            list($controller, $method) = explode('@', $fn);
            // 如果已设置命名空间，则调整控制器类
            if ($this->getNamespace() !== '') {
                $controller = $this->getNamespace() . '\\' . $controller;
            }
            // 检查类是否存在
            if (class_exists($controller)) {
                // 首先检查是否是静态方法，直接尝试调用它。
                // 如果不是有效的静态方法，我们将尝试作为普通方法调用。
                if (call_user_func_array([new $controller(), $method], $params) === false) {
                    // 尝试将该方法作为非静态方法调用 (如果什么都不做， 只是避免报错)
                    if (forward_static_call_array([$controller, $method], $params) === false);
                }
            }
        }
    }

    /**
     * 定义当前相对uri。
     *
     * @return string
     */
    public function getCurrentUri()
    {
        // 获取当前请求uri并从中删除重写基路径(= =允许在子文件夹中运行路由器)
        $uri = substr(rawurldecode($_SERVER['REQUEST_URI']), strlen($this->getBasePath()));

        // 不要在url上考虑查询参数
        if (strstr($uri, '?')) {
            $uri = substr($uri, 0, strpos($uri, '?'));
        }

        // 删除末位斜杠在开始处强制+斜杠
        return '/' . trim($uri, '/');
    }

    /**
     * 返回服务器基本路径，如果未定义则定义
     * @return string
     */
    public function getBasePath()
    {
        // 检查是否定义了服务器基本路径（如果未定义）。
        if ($this->serverBasePath === null) {
            $this->serverBasePath = implode('/', array_slice(explode('/', $_SERVER['SCRIPT_NAME']), 0, -1)) . '/';
        }

        return $this->serverBasePath;
    }

    /**
     * 设置服务器基本路径。当脚本路径与URL不同时使用。
     */
    public function setBasePath($serverBasePath)
    {
        $this->serverBasePath = $serverBasePath;
    }


    /**
     * 设置控制器路径
     */
    public function setControllerPath($controllerpath)
    {
        $this->controllerPath = $controllerpath;
    }


    /**
     * 获取控制器路径
     */
    public function getControllerPath()
    {
        return $this->controllerPath;
    }


}

//路由器模板类
class TemplateView
{
    private $viewPath = 'views/';
    /**
     * 设置视图路径
     */
    public function setViewPath($viewPath)
    {
        $this->viewPath = $viewPath;
    }
    /**
    * Render view.
    * @param  String $template Path to view
    * @param  Array  templatedata     
    * @return String Rendered view
    */
    public function render($template, $templatedata = []) {
        if(is_array($templatedata)) {
            extract($templatedata);
        }else {
            return false;
        }
        $templatePath = $this->viewPath.trim($template) . 'View.php';
        //ob_start();
        //is_readable($template)结果会被缓存,用 clearstatcache() 清除
        if (is_readable($templatePath)) {
            include $templatePath;
        } else {
            //echo $templatePath;
            echo '模板文件不存在';
            return false;
        }
        //echo ob_get_length();
        //$output = ob_get_contents(); //得到缓冲区的内容并且赋值给$info
        //ob_get_clean(); 
        //return $output;
    }
}