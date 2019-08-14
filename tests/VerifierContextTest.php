<?php

namespace HttpSignatures\tests;

use GuzzleHttp\Psr7\Request;
use HttpSignatures\Context;
use HttpSignatures\KeyStore;
use PHPUnit\Framework\TestCase;

class VerifierContextTest extends TestCase
{
    const DATE = 'Fri, 01 Aug 2014 13:44:32 -0700';
    const DATE_DIFFERENT = 'Fri, 01 Aug 2014 13:44:33 -0700';

    /**
     * @var Verifier
     */
    private $verifier;

    /**
     * @var Request
     */
    private $signedMessage;

    /**
     * @var Request
     */
    private $authorizedMessage;

    /**
     * @var Request
     */
    private $signedAndAuthorizedMessage;

    public function setUp()
    {
        $this->setUpVerifier();
        $this->setUpSignedMessage();
        $this->setUpAuthorizedMessage();
        $this->setUpSignedAndAuthorizedMessage();
    }

    private function setUpVerifier()
    {
        // $keyStore = new KeyStore(['pda' => 'secret']);
        $this->verifierCompleteContext = new Context([
          'keys' => ['pda' => 'secret'],
          'headers' => ['(request-target)', 'date'],
        ]);
        $this->verifierEmptyContext = new Context([
        ]);
    }

    private function setUpSignedMessage()
    {
        $signatureLine = sprintf(
            'keyId="%s",algorithm="%s",headers="%s",signature="%s"',
            'pda',
            'hmac-sha256',
            '(request-target) date',
            'cS2VvndvReuTLy52Ggi4j6UaDqGm9hMb4z0xJZ6adqU='
        );

        $this->signedMessage = new Request('GET', '/path?query=123', [
            'Date' => self::DATE,
            'Signature' => $signatureLine,
            'Authorization' => 'Bearer abc123',
        ]);

        $signatureLineNoHeaders = sprintf(
            'keyId="%s",algorithm="%s",signature="%s"',
            'pda',
            'hmac-sha256',
            'cS2VvndvReuTLy52Ggi4j6UaDqGm9hMb4z0xJZ6adqU='
        );

        $this->signedMessageNoHeaders = new Request('GET', '/path?query=123', [
            'Date' => self::DATE,
            'Signature' => $signatureLineNoHeaders,
            'Authorization' => 'Bearer abc123',
        ]);
    }

    private function setUpAuthorizedMessage()
    {
        $authorizationHeader = sprintf(
            'Signature keyId="%s",algorithm="%s",headers="%s",signature="%s"',
            'pda',
            'hmac-sha256',
            '(request-target) date',
            'cS2VvndvReuTLy52Ggi4j6UaDqGm9hMb4z0xJZ6adqU='
        );

        $this->authorizedMessage = new Request('GET', '/path?query=123', [
            'Date' => self::DATE,
            'Authorization' => $authorizationHeader,
            'Signature' => 'My Lawyer signed this',
        ]);
    }

    private function setUpSignedAndAuthorizedMessage()
    {
        $authorizationHeader = sprintf(
            'Signature keyId="%s",algorithm="%s",headers="%s",signature="%s"',
            'pda',
            'hmac-sha256',
            '(request-target) date',
            'cS2VvndvReuTLy52Ggi4j6UaDqGm9hMb4z0xJZ6adqU='
        );
        $signatureHeader = sprintf(
            'keyId="%s",algorithm="%s",headers="%s",signature="%s"',
            'pda',
            'hmac-sha256',
            '(request-target) date',
            'cS2VvndvReuTLy52Ggi4j6UaDqGm9hMb4z0xJZ6adqU='
        );

        $this->signedAndAuthorizedMessage = new Request('GET', '/path?query=123', [
            'Date' => self::DATE,
            'Authorization' => $authorizationHeader,
            'Signature' => $signatureHeader,
        ]);
    }

    public function testVerifySignedMessage()
    {
        $verifier = $this->verifierCompleteContext->verifier();
        $this->assertTrue($verifier->isSigned($this->signedMessage));
        $this->assertEquals(
          "Signed with SigningString 'KHJlcXVlc3QtdGFyZ2V0KTogZ2V0IC9wYXRoP3F1ZXJ5PTEyMwpkYXRlOiBGcmksIDAxIEF1ZyAyMDE0IDEzOjQ0OjMyIC0wNzAw'",
          $verifier->getStatus()[0]
        );
        $verifier->isSigned($this->signedMessage);
        $this->assertEquals(
          1,
          sizeof($verifier->getStatus())
        );
    }

    public function testVerifyAuthorizedMessage()
    {
        $verifier = $this->verifierCompleteContext->verifier();
        $this->assertTrue($verifier->isAuthorized($this->authorizedMessage));
        $this->assertEquals(
        "Authorized with SigningString 'KHJlcXVlc3QtdGFyZ2V0KTogZ2V0IC9wYXRoP3F1ZXJ5PTEyMwpkYXRlOiBGcmksIDAxIEF1ZyAyMDE0IDEzOjQ0OjMyIC0wNzAw'",
        $verifier->getStatus()[0]
      );
        $verifier->isAuthorized($this->authorizedMessage);
        $this->assertEquals(
        1,
        sizeof($verifier->getStatus())
      );
    }

    public function testProvidedParameters()
    {
        $verifier = $this->verifierCompleteContext->verifier();
        $this->assertEquals(
          ['(request-target)', 'date'],
          $verifier->getSignatureHeaders($this->signedMessage, 'headers')
        );
        $verifier = $this->verifierCompleteContext->verifier();
        $this->assertEquals(
          ['date'],
          $verifier->getSignatureHeaders($this->signedMessageNoHeaders, 'headers')
        );
    }

    // TODO: Add keys to a verifier based on provided keyId
    // public function testMinimumHeaders()
    // {
    //   $verifier = $this->verifierEmptyContext->verifier();
    //   $verifier = $verifier->withKeys(['keyId' => 'keyvalue']);
    //   $this->assertTrue($verifier->isSigned($this->signedMessage));
    //
    // }

    // // TODO: Decide on compatibility for isValid() for 99designs/http-signatures-php compat
    // // public function testLegacyIsValid()
    // // {
    // //     error_reporting(error_reporting() & ~E_USER_DEPRECATED);
    // //     $this->assertTrue($this->verifier->isValid($this->signedAndAuthorizedMessage));
    // //     error_reporting(error_reporting() | E_USER_DEPRECATED);
    // // }
    //
    // // /**
    // //  * @expectedException \PHPUnit_Framework_Error
    // //  */
    // // public function testLegacyIsValidEmitsDeprecatedWarning()
    // // {
    // //     $this->assertTrue($this->verifier->isValid($this->signedAndAuthorizedMessage));
    // // }
    //
    // public function testRejectOnlySignatureHeaderAsAuthorized()
    // {
    //     $this->assertFalse(
    //       $this->verifier->isAuthorized($this->signedMessage)
    //     );
    //     $this->assertEquals(
    //       'Authorization header not found',
    //       $this->verifier->getStatus()[0]
    //     );
    //     $this->verifier->isAuthorized($this->signedMessage);
    //     $this->assertEquals(
    //       1,
    //       sizeof($this->verifier->getStatus())
    //     );
    // }
    //
    // public function testRejectOnlyAuthorizationHeaderAsSigned()
    // {
    //     $this->assertFalse(
    //     $this->verifier->isSigned($this->authorizedMessage)
    //   );
    //     $this->assertEquals(
    //     'Signature header malformed',
    //     $this->verifier->getStatus()[0]
    //   );
    //     $this->verifier->isSigned($this->authorizedMessage);
    //     $this->assertEquals(
    //     1,
    //     sizeof($this->verifier->getStatus())
    //   );
    // }
    //
    // public function testRejectTamperedRequestMethod()
    // {
    //     $message = $this->signedMessage->withMethod('POST');
    //     $this->assertFalse($this->verifier->isSigned($message));
    //     $this->assertEquals(
    //       'Invalid signature',
    //       $this->verifier->getStatus()[0]
    //     );
    //     $this->verifier->isSigned($message);
    //     $this->assertEquals(
    //       1,
    //       sizeof($this->verifier->getStatus())
    //     );
    // }
    //
    // public function testRejectTamperedDate()
    // {
    //     $message = $this->signedMessage->withHeader('Date', self::DATE_DIFFERENT);
    //     $this->assertFalse($this->verifier->isSigned($message));
    //     $this->assertEquals(
    //       'Invalid signature',
    //       $this->verifier->getStatus()[0]
    //     );
    // }
    //
    // public function testRejectTamperedSignature()
    // {
    //     $message = $this->signedMessage->withHeader(
    //         'Signature',
    //         preg_replace('/signature="/', 'signature="x', $this->signedMessage->getHeader('Signature')[0])
    //     );
    //     $this->assertFalse($this->verifier->isSigned($message));
    //     $this->assertEquals(
    //       'Invalid signature',
    //       $this->verifier->getStatus()[0]
    //     );
    // }
    //
    // public function testRejectMessageWithoutSignatureHeader()
    // {
    //     $message = $this->signedMessage->withoutHeader('Signature');
    //     $this->assertFalse($this->verifier->isSigned($message));
    //     $this->assertEquals(
    //       'Signature header not found',
    //       $this->verifier->getStatus()[0]
    //     );
    //     $this->verifier->isSigned($message);
    //     $this->assertEquals(
    //       1,
    //       sizeof($this->verifier->getStatus())
    //     );
    // }
    //
    // public function testRejectMessageWithGarbageSignatureHeader()
    // {
    //     $message = $this->signedMessage->withHeader('Signature', 'not="a",valid="signature"');
    //     $this->assertFalse($this->verifier->isSigned($message));
    //     $this->assertEquals(
    //       'Signature header malformed',
    //       $this->verifier->getStatus()[0]
    //     );
    //     $this->verifier->isSigned($message);
    //     $this->assertEquals(
    //       1,
    //       sizeof($this->verifier->getStatus())
    //     );
    // }
    //
    // public function testRejectMessageWithPartialSignatureHeader()
    // {
    //     $message = $this->signedMessage->withHeader('Signature', 'keyId="aa",algorithm="bb"');
    //     $this->assertFalse($this->verifier->isSigned($message));
    //     $this->assertEquals(
    //       'Signature header malformed',
    //       $this->verifier->getStatus()[0]
    //     );
    // }
    //
    // public function testRejectsMessageWithUnknownKeyId()
    // {
    //     $keyStore = new KeyStore(['nope' => 'secret']);
    //     $verifier = new Verifier($keyStore);
    //     $this->assertFalse($verifier->isSigned($this->signedMessage));
    //     $this->assertEquals(
    //       "Cannot locate key for supplied keyId 'pda'",
    //       $verifier->getStatus()[0]
    //     );
    //     $verifier->isSigned($this->signedMessage);
    //     $this->assertEquals(
    //       1,
    //       sizeof($verifier->getStatus())
    //     );
    // }
    //
    // public function testRejectsMessageMissingSignedHeaders()
    // {
    //     $message = $this->signedMessage->withoutHeader('Date');
    //     $this->assertFalse($this->verifier->isSigned($message));
    //     $this->assertEquals(
    //       "Header 'date' not in message",
    //       $this->verifier->getStatus()[0]
    //     );
    // }
    //
    // public function testGetSigningString()
    // {
    //     $this->assertTrue($this->verifier->isSigned($this->signedMessage));
    //     $this->assertEquals(
    //     "Signed with SigningString 'KHJlcXVlc3QtdGFyZ2V0KTogZ2V0IC9wYXRoP3F1ZXJ5PTEyMwpkYXRlOiBGcmksIDAxIEF1ZyAyMDE0IDEzOjQ0OjMyIC0wNzAw'",
    //     $this->verifier->getStatus()[0]
    //   );
    // }
}