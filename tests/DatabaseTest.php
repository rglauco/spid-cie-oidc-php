<?php

use PHPUnit\Framework\TestCase as TestCase;
use SPID_CIE_OIDC_PHP\Core\Database;

/**
 * @covers SPID_CIE_OIDC_PHP\Core\Database
 */
class DatabaseTest extends TestCase
{
    /**
     * @covers SPID_CIE_OIDC_PHP\Core\Database::saveToStore
     * @covers SPID_CIE_OIDC_PHP\Core\Database::getFromStore
     */
    public function test_Store()
    {
        $database = new Database("tests/tests.sqlite");
        
        $type = 'openid-federation';
        $url = 'https://iss/.well-known/openid-federation';
        $object = array(
            "jti" => uniqid(),
            "iss" => "https://iss",
            "sub" => "https://sub",
            "aud" => "https://aud",
            "iat" => strtotime("now"),
            "exp" => strtotime("+24 hours"),
        );

        $iss = $object['iss'];
        $iat = $object['iat'];
        $exp = $object['exp'];

        $database->saveToStore($iss, $type, $url, $iat, $exp, $object);

        $object2 = $database->getFromStore('https://iss_not_saved', $type);
        $this->assertNull($object2);

        $object3 = $database->getFromStore($iss, 'unknown_type');
        $this->assertNull($object3);

        $object4 = $database->getFromStore($iss, $type);
        $this->assertEquals((object) $object, (object) $object4);

        $object5 = array(
            "jti" => uniqid(),
            "iss" => "https://iss",
            "sub" => "https://sub",
            "aud" => "https://aud",
            "iat" => strtotime("now"),
            "exp" => strtotime("+48 hours"),
        );

        $database->saveToStore($iss, $type, $url, $iat, $exp, $object5);

        $object6 = $database->getFromStore($iss, $type);
        $this->assertEquals((object) $object6, (object) $object5);
        $this->assertNotEquals($object6, $object);

        $object7 = $database->getFromStoreByURL('fake_url');
        $this->assertNull($object7);

        $object8 = $database->getFromStoreByURL($url);
        $this->assertEquals((object) $object8, (object) $object5);
        $this->assertNotEquals($object8, $object);
    }
}
