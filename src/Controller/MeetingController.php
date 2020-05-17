<?php

namespace App\Controller;

use App\Form\Type\JoinMeetingForm;
use App\Form\Type\PayPalPaymentForm;
use App\Model\PayPalPayment;
use App\Service\ClickMeetingRestClient;
use App\Service\PayPalClient;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;

class MeetingController extends AbstractController
{
    /**
     * @Route("/", name="app_payment_form")
     */
    public function paymentForm(Request $request, PayPalClient $payPalClient)
    {
        $form = $this->createForm(PayPalPaymentForm::class, new PayPalPayment());
        $form->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {
            $urlToApprove = $payPalClient->initPayment();
            return $this->redirect($urlToApprove);
        }
        return $this->render('PayPal\paypal_form.html.twig', ['form' => $form->createView()]);
    }

    /**
     * @Route("/payment-success", name="app_payment_success")
     */
    public function successfulPayemnt(
        Request $request,
        PayPalClient $payPalClient,
        ClickMeetingRestClient $clickMeetingRestClient
    ) {
        $paymentId = $request->get('paymentId');
        $payerId = $request->get('PayerID');

        $form = null;
        if ($payPalClient->checkPayment($paymentId, $payerId)) {
            $urlHash = $clickMeetingRestClient->getConferenceLink();
            $form = $this->createForm(JoinMeetingForm::class, [], ['action' => $urlHash]);
            return $this->render('ClickMeeting/join_meeting.html.twig', ['form' => $form->createView()]);
        }

        return $this->render('ClickMeeting/join_meeting.html.twig', ['form' => null]);
    }

}