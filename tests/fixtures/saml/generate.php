<?php

/**
 * Writes the SAML responses SamlProviderTest reads, signed with idp.key, the identity provider's test key.
 * They are committed: run this again only to change them, with `php tests/fixtures/saml/generate.php`.
 *
 * Every response but the expired one is valid until 2099, so the fixtures do not age.
 * The keys were made with `openssl req -x509 -newkey rsa:2048 -nodes -keyout idp.key -out idp.crt -days 36500 -subj /CN=yeswiki-test-saml-idp`
 * (and the same for sp). They are public and protect nothing: never use them outside the tests.
 */

use OneLogin\Saml2\Constants;
use RobRichards\XMLSecLibs\XMLSecurityDSig;
use RobRichards\XMLSecLibs\XMLSecurityKey;

require dirname(__DIR__, 3) . '/vendor/autoload.php';

// what SamlProviderTest configures
const SP_ENTITY_ID = 'http://wiki.yeswiki.test/?user/sign-in/saml-a/metadata';
const ACS_URL = 'http://wiki.yeswiki.test/?user/sign-in/saml-a/callback';
const IDP_ENTITY_ID = 'http://idp.yeswiki.test/saml/metadata';
const REQUEST_ID = 'ONELOGIN_0123456789abcdef0123456789abcdef01234567';

$dir = __DIR__;
$valid = [
    'destination' => ACS_URL,
    'audience' => SP_ENTITY_ID,
    'issued' => '2026-11-10T08:00:00Z',
    'not_before' => '2026-01-01T00:00:00Z',
    'not_on_or_after' => '2099-12-31T23:59:59Z',
    'status' => Constants::STATUS_SUCCESS,
    'signed' => true,
];

$fixtures = [
    'response-valid.xml' => $valid,
    'response-unsigned.xml' => ['signed' => false] + $valid,
    'response-other-audience.xml' => ['audience' => 'http://wiki.yeswiki.test/?user/sign-in/saml-b/metadata'] + $valid,
    'response-other-destination.xml' => ['destination' => 'http://wiki.yeswiki.test/?user/sign-in/saml-b/callback'] + $valid,
    'response-expired.xml' => ['issued' => '2020-01-01T08:00:00Z', 'not_before' => '2020-01-01T07:55:00Z', 'not_on_or_after' => '2020-01-01T08:05:00Z'] + $valid,
];

foreach ($fixtures as $file => $values) {
    file_put_contents("$dir/$file", response($values, (string)file_get_contents("$dir/idp.key"), (string)file_get_contents("$dir/idp.crt")));
}

// the identity provider refused: a status and no assertion, which nothing needs to sign
$request = REQUEST_ID;
$idp = IDP_ENTITY_ID;
file_put_contents("$dir/response-status-failure.xml", <<<XML
    <?xml version="1.0" encoding="UTF-8"?>
    <samlp:Response xmlns:samlp="urn:oasis:names:tc:SAML:2.0:protocol" xmlns:saml="urn:oasis:names:tc:SAML:2.0:assertion" ID="_response-refused" Version="2.0" IssueInstant="2026-11-10T08:00:00Z" Destination="{$valid['destination']}" InResponseTo="$request"><saml:Issuer>$idp</saml:Issuer><samlp:Status><samlp:StatusCode Value="urn:oasis:names:tc:SAML:2.0:status:Responder"><samlp:StatusCode Value="urn:oasis:names:tc:SAML:2.0:status:AuthnFailed"/></samlp:StatusCode><samlp:StatusMessage>The user cancelled</samlp:StatusMessage></samlp:Status></samlp:Response>

    XML);

// a valid response, preceded by a document type that asks for a file of the server
$xxe = (string)file_get_contents("$dir/response-valid.xml");
$xxe = preg_replace('/^<\?xml[^>]*>\s*/', '', $xxe);
$xxe = str_replace('<saml:AttributeValue xsi:type="xs:string">marie</saml:AttributeValue>', '<saml:AttributeValue xsi:type="xs:string">&xxe;</saml:AttributeValue>', (string)$xxe);
file_put_contents("$dir/response-xxe.xml", "<?xml version=\"1.0\" encoding=\"UTF-8\"?>\n<!DOCTYPE samlp:Response [<!ENTITY xxe SYSTEM \"file:///etc/passwd\">]>\n" . $xxe);

/** @param array{destination: string, audience: string, issued: string, not_before: string, not_on_or_after: string, status: string, signed: bool} $v */
function response(array $v, string $key, string $certificate): string
{
    $request = REQUEST_ID;
    $idp = IDP_ENTITY_ID;
    $attributes = '';
    foreach (['uid' => ['marie'], 'mail' => ['marie.dupont@acme.example'], 'givenName' => ['Marie'], 'sn' => ['Dupont'], 'groups' => ['wiki-editors', 'staff']] as $name => $values) {
        $attributes .= '<saml:Attribute Name="' . $name . '" NameFormat="urn:oasis:names:tc:SAML:2.0:attrname-format:basic">';
        foreach ($values as $value) {
            $attributes .= '<saml:AttributeValue xsi:type="xs:string">' . $value . '</saml:AttributeValue>';
        }
        $attributes .= '</saml:Attribute>';
    }

    $xml = <<<XML
        <?xml version="1.0" encoding="UTF-8"?>
        <samlp:Response xmlns:samlp="urn:oasis:names:tc:SAML:2.0:protocol" xmlns:saml="urn:oasis:names:tc:SAML:2.0:assertion" ID="_response-1" Version="2.0" IssueInstant="{$v['issued']}" Destination="{$v['destination']}" InResponseTo="$request"><saml:Issuer>$idp</saml:Issuer><samlp:Status><samlp:StatusCode Value="{$v['status']}"/></samlp:Status><saml:Assertion xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance" xmlns:xs="http://www.w3.org/2001/XMLSchema" ID="_assertion-1" Version="2.0" IssueInstant="{$v['issued']}"><saml:Issuer>$idp</saml:Issuer><saml:Subject><saml:NameID Format="urn:oasis:names:tc:SAML:1.1:nameid-format:unspecified">marie</saml:NameID><saml:SubjectConfirmation Method="urn:oasis:names:tc:SAML:2.0:cm:bearer"><saml:SubjectConfirmationData NotOnOrAfter="{$v['not_on_or_after']}" Recipient="{$v['destination']}" InResponseTo="$request"/></saml:SubjectConfirmation></saml:Subject><saml:Conditions NotBefore="{$v['not_before']}" NotOnOrAfter="{$v['not_on_or_after']}"><saml:AudienceRestriction><saml:Audience>{$v['audience']}</saml:Audience></saml:AudienceRestriction></saml:Conditions><saml:AuthnStatement AuthnInstant="{$v['issued']}" SessionIndex="_session-1"><saml:AuthnContext><saml:AuthnContextClassRef>urn:oasis:names:tc:SAML:2.0:ac:classes:PasswordProtectedTransport</saml:AuthnContextClassRef></saml:AuthnContext></saml:AuthnStatement><saml:AttributeStatement>$attributes</saml:AttributeStatement></saml:Assertion></samlp:Response>

        XML;
    if (!$v['signed']) {
        return $xml;
    }

    $document = new DOMDocument();
    $document->loadXML($xml);
    $assertion = $document->getElementsByTagNameNS(Constants::NS_SAML, 'Assertion')->item(0);
    $issuer = $assertion?->firstChild;
    if (!$assertion instanceof DOMElement || $issuer === null) {
        throw new RuntimeException('the assertion has no issuer');
    }

    $privateKey = new XMLSecurityKey(XMLSecurityKey::RSA_SHA256, ['type' => 'private']);
    $privateKey->loadKey($key);
    $signature = new XMLSecurityDSig();
    $signature->setCanonicalMethod(XMLSecurityDSig::EXC_C14N);
    $signature->addReference($assertion, XMLSecurityDSig::SHA256, ['http://www.w3.org/2000/09/xmldsig#enveloped-signature', XMLSecurityDSig::EXC_C14N], ['id_name' => 'ID', 'overwrite' => false]);
    $signature->sign($privateKey);
    $signature->add509Cert($certificate);
    // the schema wants the signature right after the issuer
    $signature->insertSignature($assertion, $issuer->nextSibling);

    return (string)$document->saveXML();
}
