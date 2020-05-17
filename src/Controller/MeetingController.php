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
    public function renderPaymentForm(Request $request, PayPalClient $payPalClient)
    {
        $form = $this->createForm(PayPalPaymentForm::class, new PayPalPayment());
        $form->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {
            $urlToApprove = $payPalClient->initPayment($form->getData());
            return $this->redirect($urlToApprove);
        }
        return $this->render('Register/register_form.html.twig', ['form' => $form->createView()]);
    }

    /**
     * @Route("/payment-status", name="app_payment_status")
     */
    public function getPaymentStatus(PayPalClient $payPalClient, ClickMeetingRestClient $clickMeetingRestClient)
    {
        if ($payPalClient->checkPayment()) {
            $urlHash = $clickMeetingRestClient->getConferenceLink();
            if($urlHash){
                $form = $this->createForm(JoinMeetingForm::class, [], ['action' => $urlHash]);
                return $this->render('ClickMeeting/join_meeting_form.html.twig', ['form' => $form->createView()]);
            }
        }

        return $this->render('ClickMeeting/join_meeting_form.html.twig', ['form' => null]);
    }

}