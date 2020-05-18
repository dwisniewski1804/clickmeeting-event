# CLICKMEETING APPLICATION #
This is paypal payment and clickmeeting conference test application.
It requires **PHP ^7.4**.

1. Copy `.env` file as `.env.local` 
2. Add env variables values: </br>

        ### PAYPAL ###
        PAYPAL_CLIENT_ID=clientID </br>
        PAYPAL_CLIENT_SECRET=clientSecret </br>
        PAYPAL_BASE_URL=https://api.sandbox.paypal.com </br>
        
        ### CLICKMEETING ###
        CLICKMEETING_API_KEY=apiKey</br>
        CLICKMEETING_BASE_URL='https://api.clickmeeting.com/v1/' </br>

3. Install symfony CLI from here https://symfony.com/download </br>

4. Run `composer install` </br>

5. Run local server with `symfony server:start` </br>

6. Enjoy testing my app </br>