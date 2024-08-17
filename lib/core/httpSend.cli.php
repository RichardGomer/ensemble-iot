<?php

/**
 * Helper script for sending HTTP requests
 * We use this rather than blocking the main process
 */

require __DIR__.'/../../vendor/autoload.php';


use GuzzleHttp\Client;
use GuzzleHttp\Exception\RequestException;
use GuzzleHttp\RequestOptions;

$client = new Client();
$promises = [];

echo "Interactive HTTP client started. Enter commands in the format: <HTTP_METHOD> <URL> [<JSON_FORM_PARAMETERS>]\n";
echo "Type 'exit' to quit.\n";

// Make STDIN non-blocking
stream_set_blocking(STDIN, false);

$requestCounter = 0;

while (true) {
    // Attempt to read a line from STDIN
    $input = trim(fgets(STDIN));
    
    // Check if the user has typed 'exit' to quit the program
    if (strtolower($input) === 'exit') {
        break;
    }

    // If there is input, process it
    if ($input !== '') {
        // Parse the input
        $args = explode(' ', $input, 3);

        if (count($args) < 2) {
            echo "Invalid command. Usage: <HTTP_METHOD> <URL> [<JSON_FORM_PARAMETERS>]\n";
            continue;
        }

        $method = strtoupper($args[0]);
        $url = $args[1];
        $formParams = [];

        if (isset($args[2])) {
            $formParams = json_decode($args[2], true);
            if (json_last_error() !== JSON_ERROR_NONE) {
                echo "Invalid JSON for form parameters.\n";
                continue;
            }
        }

        $requestCounter++;
        $requestId = "request-$requestCounter";

        try {
            $options = [];
            if (in_array($method, ['POST', 'PUT', 'PATCH'])) {
                $options['form_params'] = $formParams;
            }

            // POST-JSON sends json as the body with a application/json content type
            if($method === 'POST-JSON') {
                $method = 'POST';
                $options[RequestOptions::JSON] = $formParams;
            }

            // Store the promise with the request ID
            $promises[$requestId] = $client->requestAsync($method, $url, $options);
            echo "Request $requestId added to queue.\n";

        } catch (RequestException $e) {
            echo "Request $requestId failed to add: " . $e->getMessage() . "\n";
        }
    }

    // Run the task queue so that requests can make progress
    $queue = GuzzleHttp\Promise\Utils::queue();
    $queue->run();

    // Check the status of each promise
    foreach ($promises as $requestId => $promise) {
        $state = $promise->getState();
        if ($state === 'fulfilled' || $state === 'rejected') {
            try {
                $response = $promise->wait();
                echo "Request ID: $requestId\n";
                echo "Response Status: " . $response->getStatusCode() . "\n";
                echo "Response Body:\n";
                echo $response->getBody()->getContents() . "\n";
            } catch (RequestException $e) {
                echo "Request ID: $requestId\n";
                echo "Request failed: " . $e->getMessage() . "\n";
                if ($e->hasResponse()) {
                    echo "Response:\n";
                    echo $e->getResponse()->getBody()->getContents() . "\n";
                }
            }
            echo "------\n";
            unset($promises[$requestId]); // Remove the completed or failed promise
        } else {
            echo ".";
        }
    }

    // Short delay to prevent high CPU usage
    usleep(100000); // 100 milliseconds
}

echo "Exiting the interactive client.\n";