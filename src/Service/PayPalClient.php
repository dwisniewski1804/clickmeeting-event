<?php


namespace App\Service;


use App\Model\PaymentInterface;
use PayPal\Api\Payment;
use PayPal\Api\PaymentExecution;
use PayPal\Rest\ApiContext;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpClient\Exception\ClientException;
use Symfony\Component\HttpClient\HttpClient;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Session\SessionInterface;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Routing\RouterInterface;

class PayPalClient
{

    private string $baseUrl;
    private string $returnUrl;
    private string $cancelUrl;
    private ApiContext $apiContext;
    private SessionInterface $session;
    private RequestStack $requestStack;
    private LoggerInterface $logger;

    /**
     * PayPalClient constructor.
     * @param RequestStack $requestStack
     * @param RouterInterface $router
     * @param SessionInterface $session
     * @param string $baseUrl
     * @param string $clientId
     * @param string $clientSecret
     */
    public function __construct(
        RequestStack $requestStack,
        RouterInterface $router,
        SessionInterface $session,
        LoggerInterface $logger,
        string $baseUrl,
        string $clientId,
        string $clientSecret
    ) {
        $this->baseUrl = $baseUrl;
        $this->requestStack = $requestStack;
        $this->session = $session;
        $this->logger = $logger;
        $this->apiContext = new \PayPal\Rest\ApiContext(new \PayPal\Auth\OAuthTokenCredential($clientId, $clientSecret));
        $this->returnUrl = $router->generate('app_payment_status', [], UrlGeneratorInterface::ABSOLUTE_URL);
        $this->cancelUrl = $router->generate('app_payment_cancel',[],UrlGeneratorInterface::ABSOLUTE_URL);
    }

    /**
     * @param PaymentInterface $payment
     * @return string
     * @throws \Symfony\Contracts\HttpClient\Exception\ClientExceptionInterface
     * @throws \Symfony\Contracts\HttpClient\Exception\RedirectionExceptionInterface
     * @throws \Symfony\Contracts\HttpClient\Exception\ServerExceptionInterface
     * @throws \Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface
     */
    public function initPayment(PaymentInterface $payment): string
    {
        $this->session->set('payment', $payment);
        return $this->makePaymentCall();
    }

    /**
     * @return string
     * @throws \Symfony\Contracts\HttpClient\Exception\ClientExceptionInterface
     * @throws \Symfony\Contracts\HttpClient\Exception\RedirectionExceptionInterface
     * @throws \Symfony\Contracts\HttpClient\Exception\ServerExceptionInterface
     * @throws \Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface
     */
    private function makePaymentCall(): string
    {
        $payer = new \PayPal\Api\Payer();
        $payer->setPaymentMethod('paypal');

        $amount = new \PayPal\Api\Amount();
        $amount->setTotal('1.00');
        $amount->setCurrency('USD');

        $transaction = new \PayPal\Api\Transaction();
        $transaction->setAmount($amount);

        $redirectUrls = new \PayPal\Api\RedirectUrls();
        $redirectUrls->setReturnUrl($this->returnUrl)
            ->setCancelUrl($this->cancelUrl);

        $payment = new \PayPal\Api\Payment();
        $payment->setIntent('sale')
            ->setPayer($payer)
            ->setTransactions(array($transaction))
            ->setRedirectUrls($redirectUrls);

        $client = HttpClient::create();
        $body = '{
              "intent": "sale",
              "payer": {
                    "payment_method": "paypal"
              },
              "transactions": [{
                    "amount": {
                        "total": "30.11",
                  "currency": "USD",
                  "details": {
                    "subtotal": "30.00",
                    "tax": "0.07",
                    "shipping": "0.03",
                    "handling_fee": "1.00",
                    "shipping_discount": "-1.00",
                    "insurance": "0.01"
                  }
                },
                "description": "",
                "custom": "EBAY_EMS_90048630054435",
                "invoice_number": "48787581673",
                "payment_options": {
                        "allowed_payment_method": "INSTANT_FUNDING_SOURCE"
                },
                "soft_descriptor": "ECHI5786781",
                "item_list": {
                        "items": [{
                            "name": "hat",
                    "description": "Brown color hat",
                    "quantity": "5",
                    "price": "3",
                    "tax": "0.01",
                    "sku": "1",
                    "currency": "USD"
                  }, {
                            "name": "handbag",
                    "description": "Black color hand bag",
                    "quantity": "1",
                    "price": "15",
                    "tax": "0.02",
                    "sku": "product34",
                    "currency": "USD"
                  }],
                  "shipping_address": {
                            "recipient_name": "Hello World",
                    "line1": "4thFloor",
                    "line2": "unit#34",
                    "city": "SAn Jose",
                    "country_code": "US",
                    "postal_code": "95131",
                    "phone": "011862212345678",
                    "state": "CA"
                  }
                }
              }],
              "note_to_payer": "Contact us for any questions on your order.",
              "redirect_urls": {
                "return_url": "' . $this->returnUrl . '",
                "cancel_url": "https://example.com"
              }
            }';
        try {
            $response = $client->request('POST', $this->baseUrl . '/v1/payments/payment', [
                'headers' => ['Content-Type' => 'application/json'],
                'auth_bearer' => $this->accessToken,
                'body' => $body,
            ]);
            $response = json_decode($response->getContent(), true);
        }catch (ClientException $exception){
            dump($exception->getResponse()->getContent());
            die();
        }

        $urlToPayment = $response['links'][1]['href'];
        return $urlToPayment;
    }

    /**
     * @return bool
     */
    public function checkPayment(): bool
    {
        if($this->requestStack->getCurrentRequest())
        {
            $paymentId = $this->requestStack->getCurrentRequest()->get('paymentId');
            $payerId = $this->requestStack->getCurrentRequest()->get('PayerID');
            $payment = Payment::get($paymentId, $this->apiContext);
            $execution = new PaymentExecution();
            $execution->setPayerId($payerId);

            try {
                $result = $payment->execute($execution, $this->apiContext);
                try {
                    $payment = Payment::get($paymentId, $this->apiContext);
                } catch (\Exception $ex) {
                    $this->logger->error($ex->getMessage());
                    return false;
                }
            } catch (\Exception $ex) {
                $this->logger->error($ex->getMessage());
                return false;
            }
            return $payment->getState();
        }

        $this->logger->error('Request does not exist.');
        return false;
    }
}