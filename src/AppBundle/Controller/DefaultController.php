<?php

namespace AppBundle\Controller;

use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\JsonResponse;
use AppBundle\Payment\BkmExpress;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;


class DefaultController extends Controller
{
   
    /**
     * @Route("/", name="homepage")
     */
    public function indexAction(Request $request, BkmExpress $bkmExpress)
    {
        
        $payload = [
            'amount' => '1',
            'nonceUrl' => $this->generateUrl('bkm-nonce', [], UrlGeneratorInterface::ABSOLUTE_URL),
            'installmentUrl' => $this->generateUrl('bkm-installment', [], UrlGeneratorInterface::ABSOLUTE_URL),
            'orderId' => uniqid('eu_', true),
            //'address' => '',
            //'tckn' => '',
            //'msisdn' => '',
            //'campaignCode' => '',
            //'agreementUrl' => ''
        ];

        $ticket = $bkmExpress->getTicket($payload);
        
        return $this->render('default/index.html.twig', [
            'base_dir' => realpath($this->getParameter('kernel.project_dir')) . DIRECTORY_SEPARATOR,
            'ticket' => $ticket->data,
            'endpoint' => $bkmExpress->getJsUrl()
        ]);
    }
    
    /**
     * 
     * @param Request $request
     * @Route("/nonce", name="bkm-nonce")
     */
    public function nonceAction(Request $request, BkmExpress $bkmExpress)
    {
        $payload = json_decode($request->getContent());
        
        if (!$bkmExpress->getEncryption()->verifyData($payload->id, $payload->signature)) {
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
        
        return $response;
    }

    /**
     * 
     * @param Request $request
     * @Route("/installment", name="bkm-installment")
     */
    public function installmentAction(Request $request, BkmExpress $bkmExpress)
    {
        $payload = json_decode($request->getContent());

        if (!$bkmExpress->getEncryption()->verifyData($payload->ticketId, $payload->signature)) {
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
                'vposConfig' => null
            ];
            
        }
        
        if (count($installments) === 0) {
            return new JsonResponse([
                'data' => null,
                'status' => 'fail',
                'error' => 'Can not get Installments'
            ]);
        }
        
        return new JsonResponse([
            'data' => ['installments' => $installments],
            'status' => 'ok',
            'error' => ''
        ]);
    }
    
}
