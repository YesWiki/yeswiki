# Test keys

These RSA keys sign the ID tokens and the SAML assertions of the mock identity provider:
`tests/e2e/helpers/idp.ts` sends them to MockServer each time a spec configures it. They are
public, so they protect nothing: never use them outside the development and test stacks.

To replace one pair, run:

```
openssl req -x509 -newkey rsa:2048 -nodes -keyout <name>.key -out <name>.crt -days 36500 -subj /CN=yeswiki-test-<name>
```
