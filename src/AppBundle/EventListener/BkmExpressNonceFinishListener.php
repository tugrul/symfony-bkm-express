<?php

namespace AppBundle\EventListener;

use Symfony\Component\HttpKernel\Event\PostResponseEvent;
use AppBundle\Payment\BkmExpress;

class BkmExpressNonceFinishListener
{   
    protected $bkmExpress;
    
    public function __construct(BkmExpress $bkmExpress)
    {
        $this->bkmExpress = $bkmExpress;
    }
    
    public function onKernelTerminate(PostResponseEvent $event)
    {
        $request = $event->getRequest();
        
        if ($request->attributes->get('_route') !== 'bkm-nonce') {
            return;
        }
     
        $payload = json_decode($request->getContent());
        
        sleep(5);

        $nonceResponse = $this->bkmExpress->sendNonce($payload, true);
    }

}
