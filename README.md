# Overview

This module provides functionality to ratelimit aspects of SSP

# Installation

    composer require cirrusidentity/simplesamlphp-module-ratelimit


# Rate Limiting Auth Source

Any authsource that uses username and password (and also extends `UserPassBase`)
can be wrapped with `RateLimitUserPass` to limit the number of authentication attempts
an attacker can make.

## Limiters

* DeviceCookieLimimter: This sets a device cookie on successful authentications.
Subsequent authentication attempts for the same user, on the same device will be allowed to proceed. This
prevents an attacker from denial of servicing other users.
* IpLimiter: Allow limiting attempts by IP address. Allows white listing IPs and subnets to
exclude
* PasswordStuffLimiter: Limits the amount of attempts an incorrect password can be used. Used
to prevent password stuffing attacks where an attacker uses the same password but varies
the username
* UsernameLimiter: Limits the amount of attempts by username.

## Configuration

All included limiters support these 2 settings:

* limit: (int)The number of failed attempts before limiter takes affect
* window: (string) An ISO8601 duration string for the time window to track limit. Example: PT5M would be 5 minutes. P14D would be 14 days.

Configuration should be done in `authsources.php`. The `RateLimitUserPass` authsource wraps other auth sources to enforce the rate limits. Each of your existing `authsource` definitions should get moved inside the `'delegate'` key.

### Sample Configuration

```php
$config = [

//  Sample authsource prior to using rate limiting
//    'sample' => [
//        'ldap:Ldap',
//         //Other ldap:LDAP options
//     ],

// Sample authsource after moving to rate limiting
     'sample' => [  // Authsource name stays the same
        'ratelimit:RateLimitUserPass',
         'delegate' => [  // Previous authsource configuration for 'sample' moves here
            'ldap:Ldap',
            //Other ldap:LDAP options
         ],
         'ratelimit' => [
               0 => [
                    'device',
                    'window' => 'P28D',
                    'limit' => 10,
               ],
               1 => [
                    'user',
                    'window' => 'PT5M',
                    'limit' => 20
               ],
               2 => [
                    'password',
                    'window' => 'PT5M',
                    'limit' => 20
               ],
               3 => [
                    'ip',
                    'window' => 'PT5M',
                    'limit' => 100,
                    'whitelist' => [
                       '1.2.3.4',
                       '5.6.7.0/24',
                    ],
               ],
          ]
```

If no `ratelimit` block is defined then the `UsernameLimiter` and `DeviceCookieLimiter`
are automatically enabled.

## Blocking behavior

When a login attempt is blocked the authsource throws a `WRONGUSERPASS` error.

# Development

Run `phpcs` to check code style

    ./vendor/bin/phpcs --standard=PSR2 lib/ tests/

Run `phpunit` to test

    ./vendor/bin/phpunit
    
You can auto correct some findings from phpcs. It is recommended you do this after stage your changes (or maybe even commit) since there is a non-trivial chance it will just mess up your code.

    ./vendor/bin/phpcbf --ignore=somefile.php --standard=PSR2 lib/ tests/
