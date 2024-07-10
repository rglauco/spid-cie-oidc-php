# Standalone proxy relying party

## Foreword

This is very specific time-bound (July 2024) documentation whose aim is just to have a working
demo site to authenticate with OIDC/CIE, have the released attributes (givenName, sn, spidCode
and so on) printed on screen and nothing more.

## Environment survey

This example has been staged on a Debian GNU/Linux 12 (bookworm) VM.

Tested with php-7.4; php-8.2 didn't work reliably at the times.

I'm going to use ```https://rp-host-01.example.edu/``` as virtual host name and
```https://rp-host-01.example.edu/test``` as client_id.

Authority_hints (preprod/production):
* https://preprod.oidc.registry.servizicie.interno.gov.it
* https://oidc.registry.servizicie.interno.gov.it


## Setup

[code source on github](https://github.com/rglauco/spid-cie-oidc-php.git)

Beforehand install php-7.4 and composer.

    $ sudo apt-get install php-sqlite3 #and many other, see later
    $ cd /var/www/rp-host-01/
    $ git clone https://github.com/rglauco/spid-cie-oidc-php.git
    $ cd /var/www/rp-host-01/spid-cie-oidc-php
    $ php ~/composer.phar install

You don't need to provide right now all the correct details about 
your organization, you can fox them later.
* client_id: https://rp-host-01.example.edu/test
* www_dir: /var/www/rp-host-01
* service_name: oidc

Please install all the missing packages composer is asking for.

    $ mkdir -p data
    $ sudo chown www-data:www-data data
    $ a2enmod rewrite

If you need to re-run the installer:

     $ rm -rf composer.lock config cert

create a new virtual host (/etc/apache2/sites-available/rp-host-01-ssl.conf). 
Get default-ssl.conf as the blueprint to add edits as follows:

    ServerName rp-host-01.example.edu
    DocumentRoot /var/www/rp-host-01
    <Directory /var/www/rp-host-01>
        Options FollowSymLinks
        AllowOverride All
        Require all granted
    </Directory>

Edit by hand  ```config/config.json``` to update your details if you missed them 
in the install phase. Add

    "cert_public_core_sig":"\/var\/www\/rp-host-01\/spid-cie-oidc-php\/cert\/rp.crt",
    "cert_private_core_sig":"\/var\/www\/rp-host-01\/spid-cie-oidc-php\/cert\/rp.pem"

along with "cert_enc_public" key level.

Redirect_uris has to be: "redirect_uri":"https:\/\/rp-host-01.example.edu\/test\/oidc\/rp\/redirect"

    $ php ~/composer.phar run  post-update-cmd # maybe useless?

Check your [metadata](https://rp-host-01.example.edu/test/.well-known/openid-federation). You 
are going to need to decode the jwt for example at https://jwt.io 

## Onboarding

The [onboarding](https://federazione.servizicie.interno.gov.it/) site is going to ask you the 
login page (https://rp-host-01.example.edu/test/oidc/rp/authz) and the federation keys that you 
read from the decoded jwt: paste the value of the "jwks" key (it is a hash whose key is "keys") 
that you find at the top level, en par with "iss".

Pick your trust anchor (choose one way from) ...:

* From RP metadata [fetch](https://preprod.oidc.registry.servizicie.interno.gov.it/fetch/?sub=https://rp-host-01.example.edu/test&format=json);
* From RP metadata resolve:

      #!/bin/bash

      curl  -G -o trust_anchor.jwt  --data-urlencode \
      "sub=https://rp-host-01.example.edu/test" \
      --data-urlencode \
      "anchor=https://preprod.oidc.registry.servizicie.interno.gov.it"\
      -H "Content-Type: application/x-www-form-urlencoded" \
      https://preprod.oidc.registry.servizicie.interno.gov.it/resolve

... and paste it by hand into ``config/config.json``

## Last gory details

You need to fix by hand the second return redirect:

    diff --git a/www/index.php b/www/index.php
    index 46f6628..f978583 100644
    --- a/www/index.php
    +++ b/www/index.php
    @@ -298,8 +298,10 @@ $f3->route([
    $userinfoResponse->provider_id = $op_id;
    
             $responseHandlerClass = $config['response_handler'];
    -        $responseHandler = new $responseHandlerClass($config);
    -        $responseHandler->sendResponse($redirect_uri, $userinfoResponse, $state);
    +       $responseHandler = new $responseHandlerClass($config);
    +       $test_page = "https://rp-host-01.example.edu/test/test.php";
    +        //$responseHandler->sendResponse($redirect_uri, $userinfoResponse, $state);
    +        $responseHandler->sendResponse($test_page, $userinfoResponse, $state);
    } catch (Exception $e) {
    $code = in_array($e->getCode(), [200, 301, 302, 400, 401, 404])? $e->getCode() : 500;
    $f3->error($code, $e->getMessage());

## Go to Production

After onboarding, you need two setps:
* in your config/config.json switch to 'production:true'
* modify by hand login.php with production urls.

      diff --git a/www/view/login.php b/www/view/login.php
      index b899f35..737ebc1 100644
      --- a/www/view/login.php
      +++ b/www/view/login.php
      @@ -137,7 +137,7 @@
      </div>
      <div class="col-12 col-md-6 text-center">
      <li class="btn p-0 border-0 my-3">
      -                                                <a href="<?php echo $BASEURL; ?>/oidc/rp/<?php echo $DOMAIN; ?>/authz/<?php echo base64_encode('https://preprod.oidc.registry.servizicie.interno.gov.it'); ?>/<?php echo base64_encode('https://preproduzione.oidc.idserver.servizicie.interno.gov.it'); ?>?state=state123">
      +                                                <a href="<?php echo $BASEURL; ?>/oidc/rp/<?php echo $DOMAIN; ?>/authz/<?php echo base64_encode('https://oidc.registry.servizicie.interno.gov.it'); ?>/<?php echo base64_encode('https://oidc.idserver.servizicie.interno.gov.it'); ?>?state=state123">
                                                           <img class="img-fluid" src="<?php echo $BASEURL; ?>/assets/img/cie_button.png" alt="Entra con CIE">
                                                           <span class="sr-only">Accedi con identità digitale CIE</span>
                                                       </a>
## Examples

Please have a look of the example ```config.json```.
