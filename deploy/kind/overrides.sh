#!/bin/sh
# Saves a template override through the Service, reads it on every pod, reverts it, then reads on every pod what lies under it: the shipped template, or OPERATOR's mark.
# Run in a curl pod by run.sh, with WIKI (the Service's address), PASSWORD (WikiAdmin's), PODS (each pod's address), and OPERATOR and THEME when the image ships a custom/templates file and a theme.
set -u

jar=/tmp/jar
host="${WIKI#http://}"
file=core/admin/keywords.twig
mark="KIND-OVERRIDE-$(date +%s)"
curl -s -c "$jar" -b "$jar" -o /dev/null -d username=WikiAdmin --data-urlencode "password=$PASSWORD" "$WIKI/?api/login"
token="$(curl -s -c "$jar" -b "$jar" "$WIKI/?admin/custom-templates" | sed -n 's/.*name="csrf-token" value="\([^"]*\)".*/\1/p' | head -1)"
[ -n "$token" ] || { echo "no form token: the login failed"; exit 1; }

on() {
    curl -s -b "$jar" --connect-to "$host:80:$1" "$WIKI/?$2"
}

beneath() {
    if echo "$1" | grep -q "$mark"; then
        echo stale
    elif [ -n "${OPERATOR:-}" ]; then
        echo "$1" | grep -q "$OPERATOR" && echo operator || echo missed-operator
    else
        echo "$1" | grep -q 'yw-dashboard__canvas' && echo shipped || echo broken
    fi
}

for pod in $PODS; do
    echo "before $(beneath "$(on "$pod" admin/keywords)") $pod"
    if [ -n "${THEME:-}" ]; then
        on "$pod" PagePrincipale/edit | grep -q "<option value=\"$THEME\"" && echo "theme $pod" || echo "no-theme $pod"
    fi
done

code="$(curl -s -o /dev/null -w '%{http_code}' -c "$jar" -b "$jar" --data-urlencode "csrf-token=$token" --data-urlencode "file=$file" \
    --data-urlencode "contents={% extends \"@shipped/dashboard/layout.twig\" %}{% block dashboard_content %}$mark{% endblock %}" \
    "$WIKI/?admin/custom-templates")"
echo "save $code"
for pod in $PODS; do
    if on "$pod" admin/keywords | grep -q "$mark"; then
        echo "overridden $pod"
    else
        echo "missed $pod"
    fi
done

code="$(curl -s -o /dev/null -w '%{http_code}' -c "$jar" -b "$jar" --data-urlencode "csrf-token=$token" --data-urlencode "file=$file" \
    -d revert=1 "$WIKI/?admin/custom-templates")"
echo "revert $code"
for pod in $PODS; do
    echo "after $(beneath "$(on "$pod" admin/keywords)") $pod"
done
