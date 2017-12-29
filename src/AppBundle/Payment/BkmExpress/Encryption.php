<?php


namespace AppBundle\Payment\BkmExpress;

use AppBundle\Payment\BkmExpress\Exception\Encryption as EncryptionException;

class Encryption
{
    protected $publicRemoteKey;
    protected $privateLocalKey;
    
    public function __construct($publicRemoteKey, $privateLocalKey)
    {
        $this->publicRemoteKey = $publicRemoteKey;
        $this->privateLocalKey = $privateLocalKey;
    }

    protected function getPublicKey()
    {
        $publicKey = openssl_get_publickey('file://' . $this->publicRemoteKey);
        
        if ($publicKey === false) {
            throw new EncryptionException('public key not opened');
        }
        
        return $publicKey;
    }
    
    public function signData($data)
    {
        $privateKey = openssl_get_privatekey('file://' . $this->privateLocalKey);

        if ($privateKey === false) {
            throw new EncryptionException('private key not opened');
        }
        
        try {
            if (!openssl_sign($data, $signature, $privateKey, 'sha256WithRSAEncryption')) {
                throw new EncryptionException(openssl_error_string());
            }
        } finally {
            openssl_free_key($privateKey);
        }
        
        return base64_encode($signature);
    }

    public function verifyData($data, $signature)
    {
        $publicKey = $this->getPublicKey();
        
        $result = openssl_verify($data, base64_decode($signature), $publicKey, "sha256WithRSAEncryption");

        openssl_free_key($publicKey);
        
        if ($result === -1) {
            throw new EncryptionException(openssl_error_string());
        }

        return $result === 1;
    }
    
    public function encrypt($data, $chunklen = 245)
    {   
        $publicKey = $this->getPublicKey();
        
        $parts = [];
        $count = ceil(strlen($data) / $chunklen);

        try {
        
            for ($i = 0; $i < $count; $i++) {

                $part = substr($data, $i * $chunklen, $chunklen);

                if (openssl_public_encrypt($part, $encrypted, $publicKey)) {
                    $parts[] = base64_encode($encrypted);
                } else {
                    throw new EncryptionException(openssl_error_string());
                }
            }

        } finally {
            openssl_free_key($publicKey);
        }

        return implode('|:*:|', $parts);
    }
}
