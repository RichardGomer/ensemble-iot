<?php

/**
* API-related functions
*
* (C) 2014 Richard Gomer
*/

namespace QuickAPI;


class API
{
    private $dataSource;
    private $ops;
    private $resultHandlers = array();
    
    public function __construct($argSource, $opArgName)
    {
        $this->dataSource = $argSource;
        $this->opArgName = $opArgName;
    }
    
    /**
     * Look for an operation request in the data source and handle it if possible
     */
    public function handle()
    {
        $opname = $this->getArg($this->opArgName);
        
        //var_dump($opname, $this->opArgName, $this->dataSource);
        
        $handler = $this->selectOperation($opname, $this->dataSource, $args);

        if($handler instanceof APIHandler)
        {
            if($this->checkAuth($handler))
            {
                $this->runOperation($handler, $args);
            }
        }
        else
        {
            $this->error("Unknown operation $opname");
        }
        
    }
    
    /**
     * Given an operation name and set of arguments, find a handler that fits or return false
     */
    protected function selectOperation($opname, $args, &$oargs)
    {
        if($opname === false)
            $opname = '_FALSE';
        
        //var_Dump($opname);
        //var_Dump($this->ops);
        
        if(!\array_key_exists($opname, $this->ops))
            return false;
        
        foreach($this->ops[$opname] as $op)
        {
            if(($oargs = $this->getArgs($op['args'])) !== false)
            {
                return $op['handler'];
            }
        }
        
        return false;
    }
    
    /**
     * Run an operation
     */
    protected function runOperation(APIHandler $handler, $args)
    {
        try
        {
            $res = $handler->handleCall($args);
            
            $this->result($res);
        }
        catch(\Exception $ex)
        {
            $this->error($ex->getMessage());
        }
    }

    
    /**
     * Add an operation.  $name is the value of the operation argument that will trigger 
     * the operation, and $args is a list of arguments that must be provided
     * 
     * Operations can be polymorphic - Different argument signatures can be mapped to different
     * handlers for the same operation name.
     */
    public function addOperation($name, $args, APIHandler $handler)
    {
        if($name === false)
            $name = '_FALSE';
        
        $this->ops[$name][] = array('args'=>$args, 'handler'=>$handler);
    }
    
    
    /**
     * Add an auth handler
     * If an auth handler has been added, then the user and pass arguments from the request are
     * passed to it for authentication.  If they are not set, or the auth handler returns false,
     * then the request is rejected with an error.
     */
    private $auth = false;
    public function addAuth(APIAuth $handler, $fields=array('user', 'pass'))
    {
        $this->auth = $handler;
        $this->authargs = $fields;
    }
    
    protected function checkAuth(APIHandler $handler)
    {
        if(!$this->auth instanceof APIAuth)
            return true;
        
        if(!$args = $this->getArgs($this->authargs))
        {
            $this->error("Authentication is required (".implode(', ', $this->authargs).")");
            return false;
        }
        
        $authed = $this->auth->checkCredentials($args, $handler);
        
        if(!$authed)
        {
            $this->error("Authentication failed for {$args['user']}");
		return false;
        }
        
        return true;
    }
    
    
    /**
     * Prepare a response
     * 
     * Basically, pass objects to handlers (registered by class) that can prepare
     * that object to be JSON'ed, by eg convering it to an associative array
     * 
     * On arrays (or objects that are converted into arrays) this is recursive
     * 
     * Array keys are converted to lowercase for consistency
     */
    public function prepResult($result, $depth=1)
    {
        //var_dump($result);
        
        if(is_object($result))
        {
            foreach($this->resultHandlers as $class=>$handler)
            {
                if($result instanceof $class)
                {
                    $result = $this->prepResult($handler->prepareResult($result), $depth + 1);
                    break;
                }
            }
        }
        // Handle arrays (could be the result of the object conversion!)
        elseif(is_array($result))
        {
            foreach($result as $k=>$val)
            {
                unset($result[$k]);
                $result[strtolower($k)] = $this->prepResult($val, $depth+1);
            }
        }
        
        
        return $result;
    }
    
    /**
     * Register a result converter (see above)
     */
    public function registerResultHandler($className, APIResultHandler $handler)
    {
        $this->resultHandlers[strtolower($className)] = $handler;
    }

    /**
     * Look for an argument in $_GET and $_POST or return false
     */
    public function getArg($name)
    {
        $arg = \array_key_exists($name, $this->dataSource) ? $this->dataSource[$name] : false;

        $res = @json_decode($arg, true, 10);
        
        //var_Dump($name);
        //var_dump($res);
        
        if($res === NULL)
            return $arg;
        else
            return $res;
    }

    /**
     * Get an argument, or trigger an error that explains it is required
     */
    public function requireArg($name)
    {
        $arg = $this->getArg($name);

        if($arg === false)
        {
            $this->error('Required argument '.$name.' was not provided.');
            return false;
        }

        return $arg;
    }


    /**
     * Look for a number of arguments (specified as an array) - If some are not found, return false
     */
    public function getArgs($names)
    {
        $args = array();
        
        foreach($names as $name)
        {
            $args[$name] = $this->getArg($name);

            if($args[$name] === false)
                return false;
        }

        return $args;
    }

    /**
     * Get a set of arguments, or trigger an error that lists them and exit
     */
    public function requireArgs($names)
    {
        $args = getArgs($names);

        if($args === false)
        {
            $this->error('Required arguments were not provided. (One or more of: '.implode(', ', $names).')');
        }

        return $args;
    }
    
    
    /**
     * Return an error
     */
    public function error($message, $flags=array())
    {
        $this->printResult(\array_merge(array('success'=>false, 'error'=>$message), $flags));
    }

    /**
     * Return a success message
     *
     * If a redirect was specified by the client, then redirect them there
     */
    public function result($result=null)
    {
        if(($r = $this->getArg('redirect')) !== false)
            \header('Location: '.$r);

        $this->printResult(array('success'=>true, 'result'=>$this->prepResult($result)));
    }
    
    protected function printResult($array)
    {
        if(($fn = $this->getArg('callback')) !== false)
        {
            header("Content-type: text/javascript");
            echo "$fn(".json_encode($array).");";
        }
        else
        {
            header("Content-type: application/json");
            echo \json_encode($array);
        }
    }
}

interface APIHandler
{
    /**
     * Either return a return value or throw an exception
     */
    function handleCall($args);
}

interface APIResultHandler
{
    /**
     * Convert the given object into something that can be returned by the API
     * Could be another object, if that object is JSON'able!
     */
    public function prepareResult($res);
}

interface APIAuth
{
    /**
     * Check if the given username/pasword ID/key whatever/whatever are valid for access to the API
     * The requested handler is also passed, so tiered access can be implemented if desired
     */
    public function checkCredentials($args, APIHandler $handler);
}

/**
 * Print the JS for the client
 */
function getAPIClient()
{
    $fp = \fopen(__FILE__, 'r');
    \fseek($fp, __COMPILER_HALT_OFFSET__);
    $js = \stream_get_contents($fp);
    
    return $js;
}

if(array_key_exists('apiGetClient', $_GET))
{
    \header('Content-type: text/javascript');
    
    echo getAPIClient();
}

/**
* The rest of this file is a basic javascript client for the API
* The API library will return it (and then exit) if 'apiGetClient' is set in GET when it's loaded
*
* *** Requires jQuery ***
*/
__halt_compiler();

function QuickAPIClient(endpoint)
{
    var self = this;
    
    self.username = false;
    self.password = false;
    
    // Set the username/password for HTTP Authentication
    self.setAuth = function(username, password)
    {
        self.username = username;
        self.password = password;
    }
    
    /**
    * Make a request to the API
    *
    * cb_success: function(data, status, jqXHR) callback if opreation succeeds
    * cb_error (optional): function(Data, status, jqXHR) callback if API returns an error
    * cb_fail (optional, as above): Callback if something goes wrong with the request
    */
    self.request = function(args, cb_success, cb_fail, cb_error)
    {
        if(typeof cb_error == 'undefined')
            cb_error = self.cb_error;
        
        if(typeof cb_fail == 'undefined')
            cb_fail = self.cb_fail;
        
        // Parse the JSON from the API and determine if the operation was successful
        var cb = function(data, status, xhr){
            if(typeof data.success == 'undefined')
            {
                cb_error(data, status, xhr);
            }
            else if(data.success)
            {
                cb_success(data, status, xhr);
            }
            else
            {
                cb_fail(data, status, xhr);
            }
        };
        
        if(self.username !== false && self.password !== false)
        {
            args.user = self.username;
            args.pass = self.password;
        }
        
        var r = $.post(endpoint, args, cb, 'json');
        r.error(cb_error);
    }
    
    
    /**
     * Default callbacks for errors / failures
     */
    // The other end "successfully" reported a problem with the request
    self.cb_fail = function(result)
    {
        alert('RPC was unsuccessful ' + result.message);
    }
    
    // Unexpected errors
    self.cb_error = function(status)
    {
        alert('Error during RPC (' + status + ')');
    }
}

