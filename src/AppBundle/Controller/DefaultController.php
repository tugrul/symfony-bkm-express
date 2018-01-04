<?php

namespace AppBundle\Controller;

use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\JsonResponse;
use AppBundle\Payment\BkmExpress;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Psr\Log\LoggerInterface;

class DefaultController extends Controller
{
   
    protected $bkmExpress;
    protected $logger;
    
    public function __construct(BkmExpress $bkmExpress, LoggerInterface $logger)
    {
        $this->bkmExpress = $bkmExpress;
        $this->logger = $logger;
    }
    
    /**
     * @Route("/", name="homepage")
     */
    public function indexAction(Request $request)
    {
        $this->logger->info('index action');
        
        $tutar = $request->query->get('tutar', 1);

        $tutar = str_replace(',', '.');
        $tutar = number_format($tutar, 2, ',', '');

        $payload = [
            'amount' =>  $tutar,
            'nonceUrl' => $this->generateUrl('bkm-nonce', [], UrlGeneratorInterface::ABSOLUTE_URL),
            'installmentUrl' => $this->generateUrl('bkm-installment', [], UrlGeneratorInterface::ABSOLUTE_URL),
//            'nonceUrl' => 'https://tugrul.info/bkm/nonce?XDEBUG_SESSION_START=tugrul',
//            'installmentUrl' => 'https://tugrul.info/bkm/installment?XDEBUG_SESSION_START=tugrul',
            'orderId' => uniqid('eu_', true),
            //'address' => '',
            //'tckn' => '',
            //'msisdn' => '',
            //'campaignCode' => '',
            //'agreementUrl' => ''
        ];

        $ticket = $this->bkmExpress->getTicket($payload);
        
        $this->logger->info('index action return');
        
        return $this->render('default/index.html.twig', [
            'base_dir' => realpath($this->getParameter('kernel.project_dir')) . DIRECTORY_SEPARATOR,
            'ticket' => $ticket->data,
            'endpoint' => $this->bkmExpress->getJsUrl()
        ]);
    }
    
    /**
     * 
     * @param Request $request
     * @Route("/nonce", name="bkm-nonce")
     */
    public function nonceAction(Request $request)
    {
        $this->logger->info('nonce action');
        
        $payload = json_decode($request->getContent());
        
        if (!$this->bkmExpress->getEncryption()->verifyData($payload->id, $payload->signature)) {
            
            $this->logger->info('signature verification failed');
            
            $response = new JsonResponse([
                'data' => null,
                'status' => 'fail', 
                'error' => 'Signature verification failed.'
            ]);
            
            $response->setStatusCode(403, 'Forbidden');
            
            return $response;
        }
        
        $response = new JsonResponse([
            'result' => 'ok',
            'data' => 'ok'
        ]);
        
        $response->setStatusCode(202, 'Accepted');
        
        $this->logger->info('nonce action return');
        
        return $response;
    }

    /**
     * 
     * @param Request $request
     * @Route("/installment", name="bkm-installment")
     */
    public function installmentAction(Request $request)
    {
        $this->logger->info('installment action');

        $payload = json_decode($request->getContent());

        $this->logger->info('installment request body of bkm:', json_decode(json_encode($payload), true));
        
        if (!$this->bkmExpress->getEncryption()->verifyData($payload->ticketId, $payload->signature)) {

            $this->logger->info('signature verification failed');
            
            return new JsonResponse([
                'data' => null,
                'status' => 'fail', 
                'error' => 'Signature verification failed.'
            ]);
        }
        
        $installments = [];
        
        foreach ($payload->bin as $bin) {
            list($bin, $bankCode) = explode('@', $bin);
            
            
            $installmentRate = 1;
        
            $installmentAmount = floatval(str_replace(',', '.', $payload->totalAmount)) * $installmentRate;
            $installmentAmount = number_format($installmentAmount, 2, ',', '');

            $installments[$bin][] = [
                'numberOfInstallment' => 1,
                'installmentAmount' => $installmentAmount,
                'totalAmount' => $payload->totalAmount,
                'vposConfig' => $this->bkmExpress->getPosAccount($bankCode, true)
            ];

        }
        
        if (count($installments) === 0) {
            return new JsonResponse([
                'data' => null,
                'status' => 'fail',
                'error' => 'Can not get Installments'
            ]);
        }
        
        $this->logger->info('installment action return');
        
        return new JsonResponse([
            'data' => ['installments' => $installments],
            'status' => 'ok',
            'error' => ''
        ]);
    }
    
}
