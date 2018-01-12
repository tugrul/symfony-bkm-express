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
    protected $soapClient;


    public function __construct(BkmExpress $bkmExpress, LoggerInterface $logger, BkmExpress\SoapClient $soapClient)
    {
        $this->bkmExpress = $bkmExpress;
        $this->logger = $logger;
        $this->soapClient = $soapClient;
        
        $soapClient->setBkmExpress($bkmExpress);
    }
    
    /**
     * @Route("/", name="homepage")
     */
    public function indexAction(Request $request)
    {
        $this->logger->info('index action');
        
        $tutar = $request->query->get('tutar', 1);

        $tutar = str_replace(',', '.', $tutar);
        $tutar = number_format($tutar, 2, ',', '');

        $payload = [
            'amount' =>  $tutar,
            'nonceUrl' => $this->generateUrl('bkm-nonce', [], UrlGeneratorInterface::ABSOLUTE_URL),
            'installmentUrl' => $this->generateUrl('bkm-installment', [], UrlGeneratorInterface::ABSOLUTE_URL),
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
     * @Route("/verify", name="bkm-verify")
     */
    public function verifyAction(Request $request)
    {
        $payload = json_decode($request->getContent(), true);

        return new JsonResponse($this->bkmExpress->getEncryption()->verifyData($payload['data'], $payload['signature']));
    }
    
    /**
     * 
     * @param Request $request
     * @Route("/refund", name="bkm-refund")
     */
    public function refundAction(Request $request)
    {
        $payload = json_decode($request->getContent(), true);

        if (isset($payload['posId'])) {
            $posConfig = $this->bkmExpress->getPosAccount($payload['posId']);

            unset($payload['posId']);

            if (!empty($posConfig)) {
                $payload['posUid'] = $posConfig->getUserId();
                $payload['posPwd'] = $posConfig->getPassword();
                $payload['extra'] = $posConfig->getParams();
            }
        }
        
        $response = $this->soapClient->reversalWithRef(array_merge($payload, [
            'merchantId' => $this->bkmExpress->getMerchantId(),
            'uniqueReferans' => uniqid('eu_', true),
            'requestType' => 2,
        ]));
        
        
        $xml = new \SimpleXMLElement('<response/>');
        //array_walk_recursive($response, array($xml, 'addChild'));
        
        foreach ($response as $key => $value) {
            $xml->addChild($key, $value);
        }
        
        $response = new Response($xml->asXML());
        
        $response->headers->set('Content-Type', 'application/xml');
        
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
            
            
            $posConfig = $this->bkmExpress->getPosAccount($bankCode, true);
            // $posConfig = null;

            $installments[$bin][] = $this->getInstallmentRow($payload->totalAmount, 1, 1, $posConfig);
            $installments[$bin][] = $this->getInstallmentRow($payload->totalAmount, 3, 1.05, $posConfig);
            $installments[$bin][] = $this->getInstallmentRow($payload->totalAmount, 6, 1.07, $posConfig);
            $installments[$bin][] = $this->getInstallmentRow($payload->totalAmount, 9, 1.11, $posConfig);

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
    
    
    protected function getInstallmentRow($amount, $count, $rate, $pos = null)
    {
        $amount = floatval(str_replace(',', '.', $amount)) * $rate;

        return [
            'numberOfInstallment' => $count,
            'installmentAmount' => number_format($amount / $count, 2, ',', ''),
            'totalAmount' => number_format($amount, 2, ',', ''),
            'vposConfig' => $pos
        ];
    }
}
