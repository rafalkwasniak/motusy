<?php

namespace App\Exceptions;

use Exception;

/**
 * Wrong email or password. Deliberately distinct from AuthenticationException, which
 * means "no or expired token": the app reacts differently to each, so they must not
 * share a response code.
 */
class InvalidCredentialsException extends Exception
{
}
