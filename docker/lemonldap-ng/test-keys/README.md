# Test keys

These RSA keys sign the SAML responses and the OIDC tokens of the development portal
(`docker/lemonldap-ng/conf/lmConf-1.json` holds a copy of them). They are public, so they
protect nothing: never use them outside the development and test stacks.

To replace them, run `openssl genrsa` and `openssl req -x509` again and paste the results
into the portal's configuration.
