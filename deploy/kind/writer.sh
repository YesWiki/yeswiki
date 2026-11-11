#!/bin/sh
# Saves pages through the Service until /tmp/stop appears, retrying a 503 as a replica asks, then reads every confirmed save back.
# Run in a curl pod by run.sh, with WIKI (the Service's address) and PASSWORD (WikiAdmin's); prints one line per attempt, save and loss.
set -u

jar=/tmp/jar
curl -s -c "$jar" -b "$jar" -o /dev/null -d username=WikiAdmin --data-urlencode "password=$PASSWORD" "$WIKI/?api/login"
token="$(curl -s -c "$jar" -b "$jar" "$WIKI/?PagePrincipale" | sed -n 's/.*"antiCsrfToken":"\([^"]*\)".*/\1/p')"
[ -n "$token" ] || { echo "no session token: the login failed"; exit 1; }

i=0
while [ ! -f /tmp/stop ]; do
    i=$((i + 1))
    code=000
    for _ in $(seq 60); do
        code="$(curl -s -o /dev/null -w '%{http_code}' --max-time 20 -c "$jar" -b "$jar" -H "X-CSRF-Token: $token" \
            --data-urlencode "body=rolling write $i" "$WIKI/?api/pages/RollingWrite$i")"
        echo "attempt $i $code"
        [ "$code" = 503 ] || break
        sleep 1
    done
    [ "$code" = 200 ] && echo "saved $i"
    sleep 0.2
done

for n in $(seq "$i"); do
    if curl -s "$WIKI/?api/pages/RollingWrite$n" | grep -q "rolling write $n\""; then
        echo "kept $n"
    fi
done
echo "wrote $i"
