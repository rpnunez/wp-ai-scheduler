<?php

namespace Tests;

use Tests\Support\AcceptanceTester;

class LoginCest
{
    public function _before(AcceptanceTester $I)
    {
        $I->amOnPage('/');
    }

    public function loginSuccessfully(AcceptanceTester $I)
    {
        // write a positive login test 
    }

    public function loginWithInvalidPassword(AcceptanceTester $I)
    {
        // write a negative login test
    }
}