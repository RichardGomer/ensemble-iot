# QUAPI: Quick API Library for PHP

QUAPI (Kwappy) is a lightweight library for quickly building HTTP APIs.  It provides methods to dispatch calls to handlers based
on the value of an action parameter, helper methods to check for and retrieve GET/POST parameters, methods to send success/error
messages, conversion (via registered handler classes) or objects into JSON-serializable arrays and a javascript client to
take care of the client-side communication.

## Example

```php
<?php


include(dirname(__FILE__).'/quapi/api.lib.php');
use QuickAPI as API;

/**
 * Some action that the API performs
 */
class Action1 implements API\APIHandler, 
{
    public function handleCall($args)
    {
        // Do something

        // Return a foo
        return new Foo();
    }
}


/**
 * Convert a foo object into a JSON-serializable array
 */
class FooConverter implements API\APIResultHandler
{
    public function prepareResult($res)
    {
        // Bars will be converted into arrays by the BarConverter later on :)
        return array('name' => $res->name, 'bars' => $res->getBars());
    }
}

/**
 * Convert a bar object into a JSON-serializable array
 */
class BarConverter implements API\APIResultHandler
{
    public function prepareResult($res)
    {
        return array('barID' => $res->BarID, 'colour' => $bar->getColour());
    }
}


/**
 * Create an API that looks for arguments in $_GET/$_POST and uses the "action" argument to specify which 
 * action handler to invoke
 */
$api = new API\API(\array_merge($_GET, $_POST), 'action');


/**
 * Add an action to be called if the action parameter is "action1" or if "param1" is set
 */
$a1 = new Action1();
$api->addOperation(false, array('param1'), $fi);
$api->addOperation('action1', array('param1'), $fi);


/**
 * Register converters to turn Foo objects and Bar objects in json-serializable arrays
 */
$api->registerResultHandler('Foo', new FooConverter());
$api->registerResultHandler('Bar', new BarConverter());

// Handle the request
$api->handle();

?>
```

You can optionally register a handler module to check provider user/pass params using
```php
$api->addAuth(new AuthHandler()); // $authHandler must implement APIAuth
```

Get the javascript client either by
```php
<script>
<?php
include(dirname(__FILE__).'/quapi/api.lib.php');
use QuickAPI as API;
echo getAPIClient();
?>
</script>
```

or by passing the magic param apiGetClient to the library script itself

```
<script src="http://path/to/api.php?apiGetClient"></script>
```