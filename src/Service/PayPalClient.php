<?php


namespace App\Service;


use Symfony\Component\HttpClient\HttpClient;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Routing\RouterInterface;

class PayPalClient
{

    private string $baseUrl;
    private string $accessToken;
    private string $returnUrl;

    public function __construct(
        RequestStack $requestStack,
        RouterInterface $router,
        string $baseUrl,
        string $accessToken
    ) {
        $this->baseUrl = $baseUrl;
        $this->accessToken = $accessToken;
        $this->returnUrl = $router->generate('app_payment_success', [], UrlGeneratorInterface::ABSOLUTE_URL);
    }

    public function initPayment(): string
    {
        return $this->makePaymentCall();
    }

    public function checkPayment(string $paymentId, string $payerId): bool
    {
        $body = ['payer_id' => $payerId];
        $client = HttpClient::create();
        $response = $client->request('POST', $this->baseUrl . '/v1/payments/payment/' . $paymentId . '/execute', [
            'headers' => [
                'Content-Type' => "application/json"
            ],
            'auth_bearer' => $this->accessToken,
            'body' => '{"payer_id": "xxx" }',
        ]);

        return json_decode($response->getContent(), true)['state'] === 'approved';
    }

    private function makePaymentCall(): string
    {
        $client = HttpClient::create();
        $response = $client->request('POST', $this->baseUrl . '/v1/payments/payment', [
            'headers' => [
                'Content-Type' => "application/json"
            ],
            'auth_bearer' => $this->accessToken,
            'body' => '{
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
                "description": "This is the payment transaction description.",
                "custom": "EBAY_EMS_90048630024435",
                "invoice_number": "48787589673",
                "payment_options": {
                        "allowed_payment_method": "INSTANT_FUNDING_SOURCE"
                },
                "soft_descriptor": "ECHI5786786",
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
            }'
        ]);
        $response = json_decode($response->getContent(), true);
        $urlToPayment = $response['links'][1]['href'];
        return $urlToPayment;
    }
}