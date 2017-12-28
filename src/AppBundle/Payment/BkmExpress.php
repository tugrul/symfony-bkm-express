<?php


namespace AppBundle\Payment;

use Psr\Log\LoggerInterface;

class BkmExpress
{
    protected static $environments = [
        'dev' => [
            'api' => 'https://bex-api.finartz.com/v1/',
            'js'  => 'https://bex-js.finartz.com/v1/javascripts/bex.js'
        ],
        'local' => [
            'api' => 'http://api.bex.dev/v1/',
            'js'  => 'http://js.bex.dev/javascripts/bex.js'
        ],
        'sandbox' => [
            'api' => 'https://test-api.bkmexpress.com.tr/v1/',
            'js'  => 'https://test-js.bkmexpress.com.tr/v1/javascripts/bex.js'
        ],
        'preprod' => [
            'api' => 'https://preprod-api.bkmexpress.com.tr/v1/',
            'js'  => 'https://preprod-js.bkmexpress.com.tr/v1/javascripts/bex.js'
        ],
        'production' => [
            'api' => 'https://api.bkmexpress.com.tr/v1/',
            'js'  => 'https://js.bkmexpress.com.tr/v1/javascripts/bex.js'
        ]
    ];
    
    protected $merchantId;
    protected $environment;
    protected $certificates;
    protected $encryption;
    protected $logger;

    public function __construct(string $merchantId, string $environment, array $certificates, LoggerInterface $logger)
    {
        $this->merchantId = $merchantId;
        $this->environment = $environment;
        $this->certificates = $certificates;
        $this->logger = $logger;
        
        $this->encryption = new BkmExpress\Encryption($certificates['remote']['public'], 
                $certificates['local']['private']);
    }
    
    public function getMerchantId()
    {
        return $this->merchantId;
    }

    public function getEnvironment()
    {
        return $this->environment;
    }

    public function getEncryption()
    {
        return $this->encryption;
    }

    public function setMerchantId($merchantId)
    {
        $this->merchantId = $merchantId;
        return $this;
    }

    public function setEnvironment($environment)
    {
        $this->environment = $environment;
        return $this;
    }

    public function setEncryption($encryption)
    {
        $this->encryption = $encryption;
        return $this;
    }

    public function login()
    {
        $this->logger->info('login operation started');
        
        $loginUrl = $this->getApiEndpoint('merchant/login');
        $merchantId = $this->getMerchantId();
        
        $this->logger->info('login url: '. $loginUrl);
        
        $payload = [
            'id' => $merchantId, 
            'signature' => $this->getEncryption()->signData($merchantId)
        ];
        
        $this->logger->info('login payload:', $payload);
        
        $headers = [
            'Content-Type' => 'application/json'
        ];
        
        $this->logger->info('login headers:', $headers);
        
        $response = $this->postData($loginUrl, $payload, $headers);

        $this->logger->info('login operation ended');
        
        return $response;
    }
    
    public function getTicket($payload)
    {
        $login = $this->login();
        
        $this->logger->info('ticket operation started');
        
        $ticketUrl = $this->getApiEndpoint('merchant/' . $login->data->path . '/ticket?type=payment');

        $this->logger->info('ticket url:' . $ticketUrl);
        
        $headers = [
            'Content-Type' => 'application/json',
            'Bex-Connection' => $login->data->token
        ];
        
        $this->logger->info('ticket headers:', $headers);
        
        $response = $this->postData($ticketUrl, $payload, $headers);
        
        $this->logger->info('ticket operation ended');
        
        return $response;
    }
    
    public function sendNonce($data, $result, $message = null)
    {
        $login = $this->login();
        
        $this->logger->info('send nonce operation started');
        
        $nonceUrl = $this->getApiEndpoint('merchant/' . $login->data->path . 
                '/ticket/' . $data->path . '/operate?name=commit');
        
        $this->logger->info('send nonce url:'. $nonceUrl);
        
        $payload = [
            'result' => $result,
            'nonce' => $data->token,
            'id' => $data->path,
            'message' => $message
        ];
        
        $this->logger->info('send nonce payload:', $payload);
        
        $headers = [
            'Content-Type' => 'application/json',
            'Bex-Connection' => $login->data->token,
            'Bex-Nonce' => $data->token
        ];
        
        $this->logger->info('send nonce headers:', $headers);

        $response = $this->postData($nonceUrl, $payload, $headers);
        
        $this->logger->info('send nonce operation ended');
        
        return $response;
    }
    
    protected function postData($url, $data, $headers)
    {
        $headersPair = [];
        
        foreach ($headers as $header => $value) {
            $headersPair[] = $header . ': ' . $value;
        }

        $context = stream_context_create([
            'http' => [
                'ignore_errors' => true,
                'method' => 'POST',
                'header' => implode("\r\n", $headersPair),
                'content' => json_encode($data)
            ]
        ]);

        return json_decode(file_get_contents($url, false, $context));   
    }
    
    public function getApiEndpoint($uri)
    {
        return self::$environments[$this->environment]['api'] . $uri;
    }

    public function getJsUrl()
    {
        return self::$environments[$this->environment]['js'];
    }
    
}
