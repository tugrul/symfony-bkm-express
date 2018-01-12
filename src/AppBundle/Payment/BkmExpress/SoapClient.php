<?php


namespace AppBundle\Payment\BkmExpress;

class SoapClient
{
    protected $soapClient;
    protected $bkmExpress;
    
    public function __construct($wsdl)
    {
        $this->soapClient = new \SoapClient($wsdl);
    }
    
    public function reversalWithRef($payload)
    {
        if (isset($payload['extra'])) {
            $payload['extra'] = json_encode($payload['extra']);
        }

        $payload['ts'] = date('Ymd-h:m:s');
        
        $fields = ['uniqueReferans', 'merchantId', 'requestType', 'amount', 'currency', 'posUid', 'posPwd', 'transactionToken', 'ts'];
        
        $signData = []; 
        
        
        foreach ($fields as $field) {
            $signData[] = $payload[$field] ?? '';
        }
        
        $payload['s'] = $this->bkmExpress->getEncryption()->signData(implode('', $signData));
        
        // return $this->soapClient->doReversalWithRef($payload);
        
        return $payload;
        
    }
    
    function getBkmExpress()
    {
        return $this->bkmExpress;
    }

    function setBkmExpress($bkmExpress)
    {
        $this->bkmExpress = $bkmExpress;
    }


}
