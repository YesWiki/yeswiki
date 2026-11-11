<?php

namespace YesWiki\Test\Identity\SignIn;

use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBag;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use YesWiki\Identity\Service\SignIn\Type\SamlType;
use YesWiki\Identity\SignIn\Contract\FailureReason;
use YesWiki\Identity\SignIn\Contract\Handshake;
use YesWiki\Identity\SignIn\Contract\HandshakeMode;
use YesWiki\Identity\SignIn\Contract\ProviderConfig;
use YesWiki\Identity\SignIn\Contract\ProviderFailure;
use YesWiki\Identity\SignIn\Contract\PublishesMetadata;
use YesWiki\Identity\SignIn\Provider\SamlProvider;
use YesWiki\Kernel\Service\CurrentRequest;
use YesWiki\Kernel\Service\LanguageService;
use YesWiki\Kernel\Service\PageContext;
use YesWiki\Kernel\Service\UrlFormatter;

/**
 * The responses under tests/fixtures/saml/ are signed with its idp.key, and name the addresses below:
 * tests/fixtures/saml/generate.php writes them again when they must change.
 */
class SamlProviderTest extends TestCase
{
    private const FIXTURES = __DIR__ . '/../../../fixtures/saml/';
    private const BASE_URL = 'http://wiki.yeswiki.test/';
    private const SP_ENTITY_ID = 'http://wiki.yeswiki.test/?user/sign-in/saml-a/metadata';
    private const ACS_URL = 'http://wiki.yeswiki.test/?user/sign-in/saml-a/callback';
    private const IDP_ENTITY_ID = 'http://idp.yeswiki.test/saml/metadata';
    private const IDP_SSO_URL = 'http://idp.yeswiki.test/saml/singleSignOn';
    /** The InResponseTo of every fixture. */
    private const REQUEST_ID = 'ONELOGIN_0123456789abcdef0123456789abcdef01234567';

    /** @var array<string, mixed> */
    private array $server = [];

    protected function setUp(): void
    {
        // its file defines _t(), which the labels of copyableValues() use
        class_exists(LanguageService::class);
        // php-saml compares where a response was sent with the address of the request it reads
        $this->server = $_SERVER;
        $_SERVER['HTTP_HOST'] = 'wiki.yeswiki.test';
        $_SERVER['REQUEST_URI'] = '/?user/sign-in/saml-a/callback';
        $_SERVER['QUERY_STRING'] = 'user/sign-in/saml-a/callback';
    }

    protected function tearDown(): void
    {
        $_SERVER = $this->server;
    }

    /** @param array<string, mixed> $connection added to, or replacing, the identity provider's values */
    private function config(array $connection = []): ProviderConfig
    {
        return ProviderConfig::fromArray('saml-a', [
            'type' => 'saml',
            'label' => 'Acme SAML',
            'connection' => $connection + [
                'idp_entity_id' => self::IDP_ENTITY_ID,
                'idp_sso_url' => self::IDP_SSO_URL,
                'idp_certificate' => self::fixture('idp.crt'),
            ],
        ]);
    }

    /** @param array<string, mixed> $connection */
    private function provider(array $connection = [], ?MockHttpClient $http = null): SamlProvider
    {
        return new SamlProvider($this->config($connection), self::SP_ENTITY_ID, self::ACS_URL, self::BASE_URL, $http);
    }

    /** @return array<string, string> the service provider's own pair */
    private static function key(): array
    {
        return ['sp_certificate' => self::fixture('sp.crt'), 'sp_private_key' => self::fixture('sp.key')];
    }

    private static function fixture(string $name): string
    {
        return (string)file_get_contents(self::FIXTURES . $name);
    }

    private static function handshake(?string $requestId = self::REQUEST_ID): Handshake
    {
        $handshake = new Handshake('saml-a', self::BASE_URL, self::ACS_URL, HandshakeMode::Real, time() + 600);
        if ($requestId !== null) {
            $handshake->set('request_id', $requestId);
        }

        return $handshake;
    }

    /** What the browser posts to the callback, carrying this fixture. */
    private static function posted(string $fixture): Request
    {
        return Request::create(self::ACS_URL, 'POST', ['SAMLResponse' => base64_encode(self::fixture($fixture))]);
    }

    private function failure(callable $call): ProviderFailure
    {
        try {
            $call();
        } catch (ProviderFailure $failure) {
            return $failure;
        }
        $this->fail('no ProviderFailure was thrown');
    }

    /** @return array<array-key, mixed> the query of the redirect to the identity provider */
    private static function query(string $location): array
    {
        parse_str((string)parse_url($location, PHP_URL_QUERY), $query);

        return $query;
    }

    private static function authnRequest(string $location): \DOMDocument
    {
        $request = self::query($location)['SAMLRequest'] ?? null;
        $document = new \DOMDocument();
        $document->loadXML((string)gzinflate((string)base64_decode(is_string($request) ? $request : '')));

        return $document;
    }

    public function testBeginRedirectsToTheIdpAndKeepsTheRequestId(): void
    {
        $handshake = self::handshake(null);

        $response = $this->provider()->begin($handshake);

        $this->assertInstanceOf(RedirectResponse::class, $response);
        $location = (string)$response->headers->get('Location');
        $this->assertStringStartsWith(self::IDP_SSO_URL . '?', $location);
        $requestId = (string)$handshake->get('request_id');
        $this->assertNotSame('', $requestId);
        $request = self::authnRequest($location)->documentElement;
        $this->assertSame($requestId, $request?->getAttribute('ID'), 'the request the response must answer');
        $this->assertSame(self::ACS_URL, $request->getAttribute('AssertionConsumerServiceURL'));
        $this->assertArrayNotHasKey('Signature', self::query($location), 'without a key, nothing is signed');
    }

    public function testAValidResponseReturnsItsAttributes(): void
    {
        $identity = $this->provider()->complete(self::posted('response-valid.xml'), self::handshake());

        $this->assertSame('marie', $identity->attributes['name_id']);
        $this->assertSame('marie.dupont@acme.example', $identity->attributes['mail']);
        $this->assertSame('Dupont', $identity->attributes['sn']);
        $this->assertSame(['wiki-editors', 'staff'], $identity->attributes['groups']);
    }

    /** With the user ID read from the NameID, a response without one lacks the attribute the account is found by. */
    public function testAMissingNameIdIsAMissingAttributeWhenItIsTheUserId(): void
    {
        foreach (['response-no-nameid.xml', 'response-empty-nameid.xml'] as $fixture) {
            $failure = $this->failure(fn () => $this->provider()->complete(self::posted($fixture), self::handshake()));

            $this->assertSame(FailureReason::MissingAttribute, $failure->reason, $fixture);
            $this->assertStringContainsString('NameID', $failure->adminDetail, $fixture);
        }
    }

    public function testAMissingNameIdIsAnInvalidResponseOtherwise(): void
    {
        $provider = new SamlProvider(
            ProviderConfig::fromArray('saml-a', ['type' => 'saml', 'label' => 'Acme SAML', 'attributes' => ['id' => 'uid'], 'connection' => [
                'idp_entity_id' => self::IDP_ENTITY_ID, 'idp_sso_url' => self::IDP_SSO_URL, 'idp_certificate' => self::fixture('idp.crt'),
            ]]),
            self::SP_ENTITY_ID,
            self::ACS_URL,
            self::BASE_URL,
        );

        $failure = $this->failure(fn () => $provider->complete(self::posted('response-no-nameid.xml'), self::handshake()));

        $this->assertSame(FailureReason::InvalidResponse, $failure->reason);
        $this->assertStringContainsString('NameID', $failure->adminDetail);
    }

    public function testADuplicatedAttributeIsAnInvalidResponse(): void
    {
        $failure = $this->failure(fn () => $this->provider()->complete(self::posted('response-duplicated-attribute.xml'), self::handshake()));

        $this->assertSame(FailureReason::InvalidResponse, $failure->reason);
        $this->assertStringContainsString('mail', $failure->adminDetail);
        $this->assertStringNotContainsString('someone.else@acme.example', $failure->adminDetail);
    }

    public function testAnUnsignedResponseIsRefused(): void
    {
        $failure = $this->failure(fn () => $this->provider()->complete(self::posted('response-unsigned.xml'), self::handshake()));

        $this->assertSame(FailureReason::InvalidResponse, $failure->reason);
        $this->assertStringContainsString('not signed', $failure->adminDetail);
    }

    public function testAResponseSignedByAnotherKeyIsRefused(): void
    {
        $failure = $this->failure(fn () => $this->provider(['idp_certificate' => self::fixture('sp.crt')])->complete(self::posted('response-valid.xml'), self::handshake()));

        $this->assertSame(FailureReason::InvalidResponse, $failure->reason);
    }

    public function testAResponseForAnotherAudienceIsRefused(): void
    {
        $failure = $this->failure(fn () => $this->provider()->complete(self::posted('response-other-audience.xml'), self::handshake()));

        $this->assertSame(FailureReason::InvalidResponse, $failure->reason);
        $this->assertStringContainsString('audience', $failure->adminDetail);
    }

    public function testAResponseSentToAnotherProviderIsRefused(): void
    {
        $failure = $this->failure(fn () => $this->provider()->complete(self::posted('response-other-destination.xml'), self::handshake()));

        $this->assertSame(FailureReason::InvalidResponse, $failure->reason);
        $this->assertStringContainsString('saml-b/callback', $failure->adminDetail);
    }

    public function testAnExpiredResponseIsRefused(): void
    {
        $failure = $this->failure(fn () => $this->provider()->complete(self::posted('response-expired.xml'), self::handshake()));

        $this->assertSame(FailureReason::InvalidResponse, $failure->reason);
    }

    public function testAWrongInResponseToIsRefused(): void
    {
        $failure = $this->failure(fn () => $this->provider()->complete(self::posted('response-valid.xml'), self::handshake('ONELOGIN_another_request')));

        $this->assertSame(FailureReason::InvalidResponse, $failure->reason);
        $this->assertStringContainsString('InResponseTo', $failure->adminDetail);
    }

    public function testAResponseNobodyAskedForIsRefused(): void
    {
        // without the request ID, php-saml would not check InResponseTo at all
        $failure = $this->failure(fn () => $this->provider()->complete(self::posted('response-valid.xml'), self::handshake(null)));

        $this->assertSame(FailureReason::InvalidResponse, $failure->reason);
    }

    public function testNoResponseAtAllIsAnInvalidResponse(): void
    {
        $failure = $this->failure(fn () => $this->provider()->complete(Request::create(self::ACS_URL, 'POST'), self::handshake()));

        $this->assertSame(FailureReason::InvalidResponse, $failure->reason);
    }

    public function testAStatusFailureIsRejected(): void
    {
        $failure = $this->failure(fn () => $this->provider()->complete(self::posted('response-status-failure.xml'), self::handshake()));

        $this->assertSame(FailureReason::ProviderRejected, $failure->reason);
        $this->assertStringContainsString('Responder', $failure->adminDetail);
    }

    /**
     * The fixture is a valid signed response with a document type put in front after signing: the document type is all that is
     * wrong with it, so only the refusal of document types can refuse it.
     */
    public function testAnXxePayloadIsRefused(): void
    {
        $failure = $this->failure(fn () => $this->provider()->complete(self::posted('response-xxe.xml'), self::handshake()));

        $this->assertSame(FailureReason::InvalidResponse, $failure->reason);
        $this->assertStringContainsString('DOCTYPE', $failure->adminDetail, 'refused for its document type');
        $this->assertStringNotContainsString('root:', $failure->adminDetail);
        $this->assertSame([], $failure->attributes);

        $withoutDoctype = (string)preg_replace('/<!DOCTYPE[^\[>]*(\[[^\]]*\])?\s*>\s*/', '', self::fixture('response-xxe.xml'));
        $this->assertStringNotContainsString('<!DOCTYPE', $withoutDoctype);
        $posted = Request::create(self::ACS_URL, 'POST', ['SAMLResponse' => base64_encode($withoutDoctype)]);
        $this->assertSame('marie', $this->provider()->complete($posted, self::handshake())->attributes['name_id'], 'the rest of it is a valid, signed response');
    }

    public function testMetadataContainsTheAcsUrlAndCertificateWhenSet(): void
    {
        $plain = $this->provider()->metadataResponse();
        $this->assertSame(200, $plain->getStatusCode());
        $this->assertStringContainsString('xml', (string)$plain->headers->get('Content-Type'));
        $xml = (string)$plain->getContent();
        $this->assertStringContainsString('entityID="' . self::SP_ENTITY_ID . '"', $xml);
        $this->assertStringContainsString('Location="' . self::ACS_URL . '"', $xml);
        $this->assertStringNotContainsString('X509Certificate', $xml);

        $withKey = (string)$this->provider(self::key())->metadataResponse()->getContent();
        $certificate = preg_replace('/-----[A-Z ]+-----|\s+/', '', self::fixture('sp.crt'));
        $this->assertStringContainsString((string)$certificate, $withKey);
        $this->assertStringContainsString('AuthnRequestsSigned="true"', $withKey);
        $this->assertStringNotContainsString('PRIVATE', $withKey);
    }

    public function testMetadataIsPublishedBeforeTheIdpIsKnown(): void
    {
        $provider = new SamlProvider(ProviderConfig::fromArray('saml-a', ['type' => 'saml']), self::SP_ENTITY_ID, self::ACS_URL, self::BASE_URL);

        $this->assertStringContainsString(self::ACS_URL, (string)$provider->metadataResponse()->getContent());
        $this->assertSame([self::SP_ENTITY_ID, self::ACS_URL], array_values($provider->copyableValues()));
    }

    public function testWithAKeyRequestsAreSigned(): void
    {
        $location = (string)$this->provider(self::key())->begin(self::handshake(null))->headers->get('Location');

        $query = self::query($location);
        $this->assertArrayHasKey('Signature', $query);
        $this->assertArrayHasKey('SigAlg', $query);
    }

    public function testAKeyWithoutItsCertificateIsAConfigurationError(): void
    {
        $failure = $this->failure(fn () => $this->provider(['sp_private_key' => self::fixture('sp.key')])->begin(self::handshake(null)));

        $this->assertSame(FailureReason::ConfigurationInvalid, $failure->reason);
        $this->assertStringNotContainsString('PRIVATE', $failure->adminDetail);
    }

    public function testNoAssertionIsInTheFailureDetail(): void
    {
        foreach (['response-other-audience.xml', 'response-expired.xml', 'response-unsigned.xml', 'response-xxe.xml'] as $fixture) {
            $posted = self::posted($fixture);
            $failure = $this->failure(fn () => $this->provider()->complete($posted, self::handshake()));

            $payload = (string)$posted->request->get('SAMLResponse');
            $this->assertStringNotContainsString(substr($payload, 0, 40), $failure->adminDetail, $fixture);
            $this->assertStringNotContainsString('saml:Assertion', $failure->adminDetail, $fixture);
            $this->assertStringNotContainsString('marie.dupont@acme.example', $failure->adminDetail, $fixture);
        }
    }

    public function testTheIdpIsReadFromItsMetadataXml(): void
    {
        $metadata = self::idpMetadata('http://idp.yeswiki.test/saml/fromXml');
        $provider = new SamlProvider(ProviderConfig::fromArray('saml-a', ['type' => 'saml', 'connection' => ['metadata_xml' => $metadata]]), self::SP_ENTITY_ID, self::ACS_URL, self::BASE_URL);

        $location = (string)$provider->begin(self::handshake(null))->headers->get('Location');
        $this->assertStringStartsWith('http://idp.yeswiki.test/saml/fromXml?', $location);
        $this->assertSame('marie', $provider->complete(self::posted('response-valid.xml'), self::handshake())->attributes['name_id']);
    }

    public function testTheIdpIsReadFromItsMetadataUrlAndExplicitValuesWin(): void
    {
        $fetched = [];
        $http = new MockHttpClient(function (string $method, string $url) use (&$fetched): MockResponse {
            $fetched[] = $url;

            return new MockResponse(self::idpMetadata('http://idp.yeswiki.test/saml/fromUrl'));
        });
        $connection = ['metadata_url' => 'http://idp.yeswiki.test/saml/metadata'];

        $location = (string)(new SamlProvider(ProviderConfig::fromArray('saml-a', ['type' => 'saml', 'connection' => $connection]), self::SP_ENTITY_ID, self::ACS_URL, self::BASE_URL, $http))
            ->begin(self::handshake(null))->headers->get('Location');
        $this->assertStringStartsWith('http://idp.yeswiki.test/saml/fromUrl?', $location);
        $this->assertSame(['http://idp.yeswiki.test/saml/metadata'], $fetched);

        $explicit = new SamlProvider(ProviderConfig::fromArray('saml-a', ['type' => 'saml', 'connection' => $connection + ['idp_sso_url' => self::IDP_SSO_URL]]), self::SP_ENTITY_ID, self::ACS_URL, self::BASE_URL, $http);
        $this->assertStringStartsWith(self::IDP_SSO_URL . '?', (string)$explicit->begin(self::handshake(null))->headers->get('Location'));
    }

    public function testAnUnreachableMetadataUrlIsUnreachable(): void
    {
        $http = new MockHttpClient(fn (): MockResponse => new MockResponse('', ['error' => 'connection refused']));
        $provider = new SamlProvider(ProviderConfig::fromArray('saml-a', ['type' => 'saml', 'connection' => ['metadata_url' => 'http://idp.yeswiki.test/saml/metadata']]), self::SP_ENTITY_ID, self::ACS_URL, self::BASE_URL, $http);

        $this->assertSame(FailureReason::ProviderUnreachable, $this->failure(fn () => $provider->begin(self::handshake(null)))->reason);
    }

    public function testNoIdpAtAllIsAConfigurationError(): void
    {
        $provider = new SamlProvider(ProviderConfig::fromArray('saml-a', ['type' => 'saml']), self::SP_ENTITY_ID, self::ACS_URL, self::BASE_URL);

        $failure = $this->failure(fn () => $provider->begin(self::handshake(null)));
        $this->assertSame(FailureReason::ConfigurationInvalid, $failure->reason);
        $this->assertStringContainsString('metadata_url', $failure->adminDetail);
    }

    public function testTheTypeBuildsAProviderAtTheWikisAddresses(): void
    {
        $urls = new UrlFormatter(new ParameterBag(['base_url' => 'http://wiki.yeswiki.test/?', 'rewrite_mode' => false]), new PageContext(), new CurrentRequest());
        $type = new SamlType($urls);

        $provider = $type->create(ProviderConfig::fromArray('saml-a', ['type' => 'saml'], $type->defaultAttributes()));

        $this->assertInstanceOf(PublishesMetadata::class, $provider);
        $this->assertSame([self::SP_ENTITY_ID, self::ACS_URL], array_values($provider->copyableValues()));
        $this->assertSame('name_id', $type->defaultAttributes()['id']);
        $this->assertSame('mail', $type->defaultAttributes()['email']);
    }

    private static function idpMetadata(string $ssoUrl): string
    {
        $certificate = preg_replace('/-----[A-Z ]+-----|\s+/', '', self::fixture('idp.crt'));

        return '<?xml version="1.0"?><md:EntityDescriptor xmlns:md="urn:oasis:names:tc:SAML:2.0:metadata" xmlns:ds="http://www.w3.org/2000/09/xmldsig#" entityID="' . self::IDP_ENTITY_ID . '">'
            . '<md:IDPSSODescriptor protocolSupportEnumeration="urn:oasis:names:tc:SAML:2.0:protocol">'
            . '<md:KeyDescriptor use="signing"><ds:KeyInfo><ds:X509Data><ds:X509Certificate>' . $certificate . '</ds:X509Certificate></ds:X509Data></ds:KeyInfo></md:KeyDescriptor>'
            . '<md:SingleSignOnService Binding="urn:oasis:names:tc:SAML:2.0:bindings:HTTP-Redirect" Location="' . $ssoUrl . '"/>'
            . '</md:IDPSSODescriptor></md:EntityDescriptor>';
    }
}
