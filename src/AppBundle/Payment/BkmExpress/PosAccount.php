<?php

namespace AppBundle\Payment\BkmExpress;

class PosAccount implements \JsonSerializable
{
    protected $bankIndicator;
    
    protected $serviceUrl;
    
    protected $userId;
    
    protected $password;
    
    protected $params;

    protected $preAuth = false;
    
    public function getBankIndicator()
    {
        return $this->bankIndicator;
    }

    public function getServiceUrl()
    {
        return $this->serviceUrl;
    }

    public function getUserId()
    {
        return $this->userId;
    }

    public function getPassword()
    {
        return $this->password;
    }

    public function getParams()
    {
        return $this->params;
    }
    
    public function setBankIndicator($bankIndicator)
    {
        $this->bankIndicator = $bankIndicator;
        return $this;
    }

    public function setServiceUrl($serviceUrl)
    {
        $this->serviceUrl = $serviceUrl;
        return $this;
    }

    public function setUserId($userId)
    {
        $this->userId = $userId;
        return $this;
    }

    public function setPassword($password)
    {
        $this->password = $password;
        return $this;
    }

    public function setParams($params)
    {
        $this->params = $params;
        return $this;
    }

    public function setPreAuth($preAuth)
    {
        $this->preAuth = $preAuth;
        return $this;
    }
    
    public function getPreAuth()
    {
        return $this->preAuth;
    }
    
    public function jsonSerialize()
    {
        return [
            'vposUserId' => $this->userId ?? '',
            'vposPassword' => $this->password ?? '',
            'extra' => $this->params ?? [],
            'bankIndicator' => sprintf('%04d', $this->bankIndicator),
            'serviceUrl' => $this->serviceUrl,
            'preAuth' => $this->preAuth
        ];
    }

}
