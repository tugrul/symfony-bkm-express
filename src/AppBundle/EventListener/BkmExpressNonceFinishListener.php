<?php

namespace AppBundle\EventListener;

use Symfony\Component\HttpKernel\Event\PostResponseEvent;
use AppBundle\Payment\BkmExpress;
use Psr\Log\LoggerInterface;

class BkmExpressNonceFinishListener
{   
    protected $bkmExpress;
    protected $logger;
    
    public function __construct(BkmExpress $bkmExpress, LoggerInterface $logger)
    {
        $this->bkmExpress = $bkmExpress;
        $this->logger = $logger;
    }
    
    public function onKernelTerminate(PostResponseEvent $event)
    {
        $request = $event->getRequest();
        
        if ($request->attributes->get('_route') !== 'bkm-nonce') {
            return;
        }
     
        $payload = json_decode($request->getContent());
        
        $this->logger->info('waiting for 5 seconds');
        
        sleep(5);

        $this->logger->info('sending nonce request response');
        $this->logger->info('nonce request body of bkm:', json_decode(json_encode($payload), true));
        
        $nonceResponse = $this->bkmExpress->sendNonce($payload, true);
    }

}
