<?php


namespace AppBundle\Payment;

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

    public function __construct(string $merchantId, string $environment, array $certificates)
    {
        $this->merchantId = $merchantId;
        $this->environment = $environment;
        $this->certificates = $certificates;
        
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
        $loginUrl = $this->getApiEndpoint('merchant/login');
        $merchantId = $this->getMerchantId();
        
        $payload = [
            'id' => $merchantId, 
            'signature' => $this->getEncryption()->signData($merchantId)
        ];
        
        $headers = [
            'Content-Type' => 'application/json'
        ];
        
        return $this->postData($loginUrl, $payload, $headers);
    }
    
    public function getTicket($payload)
    {
        $login = $this->login();
        
        $ticketUrl = $this->getApiEndpoint('merchant/' . $login->data->path . '/ticket?type=payment');

        $headers = [
            'Content-Type' => 'application/json',
            'Bex-Connection' => $login->data->token
        ];
        
        return $this->postData($ticketUrl, $payload, $headers);
    }
    
    public function sendNonce($data, $result, $message = null)
    {
        $login = $this->login();
        
        $nonceUrl = $this->getApiEndpoint('merchant/' . $login->data->path . 
                '/ticket/' . $data->path . '/operate?name=commit');
        
        $payload = [
            'result' => $result,
            'nonce' => $data->token,
            'id' => $data->path,
            'message' => $message
        ];
        
        $headers = [
            'Content-Type' => 'application/json',
            'Bex-Connection' => $login->data->token,
            'Bex-Nonce' => $data->token
        ];

        return $this->postData($nonceUrl, $payload, $headers);
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
