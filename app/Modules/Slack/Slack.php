<?php
 namespace App\Modules\Slack;

 use Illuminate\Http\Request;
 use Illuminate\Http\Response;
 use Maknz\Slack\Client;
 use Exception;

 class Slack
 {
     protected static $client = null;

     public static function send(Exception $exception, Request $request, Response $response)
     {
         $service_name = $request->header('x-service');
         $msg = self::createMSG([
             'service_name' => $service_name,
             'uri' => $request->getUri(),
             'method' => $request->method(),
             'status_code' => $response->getStatusCode(),
             'exception_class' => get_class($exception),
             'message' => $exception->getMessage(),
             'file' => $exception->getFile(),
             'line' => $exception->getLine(),
             'previous' => $exception->getPrevious(),
             'request_params' => json_encode($request->all()),
             'headers' => json_encode($request->headers->all())
         ]);
         $client = self::createClient();

         if ($client->send($msg)) {
             dd('sent');
         }
     }

     public static function createClient()
     {
         if (self::$client == null) {
             $settings = [
                 'username' => env('SLACK_USERNAME'),
                 'channel' => env('SLACK_CHANNEL'),
                 'link_names' => true
             ];
             $client = new Client(env('SLACK_HOOK'), $settings);

             return $client;
         }

         return self::$client;
     }

     public static function createMSG(array $msg)
     {
         $message = "****  "
             .$msg['service_name']
             ."  ****\n";

         foreach ($msg as $key => $value) {
             $message .= "$key: ";
             if (is_string($value) || is_numeric($value) || is_bool($value)) {
                 $message .= $value;
             } elseif (is_array($value)) {
                 $message .= json_encode($value);
             }
             $message .= " \n";
         }
         return $message;
     }
 }
