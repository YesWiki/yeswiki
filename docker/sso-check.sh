#!/usr/bin/env bash
# Quick check of the identity servers of the development stack. Run it from inside the
# yeswiki-app container. Prints ok or FAIL per line, and exits non-zero on any failure.
set -u

PORTAL=http://auth.yeswiki.test
LDAP_URI=ldap://yeswiki-ldap:1389
MARIE=uid=marie,ou=people,dc=acme,dc=example

failed=0

# $1 is the label, the rest is the command that must succeed.
check() {
    local label=$1
    shift
    if "$@" >/dev/null 2>&1; then
        echo "ok    $label"
    else
        echo "FAIL  $label"
        failed=1
    fi
}

# Succeeds when the page at $1 answers and contains $2.
page_contains() {
    curl -fsS --max-time 10 "$1" | grep -q "$2"
}

php_has_ldap() {
    php -m | grep -qx ldap
}

marie_is_in_wiki_editors() {
    ldapsearch -x -LLL -H "$LDAP_URI" -D "$MARIE" -w TestPassword1 \
        -b ou=groups,dc=acme,dc=example "(member=$MARIE)" cn | grep -q '^cn: wiki-editors$'
}

check 'PHP module ldap' php_has_ldap
check 'Wiki alias wiki.yeswiki.test' curl -fsS --max-time 10 -o /dev/null http://wiki.yeswiki.test/
check 'OIDC discovery' page_contains "$PORTAL/.well-known/openid-configuration" issuer
check 'SAML metadata' page_contains "$PORTAL/saml/metadata" EntityDescriptor
check 'CAS login' curl -fsS --max-time 10 -o /dev/null "$PORTAL/cas/login"
check 'LDAP bind as marie' ldapwhoami -x -H "$LDAP_URI" -D "$MARIE" -w TestPassword1
check 'LDAP groups of marie' marie_is_in_wiki_editors

exit $failed
