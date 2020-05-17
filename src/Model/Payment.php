<?php


namespace App\Model;


abstract class Payment implements PaymentInterface
{
    private $nickname;
    private $email;

    /**
     * @return mixed
     */
    public function getNickname(): ?string
    {
        return $this->nickname;
    }

    /**
     * @param mixed $nickname
     */
    public function setNickname(string $nickname): void
    {
        $this->nickname = $nickname;
    }

    /**
     * @return mixed
     */
    public function getEmail(): ?string
    {
        return $this->email;
    }

    /**
     * @param mixed $email
     */
    public function setEmail(string $email): void
    {
        $this->email = $email;
    }
}