<?php

namespace YesWiki\Identity\SignIn\Provider;

use OneLogin\Saml2\Auth;
use OneLogin\Saml2\Constants;
use OneLogin\Saml2\IdPMetadataParser;
use OneLogin\Saml2\Response as SamlResponse;
use OneLogin\Saml2\Settings;
use OneLogin\Saml2\Utils;
use OneLogin\Saml2\ValidationError;
use Symfony\Component\HttpClient\HttpClient;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use YesWiki\Identity\SignIn\Contract\FailureReason;
use YesWiki\Identity\SignIn\Contract\Handshake;
use YesWiki\Identity\SignIn\Contract\ProviderConfig;
use YesWiki\Identity\SignIn\Contract\ProviderFailure;
use YesWiki\Identity\SignIn\Contract\ProviderIdentity;
use YesWiki\Identity\SignIn\Contract\PublishesMetadata;
use YesWiki\Identity\SignIn\Contract\RedirectProvider;

/**
 * SAML 2.0 as a service provider: an AuthnRequest by HTTP-Redirect, the response by HTTP-POST to the callback.
 * The assertion must be signed; its audience, time window, InResponseTo and destination are checked, and a document type is refused (php-saml in strict mode).
 * The identity provider is read from its metadata (URL or XML), and its entity ID, sign-on URL and certificate, when written in the configuration, win over it.
 * With a certificate and its private key, requests are signed, encrypted assertions are read and the certificate is in this wiki's metadata.
 *
 * php-saml keeps the wiki's address in statics of Utils: it is set again before each use, so another wiki served by the same worker never leaks into this one.
 * The response is read from the Request, never from $_POST, and nothing of it is kept or written anywhere: failures say what was wrong, not what was sent.
 */
final class SamlProvider implements RedirectProvider, PublishesMetadata
{
    private const TIMEOUT = 10;

    private readonly string $baseUrl;
    private readonly HttpClientInterface $http;

    /**
     * @param string  $spEntityId this wiki's entity ID for this provider: the address of its metadata
     * @param string  $acsUrl     where the identity provider posts its response: the callback
     * @param ?string $baseUrl    the wiki's address, which php-saml compares with where a response was sent; by default the origin of $acsUrl
     */
    public function __construct(
        private readonly ProviderConfig $config,
        private readonly string $spEntityId,
        private readonly string $acsUrl,
        ?string $baseUrl = null,
        ?HttpClientInterface $http = null,
    ) {
        $this->baseUrl = $baseUrl ?? self::origin($acsUrl);
        $this->http = $http ?? HttpClient::create();
        Utils::setBaseURL($this->baseUrl);
    }

    public function id(): string
    {
        return $this->config->id;
    }

    public function label(): string
    {
        return $this->config->label;
    }

    public function type(): string
    {
        return $this->config->type;
    }

    public function begin(Handshake $handshake): Response
    {
        $settings = $this->settings($this->identityProvider());
        $url = $this->library('the sign-in request could not be built', function () use ($settings, $handshake): string {
            $auth = new Auth($settings);
            // the RelayState comes back untouched; the handshake, not it, says where to go next
            $url = $auth->login($this->baseUrl, [], false, false, true);
            $handshake->set('request_id', (string)$auth->getLastRequestID());

            return $url;
        });

        return new RedirectResponse($url);
    }

    public function complete(Request $request, Handshake $handshake): ProviderIdentity
    {
        $requestId = (string)$handshake->get('request_id');
        if ($requestId === '') {
            throw new ProviderFailure(FailureReason::InvalidResponse, 'this sign-in sent no request to the identity provider, so no response was expected');
        }
        $posted = $request->request->all()['SAMLResponse'] ?? null;
        if (!is_string($posted) || $posted === '') {
            throw new ProviderFailure(FailureReason::InvalidResponse, 'the identity provider posted no SAMLResponse');
        }

        $settings = $this->settings($this->identityProvider());
        $settings = $this->library('the identity provider is not configured right', fn (): Settings => new Settings($settings));
        $response = $this->quietly(function () use ($settings, $posted, $requestId): SamlResponse {
            try {
                $response = new SamlResponse($settings, $posted);
            } catch (\Exception $unreadable) {
                throw new ProviderFailure(FailureReason::InvalidResponse, 'the SAML response could not be read: ' . $unreadable->getMessage());
            }
            if (!$response->isValid($requestId)) {
                $error = $response->getErrorException();
                $refused = $error instanceof ValidationError && $error->getCode() === ValidationError::STATUS_CODE_IS_NOT_SUCCESS;

                throw new ProviderFailure($refused ? FailureReason::ProviderRejected : FailureReason::InvalidResponse, 'the SAML response was refused: ' . ($error?->getMessage() ?? 'invalid'));
            }

            return $response;
        });

        // php-saml only compares the start of the address, which every provider of this wiki shares
        $destination = trim($response->document->documentElement?->getAttribute('Destination') ?? '');
        if ($destination !== '' && $destination !== $this->acsUrl) {
            throw new ProviderFailure(FailureReason::InvalidResponse, "the SAML response was sent to $destination, not to {$this->acsUrl}");
        }

        return new ProviderIdentity($this->attributes($response));
    }

    public function metadataResponse(): Response
    {
        $xml = $this->library('the metadata could not be built', function (): string {
            // the identity provider is not needed to describe this side, so the metadata is there before it is configured
            $settings = new Settings($this->settings(null), true);

            return $settings->getSPMetadata(true);
        });

        return new Response($xml, Response::HTTP_OK, ['Content-Type' => 'application/samlmetadata+xml; charset=UTF-8']);
    }

    public function copyableValues(): array
    {
        return [
            _t('SIGN_IN_SAML_SP_ENTITY_ID') => $this->spEntityId,
            _t('SIGN_IN_SAML_ACS_URL') => $this->acsUrl,
        ];
    }

    /**
     * php-saml's settings. Utils keeps the wiki's address in statics, so it is set here, before each use.
     *
     * @param ?array<string, mixed> $idp null for this side alone (the metadata)
     *
     * @return array<string, mixed>
     */
    private function settings(?array $idp): array
    {
        $certificate = self::text($this->config->connection('sp_certificate')) ?? '';
        $key = self::text($this->config->connection('sp_private_key')) ?? '';
        if ($idp !== null && ($certificate === '') !== ($key === '')) {
            throw new ProviderFailure(FailureReason::ConfigurationInvalid, $key === '' ? 'sp_certificate is set without sp_private_key: set both, or neither' : 'sp_private_key is set without sp_certificate: set both, or neither');
        }
        Utils::setBaseURL($this->baseUrl);

        $settings = [
            'strict' => true,
            'debug' => false,
            'baseurl' => $this->baseUrl,
            'sp' => [
                'entityId' => $this->spEntityId,
                'assertionConsumerService' => ['url' => $this->acsUrl, 'binding' => Constants::BINDING_HTTP_POST],
                'NameIDFormat' => Constants::NAMEID_UNSPECIFIED,
                'x509cert' => $certificate,
                'privateKey' => $key,
            ],
            'security' => [
                'authnRequestsSigned' => $certificate !== '' && $key !== '',
                'wantAssertionsSigned' => true,
                'wantMessagesSigned' => false,
                'wantXMLValidation' => true,
                'rejectUnsolicitedResponsesWithInResponseTo' => true,
                // asking for a way of signing in can make an identity provider refuse a person it would otherwise accept
                'requestedAuthnContext' => false,
            ],
        ];
        if ($idp !== null) {
            $settings['idp'] = $idp;
        }

        return $settings;
    }

    /**
     * The identity provider: its metadata, overridden by the values written in the configuration.
     *
     * @return array<string, mixed>
     */
    private function identityProvider(): array
    {
        $xml = self::text($this->config->connection('metadata_xml'));
        $url = self::text($this->config->connection('metadata_url'));
        $from = $xml !== null ? 'metadata_xml' : "the metadata at $url";
        if ($xml === null && $url !== null) {
            $xml = $this->fetch($url);
        }
        $found = $xml === null ? [] : $this->parse($xml, $from);

        $idp = [
            'entityId' => self::text($this->config->connection('idp_entity_id')) ?? self::text($found['entityId'] ?? null),
            'singleSignOnService' => [
                'url' => self::text($this->config->connection('idp_sso_url')) ?? self::text($found['singleSignOnService']['url'] ?? null),
                'binding' => Constants::BINDING_HTTP_REDIRECT,
            ],
        ];
        $certificate = self::text($this->config->connection('idp_certificate'));
        if ($certificate !== null) {
            $idp['x509cert'] = $certificate;
        } elseif (isset($found['x509certMulti']) && is_array($found['x509certMulti'])) {
            $idp['x509certMulti'] = $found['x509certMulti'];
        } else {
            $idp['x509cert'] = self::text($found['x509cert'] ?? null);
        }

        $missing = array_keys(array_filter([
            'idp_entity_id' => $idp['entityId'],
            'idp_sso_url' => $idp['singleSignOnService']['url'],
            'idp_certificate' => $idp['x509cert'] ?? $idp['x509certMulti'] ?? null,
        ], fn (mixed $value): bool => $value === null));
        if ($missing !== [] && $xml === null) {
            throw new ProviderFailure(FailureReason::ConfigurationInvalid, 'no metadata_url or metadata_xml, and no ' . implode(', ', $missing) . ': set metadata_url or metadata_xml, or idp_entity_id, idp_sso_url and idp_certificate');
        }
        if ($missing !== []) {
            throw new ProviderFailure(FailureReason::ConfigurationInvalid, "$from gives no " . implode(', ', array_map(fn (string $name): string => substr($name, 4), $missing)) . ', and the configuration does not either');
        }

        return $idp;
    }

    private function fetch(string $url): string
    {
        try {
            $response = $this->http->request('GET', $url, ['headers' => ['Accept' => 'application/samlmetadata+xml, application/xml, text/xml'], 'timeout' => self::TIMEOUT]);
            $status = $response->getStatusCode();
            $body = $response->getContent(false);
        } catch (TransportExceptionInterface $unreachable) {
            throw new ProviderFailure(FailureReason::ProviderUnreachable, "the metadata at $url could not be fetched: " . $unreachable->getMessage());
        }
        if ($status !== 200) {
            throw new ProviderFailure(FailureReason::ConfigurationInvalid, "the metadata at $url answered HTTP $status");
        }

        return $body;
    }

    /** @return array<string, mixed> what php-saml found of the first identity provider described */
    private function parse(string $xml, string $from): array
    {
        return $this->quietly(function () use ($xml, $from): array {
            try {
                $found = IdPMetadataParser::parseXML($xml);
            } catch (\Exception $unreadable) {
                throw new ProviderFailure(FailureReason::ConfigurationInvalid, "$from could not be read: " . $unreadable->getMessage());
            }
            $idp = $found['idp'] ?? null;

            return is_array($idp) ? $idp : [];
        });
    }

    /**
     * The attributes under their names, those with a friendly name under it too, and the NameID under `name_id`.
     * A value sent once is a string; several stay a list.
     *
     * php-saml refuses, while reading them, an attribute sent in two elements and an assertion with no NameID: a response the wiki
     * cannot read, unless the NameID is the user ID, which is then the attribute missing.
     *
     * @return array<string, mixed>
     */
    private function attributes(SamlResponse $response): array
    {
        try {
            $attributes = [];
            foreach ([$response->getAttributes(), $response->getAttributesWithFriendlyName()] as $named) {
                foreach ($named as $name => $values) {
                    $values = array_values((array)$values);
                    $attributes[(string)$name] ??= count($values) === 1 ? $values[0] : $values;
                }
            }
            $attributes['name_id'] = $response->getNameId();
        } catch (ValidationError $unreadable) {
            throw $this->unreadable($response, $unreadable);
        }

        return $attributes;
    }

    private function unreadable(SamlResponse $response, ValidationError $error): ProviderFailure
    {
        switch ($error->getCode()) {
            case ValidationError::NO_NAMEID:
            case ValidationError::EMPTY_NAMEID:
                $id = $this->config->attributes['id'] ?? 'name_id';
                $said = $error->getCode() === ValidationError::NO_NAMEID ? 'has no NameID' : 'has an empty NameID';

                return $id === 'name_id'
                    ? new ProviderFailure(FailureReason::MissingAttribute, "the SAML response $said, and the user ID is read from it (name_id): the identity provider must send a persistent NameID")
                    : new ProviderFailure(FailureReason::InvalidResponse, "the SAML response $said, which an assertion must have even when the user ID is read from \"$id\"");
            case ValidationError::DUPLICATED_ATTRIBUTE_NAME_FOUND:
                $names = [];
                foreach ($response->document->getElementsByTagNameNS(Constants::NS_SAML, 'Attribute') as $attribute) {
                    $name = $attribute->getAttribute('Name');
                    if ($name !== '') {
                        $names[] = $name;
                    }
                }
                $twice = array_keys(array_filter(array_count_values($names), fn (int $count): bool => $count > 1));

                return new ProviderFailure(FailureReason::InvalidResponse, $twice === []
                    ? 'the SAML response sends an attribute in two Attribute elements'
                    : 'the SAML response sends the attribute "' . implode('", "', $twice) . '" in two Attribute elements: the identity provider must send each attribute once, with all its values');
            default:
                return new ProviderFailure(FailureReason::InvalidResponse, 'the SAML response could not be read: ' . $error->getMessage());
        }
    }

    /**
     * A call into php-saml, whose errors are said for an administrator.
     *
     * @template T
     *
     * @param callable(): T $call
     *
     * @return T
     */
    private function library(string $what, callable $call): mixed
    {
        try {
            return $this->quietly($call);
        } catch (ProviderFailure $failure) {
            throw $failure;
        } catch (\Exception $error) {
            throw new ProviderFailure(FailureReason::ConfigurationInvalid, "$what: " . $error->getMessage());
        }
    }

    /**
     * libxml's warnings would go to the log, quoting what the other side sent: they are kept to libxml and dropped.
     *
     * @template T
     *
     * @param callable(): T $call
     *
     * @return T
     */
    private function quietly(callable $call): mixed
    {
        $previous = libxml_use_internal_errors(true);
        try {
            return $call();
        } finally {
            libxml_clear_errors();
            libxml_use_internal_errors($previous);
        }
    }

    /** The scheme, host and port of an address, with a slash. */
    private static function origin(string $url): string
    {
        $parts = parse_url($url);
        $port = isset($parts['port']) ? ':' . $parts['port'] : '';

        return ($parts['scheme'] ?? 'http') . '://' . ($parts['host'] ?? '') . $port . '/';
    }

    private static function text(mixed $value): ?string
    {
        return is_string($value) && trim($value) !== '' ? trim($value) : null;
    }
}
