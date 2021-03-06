<?php

/*
 * This file is part of the LightSAML-Core package.
 *
 * (c) Milos Tomic <tmilos@lightsaml.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace LightSaml\Model\Protocol;

use LightSaml\Error\LightSamlXmlException;
use LightSaml\Model\Context\DeserializationContext;
use LightSaml\Model\Context\SerializationContext;
use LightSaml\Helper;
use LightSaml\Model\AbstractSamlModel;
use LightSaml\Model\Assertion\Issuer;
use LightSaml\Model\SamlElementInterface;
use LightSaml\Model\XmlDSig\Signature;
use LightSaml\SamlConstants;

abstract class SamlMessage extends AbstractSamlModel
{
    /** @var string */
    protected $id;

    /** @var string */
    protected $version = SamlConstants::VERSION_20;

    /** @var int */
    protected $issueInstant;

    /** @var  string|null */
    protected $destination;

    /** @var Issuer|null */
    protected $issuer;

    /** @var  Signature|null */
    protected $signature;

    /** @var  string|null */
    protected $relayState;

    /**
     * @param string                 $xml
     * @param DeserializationContext $context
     *
     * @return AuthnRequest|LogoutRequest|LogoutResponse|Response|SamlMessage
     *
     * @throws \Exception
     */
    public static function fromXML($xml, DeserializationContext $context)
    {
        if (false == is_string($xml)) {
            throw new \InvalidArgumentException('Expecting string');
        }

        $context->getDocument()->loadXML($xml);

        if (SamlConstants::NS_PROTOCOL !== $context->getDocument()->namespaceURI &&
            SamlConstants::NS_PROTOCOL !== $context->getDocument()->firstChild->namespaceURI
        ) {
            throw new LightSamlXmlException(sprintf(
                "Invalid namespace '%s' of the root XML element, expected '%s'",
                $context->getDocument()->namespaceURI,
                SamlConstants::NS_PROTOCOL
            ));
        }

        $map = array(
            'AttributeQuery' => null,
            'AuthnRequest' => '\LightSaml\Model\Protocol\AuthnRequest',
            'LogoutResponse' => '\LightSaml\Model\Protocol\LogoutResponse',
            'LogoutRequest' => '\LightSaml\Model\Protocol\LogoutRequest',
            'Response' => '\LightSaml\Model\Protocol\Response',
            'ArtifactResponse' => null,
            'ArtifactResolve' => null,
        );

        $rootElementName = $context->getDocument()->firstChild->localName;

        if (array_key_exists($rootElementName, $map)) {
            if ($class = $map[$rootElementName]) {
                /** @var SamlElementInterface $result */
                $result = new $class();
            } else {
                throw new \Exception('Deserialization of %s root element is not implemented');
            }
        } else {
            throw new LightSamlXmlException(sprintf("Unknown SAML message '%s'", $rootElementName));
        }

        $result->deserialize($context->getDocument()->firstChild, $context);

        return $result;
    }

    /**
     * @param string $id
     *
     * @return SamlMessage
     */
    public function setID($id)
    {
        $this->id = (string) $id;

        return $this;
    }

    /**
     * @return string
     */
    public function getID()
    {
        return $this->id;
    }

    /**
     * @param int|string|\DateTime $issueInstant
     *
     * @return SamlMessage
     */
    public function setIssueInstant($issueInstant)
    {
        $this->issueInstant = Helper::getTimestampFromValue($issueInstant);

        return $this;
    }

    /**
     * @return int|null
     */
    public function getIssueInstantTimestamp()
    {
        return $this->issueInstant;
    }

    /**
     * @return string|null
     */
    public function getIssueInstantString()
    {
        if ($this->issueInstant) {
            return Helper::time2string($this->issueInstant);
        }

        return null;
    }

    /**
     * @return \DateTime|null
     */
    public function getIssueInstantDateTime()
    {
        if ($this->issueInstant) {
            return new \DateTime('@'.$this->issueInstant);
        }

        return null;
    }

    /**
     * @param string $version
     *
     * @return SamlMessage
     */
    public function setVersion($version)
    {
        $this->version = (string) $version;

        return $this;
    }

    /**
     * @return string
     */
    public function getVersion()
    {
        return $this->version;
    }

    /**
     * @param null|string $destination
     *
     * @return SamlMessage
     */
    public function setDestination($destination)
    {
        $this->destination = $destination;

        return $this;
    }

    /**
     * @return null|string
     */
    public function getDestination()
    {
        return $this->destination;
    }

    /**
     * @param Issuer|null $issuer
     *
     * @return SamlMessage
     */
    public function setIssuer(Issuer $issuer = null)
    {
        $this->issuer = $issuer;

        return $this;
    }

    /**
     * @return \LightSaml\Model\Assertion\NameID|null
     */
    public function getIssuer()
    {
        return $this->issuer;
    }

    /**
     * @param Signature|null $signature
     *
     * @return SamlMessage
     */
    public function setSignature(Signature $signature = null)
    {
        $this->signature = $signature;

        return $this;
    }

    /**
     * @return Signature|null
     */
    public function getSignature()
    {
        return $this->signature;
    }

    /**
     * @param null|string $relayState
     *
     * @return SamlMessage
     */
    public function setRelayState($relayState)
    {
        $this->relayState = $relayState;

        return $this;
    }

    /**
     * @return null|string
     */
    public function getRelayState()
    {
        return $this->relayState;
    }

    /**
     * @param \DOMNode             $parent
     * @param SerializationContext $context
     *
     * @return void
     */
    public function serialize(\DOMNode $parent, SerializationContext $context)
    {
        $this->attributesToXml(array('Destination'), $parent);

        $this->singleElementsToXml(array('Signature'), $parent, $context);
    }

    /**
     * @param \DOMElement            $node
     * @param DeserializationContext $context
     *
     * @return void
     */
    public function deserialize(\DOMElement $node, DeserializationContext $context)
    {
        $this->attributesFromXml($node, array('ID', 'Version', 'IssueInstant', 'Destination'));

        $this->singleElementsFromXml($node, $context, array(
            'Issuer' => array('saml', 'LightSaml\Model\Assertion\Issuer'),
            'Signature' => array('ds', 'LightSaml\Model\XmlDSig\SignatureXmlReader'),
        ));
    }
}
