<?php


namespace App\Model;


interface PaymentInterface
{
    public function getNickname():?string;
    public function getEmail():?string;
    public function setEmail(string $email):void;
    public function setNickname(string $nickname):void;
}