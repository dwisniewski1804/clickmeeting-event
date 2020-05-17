<?php
namespace App\Controller;

use App\Form\Type\PayPalPaymentForm;
use App\Model\PayPalPayment;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;

class MeetingController extends AbstractController
{
    /**
     * @Route("/", name="app_payment_form")
     */
    public function paymentForm(Request $request)
    {
        $form = $this->createForm(PayPalPaymentForm::class, new PayPalPayment());
        $form->handleRequest($request);
        if($form->isSubmitted() && $form->isValid()){
            dump($form->getData());
            die();
        }
        return $this->render('PayPal\paypal_form.html.twig', ['form' => $form->createView()]);
    }
}