<?php


namespace App\Service;


use App\Model\PaymentInterface;
use PayPal\Api\Amount;
use PayPal\Api\Payer;
use PayPal\Api\Payment;
use PayPal\Api\PaymentExecution;
use PayPal\Api\RedirectUrls;
use PayPal\Api\Transaction;
use PayPal\Auth\OAuthTokenCredential;
use PayPal\Rest\ApiContext;
use Psr\Log\LoggerInterface;
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
     * @param LoggerInterface $logger
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
        $this->logger = $logger;
        $this->session = $session;
        $this->apiContext = new ApiContext(new OAuthTokenCredential($clientId, $clientSecret));
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
     * Create payment
     * @return string
     */
    private function makePaymentCall(): string
    {
        $payer = new Payer();
        $payer->setPaymentMethod('paypal');

        $amount = new Amount();
        $amount->setTotal('1.00');
        $amount->setCurrency('USD');

        $transaction = new Transaction();
        $transaction->setAmount($amount);

        $redirectUrls = new RedirectUrls();
        $redirectUrls->setReturnUrl($this->returnUrl)
            ->setCancelUrl($this->cancelUrl);

        $payment = new Payment();
        $payment->setIntent('sale')
            ->setPayer($payer)
            ->setTransactions(array($transaction))
            ->setRedirectUrls($redirectUrls);

        try {
            $payment->create($this->apiContext);
            return $payment->getApprovalLink();
        }
        catch (\Exception $ex) {
            $this->logger->error($ex->getMessage());
        }

        return false;
    }

    /**
     * Check if payment is approved
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

    /**
     * @return PaymentInterface|null
     */
    public function getSessionPayment():?PaymentInterface
    {
        return $this->session->get('payment');
    }
}