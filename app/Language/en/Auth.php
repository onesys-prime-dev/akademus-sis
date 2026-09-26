<?php

declare(strict_types=1);

/**
 * Overrides for CodeIgniter Shield's default auth messages.
 *
 * A wrong password and an unknown email must read identically, otherwise the
 * login form reveals which email addresses have accounts.
 */
return [
    'badAttempt'      => 'Incorrect email or password. Please try again.',
    'invalidPassword' => 'Incorrect email or password. Please try again.',
];
