<?php

namespace HttpSignatures;

use Psr\Http\Message\RequestInterface;

class Verification
{
    /** @var RequestInterface */
    private $message;

    /** @var KeyStoreInterface */
    private $keyStore;

    /** @var string */
    private $header;

    /** @var array */
    private $parameters;

    /**
     * @param RequestInterface  $message
     * @param KeyStoreInterface $keyStore
     */
    public function __construct($message, KeyStoreInterface $keyStore, string $header)
    {
        $this->message = $message;
        $this->keyStore = $keyStore;

        switch (strtolower($header)) {
            case 'signature':
                if (0 == sizeof($message->getHeader('Signature'))) {
                    throw new HeaderException("Cannot locate header 'Signature'");
                } elseif (sizeof($message->getHeader('Signature')) > 1) {
                    throw new HeaderException("Multiple headers named 'Signature'");
                }
                $headerParameterString = $message->getHeader('Signature')[0];
                break;
            case 'authorization':
            if (0 == sizeof($message->getHeader('Authorization'))) {
                throw new HeaderException("Cannot locate header 'Authorization'");
            } elseif (sizeof($message->getHeader('Authorization')) > 1) {
                throw new HeaderException("Multiple headers named 'Authorization'");
            }
                $headerParameterString = substr($message->getHeader('Authorization')[0], strlen('Signature '));
                break;
            default:
                throw new HeaderException("Unknown header type '".$header."', cannot verify");
                break;
        }
        $signatureParametersParser = new SignatureParametersParser(
          $headerParameterString
        );
        $this->parameters = $signatureParametersParser->parse();
    }

    /**
     * @return bool
     */
    public function verify()
    {
        try {
            $key = $this->key();
            switch ($key->getType()) {
                case 'secret':
                  return hash_equals(
                    $this->expectedSignatureBase64(),
                    $this->providedSignatureBase64()
                    );
                case 'asymmetric':
                    $signedString = new SigningString(
                        $this->headerList(),
                        $this->message
                    );
                    $hashAlgo = explode('-', $this->parameter('algorithm'))[1];
                    $algorithm = new RsaAlgorithm($hashAlgo);
                    $result = $algorithm->verify(
                        $signedString->string(),
                        $this->parameter('signature'),
                        $key->getVerifyingKey());

                    return $result;
                default:
                    throw new Exception("Unknown key type '".$key->getType()."', cannot verify");
            }
        } catch (SignatureParseException $e) {
            return false;
        } catch (KeyStoreException $e) {
            return false;
        } catch (SignedHeaderNotPresentException $e) {
            return false;
        }
    }

    /**
     * @return string
     */
    private function expectedSignatureBase64()
    {
        return base64_encode($this->expectedSignature()->string());
    }

    /**
     * @return Signature
     */
    private function expectedSignature()
    {
        return new Signature(
            $this->message,
            $this->keyId(),
            $this->algorithm(),
            $this->headerList()
        );
    }

    /**
     * @return string
     */
    private function providedSignatureBase64()
    {
        return $this->headerParameter('signature');
    }

    /**
     * @return Key
     *
     * @throws Exception
     */
    private function keyId()
    {
        return $this->keyStore->fetch($this->headerParameter('keyId'));
    }

    /**
     * @return Algorithm
     *
     * @throws Exception
     */
    private function algorithm()
    {
        return Algorithm::create($this->headerParameter('algorithm'));
    }

    /**
     * @return HeaderList
     */
    private function signatureHeaderList()
    {
        return HeaderList::fromString($this->signatureHeaderParameter('headers'));
    }

    /**
     * @param string $name
     *
     * @return string
     *
     * @throws Exception
     */
    private function headerParameter($name)
    {
        // $headerParameters = $this->headerParameters();
        if (!isset($this->parameters[$name])) {
            throw new Exception("'$this->header' header parameters does not contain '$name'");
        }

        return $this->parameters[$name];
    }

    /**
     * @return string
     */
    private function signatureHeaderValue($headerName)
    {
        $headerLine = $this->fetchHeader($headerName);
        switch ($headerName) {
            case 'Authorization':
                return substr($headerLine, strlen('Signature '));
                break;
            case 'Signature':
                return $headerLine;
                break;
        }
    }

    /**
     * @param $name
     *
     * @return string|null
     */
    private function fetchHeader($name)
    {
        // grab the most recently set header.
        $header = $this->message->getHeader($name);

        return end($header);
    }

    /**
     * @return Key
     */
    private function key()
    {
        return $this->keyStore->fetch($this->parameter('keyId'));
    }

    /**
     * @param string $name
     *
     * @return string
     *
     * @throws Exception
     */
    private function parameter($name)
    {
        // $parameters = $this->parameters();
        if (!isset($this->parameters[$name])) {
            throw new Exception("Signature parameters does not contain '$name'");
        }

        return $this->parameters[$name];
    }

    /**
     * @return string
     *
     * @throws Exception
     */
    private function header()
    {
        switch ($this->header) {
          case 'Signature':
            return $this->fetchHeader('Signature');
            break;
          case 'Authorization':
            return substr($authorization, strlen('Signature '));
            break;
        }
    }

    /**
     * @return HeaderList
     */
    private function headerList()
    {
        return HeaderList::fromString($this->parameter('headers'));
    }
}
