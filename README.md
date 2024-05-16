<!-- START doctoc generated TOC please keep comment here to allow auto update -->
<!-- DON'T EDIT THIS SECTION, INSTEAD RE-RUN doctoc TO UPDATE -->
**Table of Contents**  *generated with [DocToc](https://github.com/thlorenz/doctoc)*

- [Overview](#overview)
- [Installation](#installation)
- [Rate Limiting Auth Source](#rate-limiting-auth-source)
  - [Limiters](#limiters)
  - [Configuration](#configuration)
    - [Sample Configuration](#sample-configuration)
      - [Standalone delegate/SSP 2 style configuration](#standalone-delegatessp-2-style-configuration)
      - [Embedded delegate/SSP 1.x style configuration](#embedded-delegatessp-1x-style-configuration)
  - [Blocking behavior](#blocking-behavior)
- [Login Loop Detection](#login-loop-detection)
  - [Configuration](#configuration-1)
- [Exploring with Docker](#exploring-with-docker)
  - [Things to try](#things-to-try)
    - [Blocking logins](#blocking-logins)
    - [Loop Detection](#loop-detection)
- [Development](#development)

<!-- END doctoc generated TOC please keep comment here to allow auto update -->

# Overview

This module provides functionality to ratelimit aspects of SSP

# Installation

* SSP 2: Use v2  (currently at v2.0.0-alpha.1)
* SSP 1: Use v1  (currently at 1.10)

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

`limiters` are run in the order defined, and not in numerical order of the keys.

### Sample Configuration

#### Standalone delegate/SSP 2 style configuration

In SSP v2, on the IdP side, an attacker cannot invoke an authsource directly. This allows you to define the rate limit authsource
and have it reference another one in the config file.

```php
$config = [

//  Sample authsource prior to using rate limiting
   'sample' => [
       'ldap:Ldap',
       //Other ldap:LDAP options
   ],

// Sample authsource after moving to rate limiting
     'sample-ratelimit' => [  // Authsource name stays the same
        'ratelimit:RateLimitUserPass',
         'delegate' => 'sample',
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

#### Embedded delegate/SSP 1.x style configuration

In SSP 1.x there was no way to hide an authsource from someone invoking it directly through the test authsources functionality.
This meant to truly rate limit an authsource you had to hide its configuration inside the ratelimit authsource.

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

# Login Loop Detection
When configured, will stop the browser from looping indefinately when interacting with a broken/mis configured SP. 

## Configuration

```
    $config['authproc.idp'] = [
...
        51 => [
            'class' => 'loginloopdetection:LoopDetection',
            'secondsSinceLastSso' => 5,
            'loopsBeforeWarning' => 15,
            'logOnly' => false,
        ],
...

```
# Exploring with Docker

You can explore these features with Docker.

```bash

docker network create --driver bridge ratelimit-net
# Ratelimit requires you to define a "store" to store rate limit data. These tests use memcached
docker run --network ratelimit-net -p 11211:11211 --name memcache-ratelimit -d memcached

docker run -d --name ssp-ratelimit \
   --network ratelimit-net \
   --mount type=bind,source="$(pwd)",target=/var/simplesamlphp/staging-modules/ratelimit,readonly \
  -e STAGINGCOMPOSERREPOS=ratelimit \
  -e COMPOSER_REQUIRE="cirrusidentity/simplesamlphp-module-ratelimit:@dev" \
  -e SSP_ENABLED_MODULES="ratelimit" \
  --mount type=bind,source="$(pwd)/tests/docker/metadata/",target=/var/simplesamlphp/metadata/,readonly \
  --mount type=bind,source="$(pwd)/tests/docker/authsources.php",target=/var/simplesamlphp/config/authsources.php,readonly \
  --mount type=bind,source="$(pwd)/tests/docker/config-override.php",target=/var/simplesamlphp/config/config-override.php,readonly \
  --mount type=bind,source="$(pwd)/tests/docker/cert/",target=/var/simplesamlphp/cert/,readonly \
  --mount type=bind,source="$(pwd)/tests/docker/public/looping-login.php",target=/var/simplesamlphp/public/looping-login.php,readonly \
   -p 443:443 cirrusid/simplesamlphp:v2.2.2
```

Then log in as `admin:secret` to https://ratelimit.local.stack-dev.cirrusidentity.com/simplesaml/
to confirm SSP is running.

## Things to try

### Blocking logins

To reach the `admin` test login endpoints you must first authenticate as an admin. Login to https://ratelimit.local.stack-dev.cirrusidentity.com/simplesaml/admin
as `admin:secret`

The [example-userpass](https://ratelimit.local.stack-dev.cirrusidentity.com/simplesaml/module.php/admin/test/example-userpass)
authsource is configured with a low number of attempts for logins. Try logging in 3 or 4 times with the same username and wrong password and
you should see log lines like

    [Tue Dec 06 22:04:23.114923 2022] [php:notice] [pid 58] [client 172.18.0.1:59924] %date{M j H:i:s} simplesamlphp NOTICE STAT [c854ab328b] User 'testuser' login attempt blocked by SimpleSAML\\Module\\ratelimit\\Limiters\\UsernameLimiter

If you try varying usernames and the same password (a password stuffing attack) then after a few attempts you should see

    User 'pass2' login attempt blocked by SimpleSAML\\Module\\ratelimit\\Limiters\\PasswordStuffingLimiter

### Loop Detection

Visiting the [looping-login page](https://ratelimit.local.stack-dev.cirrusidentity.com/simplesaml/looping-login.php)
will issue a request as an SP to log in with a local IdP and print out the attributes. User `member`, password `memberpass`.
If you add a `loop` query parameter
you can mimic a misbehaving SP that continuously sends a user to the IdP to login. The IdP is configured (see `saml20-idp-hosted.php`)
with loop detection and will display an error page after too many loops.


```
WARNING [c854ab328b] LoopDetection: Only 0 seconds since last SSO for this user from the SP 'https://ratelimit.local.stack-dev.cirrusidentity.com/simplesaml/module.php/saml/sp/metadata.php/loop-test' LoopDetectionCount 5
```

# Development

Run `phpcs` to check code style

    ./vendor/bin/phpcs

Run `phpunit` to test

    ./vendor/bin/phpunit
    
You can auto correct some findings from phpcs. It is recommended you do this after stage your changes (or maybe even commit) since there is a non-trivial chance it will just mess up your code.

    ./vendor/bin/phpcbf

I always have trouble with `psalm` and it's cache, so I tend to run without caching

     ./vendor/bin/psalm --no-cache

