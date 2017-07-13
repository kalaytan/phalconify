<?php

namespace Phalconify\Application\Rest\Auth\Helpers;

use Phalconify\Application\Rest\Collections\UserBase as UsersCollection;
use Phalconify\Application\Rest\Http\Response\Json as JsonResponse;

class User
{
    public static function getUsersCollection()
    {
        $di = \Phalcon\Di::getDefault();
        $config = $di->getShared('phalconify-config');

        if (isset($config->overrides) && isset($config->overrides->usersCollection)) {
            return $config->overrides->usersCollection;
        }

        return '\Phalconify\Application\Rest\Collections\Users';
    }

    public function hasValidUser(\Phalcon\Di\FactoryDefault $di = null)
    {
        // Ensure $di is not null
        if ($di === null) {
            return false;
        }

        // Ensure user is in in service container
        if (!isset($di['phalconify-user'])) {
            return false;
        }

        // Ensure user was found
        if ($di['phalconify-user'] === false) {
            return false;
        }

        // Ensure user status is present
        if (!isset($di['phalconify-user']->status)) {
            return false;
        }

        // Ensure user is set to active
        if ($di['phalconify-user']->status === UsersCollection::STATUS_ACTIVE) {
            return true;
        }

        return false;
    }

    public static function generateToken()
    {
        // Initialise
        $exists = true;

        // Loop until a unique token has been generated
        while ($exists !== false) {

            // Create a random string token
            $token = \Phalcon\Text::random(\Phalcon\Text::RANDOM_ALNUM, 255);

            // Check token is unique
            $exists = UsersCollection::findFirst([
                'conditions' => [
                    'token' => $token,
                ],
            ]);
        }

        // Return the unique token
        return $token;
    }

    public static function passwordsMatch($encryptedPassword, $password, $encryptionKey)
    {
        // Create instance of the Phalcon cryptor
        $cryptor = new \Phalcon\Crypt();

        // Verify decrypted password matches the plain text password
        if (trim($cryptor->decryptBase64($encryptedPassword, $encryptionKey)) !== $password) {
            return false;
        }

        return true;
    }
    
    public static function accessDenied($target)
    {
        $di = \Phalcon\Di::getDefault();
        $ip = $di->get('phalconify-request')->getClientAddress();
        $user = $di->getShared('phalconify-auth-authorise')->getCurrentUser();
        $logger = new \Phalconify\Utils\Logger();

        if ($user->role == 'Guest'){
            $logger->logDeniedAccess($ip.' trying to access '.$target);
            
            return JsonResponse::unauthorized(['You are unauthorized']);
        }
        elseif ($user->role == 'User' || $user->role == 'Admin'){
            $logger->logDeniedAccess($ip.' '.(string)$user->_id.' trying to access '.$target);

            return JsonResponse::denied(['Access denied']);
        }
    }
}
