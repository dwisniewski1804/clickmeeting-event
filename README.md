# CLICKMEETING APPLICATION #
This is paypal payment and clickmeeting conference test application.
It requires **[Composer](http://getcomposer.org)** and **PHP ^7.4**. You DO NOT need any database. It works on Session service.

1. Copy `.env` file as `.env.local` 
2. Fill env variables values: </br>

        ### PAYPAL ###
        PAYPAL_CLIENT_ID=clientID 
        PAYPAL_CLIENT_SECRET=clientSecret
        PAYPAL_BASE_URL=https://api.sandbox.paypal.com
        
        ### CLICKMEETING ###
        CLICKMEETING_API_KEY=apiKey
        CLICKMEETING_BASE_URL=https://api.clickmeeting.com/v1/

3. Install symfony CLI from here https://symfony.com/download

4. Run `composer install` </br>

5. Run local server with `symfony server:start` </br>

6. Enjoy testing my app </br>