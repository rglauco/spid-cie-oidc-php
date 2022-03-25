<?php

/**
 * spid-cie-oidc-php
 * https://github.com/italia/spid-cie-oidc-php
 *
 * 2022 Michele D'Amico (damikael)
 *
 * Licensed under the Apache License, Version 2.0 (the "License");
 * you may not use this file except in compliance with the License.
 * You may obtain a copy of the License at
 *
 *    http://www.apache.org/licenses/LICENSE-2.0
 *
 * Unless required by applicable law or agreed to in writing, software
 * distributed under the License is distributed on an "AS IS" BASIS,
 * WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
 * See the License for the specific language governing permissions and
 * limitations under the License.
 *
 * @author     Michele D'Amico <michele.damico@linfaservice.it>
 * @license    http://www.apache.org/licenses/LICENSE-2.0  Apache License 2.0
 */

namespace SPID_CIE_OIDC_PHP\Core;

use Jose\Component\Core\JWK;
use Jose\Component\Core\AlgorithmManager;
use Jose\Component\KeyManagement\JWKFactory;
use Jose\Component\Signature\JWSBuilder;
use Jose\Component\Signature\JWSLoader;
use Jose\Component\Signature\JWSVerifier;
use Jose\Component\Signature\Serializer\JWSSerializerManager;
use Jose\Component\Signature\Serializer\CompactSerializer as JWSSerializer;
use Jose\Component\Encryption\JWEDecrypter;
use Jose\Component\Encryption\JWELoader;
use Jose\Component\Encryption\Serializer\JWESerializerManager;
use Jose\Component\Encryption\Serializer\CompactSerializer as JWESerializer;
use Jose\Component\Encryption\Compression\CompressionMethodManager;
use Jose\Component\Encryption\Compression\Deflate;
// Signature Algorithms - HMAC with SHA-2 Functions
use Jose\Component\Signature\Algorithm\HS256;
use Jose\Component\Signature\Algorithm\HS384;
use Jose\Component\Signature\Algorithm\HS512;
// Signature Algorithms - RSASSA-PKCS1 v1_5
use Jose\Component\Signature\Algorithm\RS256;
use Jose\Component\Signature\Algorithm\RS384;
use Jose\Component\Signature\Algorithm\RS512;
// Signature Algorithms - RSASSA-PSS
use Jose\Component\Signature\Algorithm\PS256;
use Jose\Component\Signature\Algorithm\PS384;
use Jose\Component\Signature\Algorithm\PS512;
// Signature Algorithms - Elliptic Curve Digital Signature Algorithm (ECDSA)
use Jose\Component\Signature\Algorithm\ES256;
use Jose\Component\Signature\Algorithm\ES384;
use Jose\Component\Signature\Algorithm\ES512;
// Key Encryption Algorithm
use Jose\Component\Encryption\Algorithm\KeyEncryption\A256KW;
use Jose\Component\Encryption\Algorithm\KeyEncryption\A256GCMKW;
use Jose\Component\Encryption\Algorithm\KeyEncryption\PBES2HS256A128KW;
use Jose\Component\Encryption\Algorithm\KeyEncryption\PBES2HS384A192KW;
use Jose\Component\Encryption\Algorithm\KeyEncryption\PBES2HS512A256KW;
use Jose\Component\Encryption\Algorithm\KeyEncryption\RSAOAEP;
use Jose\Component\Encryption\Algorithm\KeyEncryption\RSAOAEP256;
// Content Encryption Algorithm
use Jose\Component\Encryption\Algorithm\ContentEncryption\A256GCM;
use Jose\Component\Encryption\Algorithm\ContentEncryption\A128CBCHS256;
use Jose\Component\Encryption\Algorithm\ContentEncryption\A192CBCHS384;
use Jose\Component\Encryption\Algorithm\ContentEncryption\A256CBCHS512;

const DEFAULT_TOKEN_EXPIRATION_TIME = 1200;


class JWT
{
    public static function getKeyJWK(string $file)
    {
        $jwk = JWKFactory::createFromKeyFile($file);
        return $jwk;
    }

    public static function getJWKFromJSON(string $json)
    {
        $jwk_obj = JWK::createFromJson($json);
        return $jwk_obj;
    }

    public static function getCertificateJWK(string $file, string $use = 'sig')
    {
        $jwk_obj = JWKFactory::createFromCertificateFile($file, ['use' => $use]);

        // fix \n json_encode issue
        $x5c    = $jwk_obj->get('x5c')[0];
        $x5c    = preg_replace("/\s+/", "", $x5c);

        $jwk = array(
            'kid'       => '1',
            'kty'       => $jwk_obj->get('kty'),
            'n'         => $jwk_obj->get('n'),
            'e'         => $jwk_obj->get('e'),
            'x5c'       => $x5c,
            'x5t'       => $jwk_obj->get('x5t'),
            'x5t#256'   => $jwk_obj->get('x5t#256'),
            'use'       => $jwk_obj->get('use')
        );

        return $jwk;
    }

    public static function getJWKSFromValues(object $values)
    {
        $jwks_obj = JWKSet::createFromKeyData($values);
        return $jwks_obj;
    }

    public static function makeJWS(array $header, array $payload, object $jwk): string
    {
        //$jwk = self::getKeyJWK($file);
        $algorithmManager = JWT::getSigAlgManager($header['alg']);
        $jwsBuilder = new JWSBuilder($algorithmManager);
        $jws = $jwsBuilder
            ->create()
            ->withPayload(json_encode($payload))
            ->addSignature($jwk, $header)
            ->build();

        $serializer = new JWSSerializer();
        $token = $serializer->serialize($jws, 0);

        return $token;
    }

    public static function getJWSPayload(string $token)
    {
        $serializerManager = new JWSSerializerManager([ new JWSSerializer() ]);
        $jws = $serializerManager->unserialize($token);
        $payload = $jws->getPayload();
        return $payload;
    }

    public static function isVerified(string $token, object $jwk)
    {

        $algorithmManager = JWT::getSigAlgManager();
        $jwsVerifier = new JWSVerifier($algorithmManager);
        $serializerManager = new JWSSerializerManager([ new JWSSerializer() ]);
        $jws = $serializerManager->unserialize($token);

        $isVerified = $jwsVerifier->verifyWithKey($jws, $jwk, 0);

        /*
        $jwsLoader = new JWSLoader(
            $serializerManager,
            $jwsVerifier,
            $headerCheckerManager
        );

        $jws = $jwsLoader->loadAndVerifyWithKey($token, $jwk, $signature, $payload);
        */

        return $isVerified;
    }

    /*
    * decryptJWS
    * descrypts the token and return the embedded JWS
    */
    public static function decryptJWE(string $token, string $file)
    {

        $keyEncryptionAlgorithmManager = JWT::getKeyEncAlgManager();
        $contentEncryptionAlgorithmManager = JWT::getContentEncAlgManager();

        $compressionMethodManager = new CompressionMethodManager([
            new Deflate(),
        ]);

        $jweDecrypter = new JWEDecrypter(
            $keyEncryptionAlgorithmManager,
            $contentEncryptionAlgorithmManager,
            $compressionMethodManager
        );

        $serializerManager = new JWESerializerManager([
            new JWESerializer(),
        ]);

        $jweLoader = new JWELoader(
            $serializerManager,
            $jweDecrypter,
            $headerCheckerManager
        );

        $jwk = JWKFactory::createFromKeyFile($file);
        $jws = $jweLoader->loadAndDecryptWithKey($token, $jwk, $recipient);

        return $jws;
    }

    /*
    * private functions for Algorithms
    */

    private static function getSigAlgClassMap()
    {
        $algMap = json_decode(file_get_contents(__DIR__ . '/../../config/alg-sig.json'));
        return $algMap;
    }

    private static function getKeyEncAlgClassMap()
    {
        $algMap = json_decode(file_get_contents(__DIR__ . '/../../config/alg-key-enc.json'));
        return $algMap;
    }

    private static function getContentEncAlgClassMap()
    {
        $algMap = json_decode(file_get_contents(__DIR__ . '/../../config/alg-content-enc.json'));
        return $algMap;
    }

    /*
    *   if $alg is set to an algorithm string, return manager for that,
    *   alse $alg is null, return manager for all supported algorithms
    */
    private static function getAlgManager(object $algClassMap, string $alg = null)
    {
        $algList = array();
        if ($alg != null && property_exists($algClassMap, $alg)) {
            $algClass = $algClassMap->{$alg};
            $algList[] = new $algClass();
        } elseif ($alg != null && !property_exists($algClassMap, $alg)) {
            throw new \Exception("Algorithm not supported");
        } else {
            foreach ($algClassMap as $algClass) {
                $algList[] = new $algClass();
            }
        }

        $algorithmManager = new AlgorithmManager($algList);
        return $algorithmManager;
    }

    private static function getSigAlgManager(string $alg = null)
    {
        $algClassMap = JWT::getSigAlgClassMap();
        $algorithmManager = JWT::getAlgManager($algClassMap, $alg);
        return $algorithmManager;
    }

    private static function getKeyEncAlgManager(string $alg = null)
    {
        $algClassMap = JWT::getKeyEncAlgClassMap();
        $algorithmManager = JWT::getAlgManager($algClassMap, $alg);
        return $algorithmManager;
    }

    private static function getContentEncAlgManager(string $alg = null)
    {
        $algClassMap = JWT::getContentEncAlgClassMap();
        $algorithmManager = JWT::getAlgManager($algClassMap, $alg);
        return $algorithmManager;
    }
}
