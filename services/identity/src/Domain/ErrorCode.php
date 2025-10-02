<?php

declare(strict_types=1);

namespace Plushki\Identity\Domain;

/**
 * ErrorCode enumerates the domain errors identity surfaces to the app layer. The
 * HTTP adapter maps each code to an RFC 7807 type URI.
 */
enum ErrorCode: string
{
    case InvalidEmail = 'invalid_email';
    case PasswordTooShort = 'password_too_short';
    case EmailAlreadyTaken = 'email_already_taken';
    case UserNotFound = 'user_not_found';
    case InvalidCredentials = 'invalid_credentials';
    case UserArchived = 'user_archived';

    case RefreshTokenInvalid = 'refresh_token_invalid';
    case RefreshTokenExpired = 'refresh_token_expired';
    case RefreshTokenUsed = 'refresh_token_used';
    case ServiceTokenInvalid = 'service_token_invalid';
    case ServiceTokenRevoked = 'service_token_revoked';

    case InvalidRole = 'invalid_role';
}
