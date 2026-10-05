package yeswiki

import (
	"errors"
	"strings"
	"testing"

	"github.com/YesWiki/yeswiki/binary/internal/program"
)

var threeWikis = []program.Wiki{
	{Directory: "/srv/wikis/alpha", Host: "alpha.example.org", Address: "alpha.example.org"},
	{Directory: "/srv/wikis/beta", Host: "beta.example.org", Address: "beta.example.org"},
	{Directory: "/srv/wikis/gamma", Host: "gamma.example.org", Address: "gamma.example.org"},
}

// A wiki is served at the address its own configuration states, port and scheme included.
func TestAWikiIsServedAtTheAddressItsConfigurationStates(t *testing.T) {
	file := FarmCaddyfile([]program.Wiki{
		{Directory: "/srv/wikis/plain", Host: "plain.example.org", Address: "http://plain.example.org:8080"},
		{Directory: "/srv/wikis/high", Host: "high.example.org", Address: "high.example.org:8443"},
	}, Workers{}, "", "")

	if !strings.Contains(file, "http://plain.example.org:8080 {") {
		t.Errorf("an http base_url should be served without a certificate:\n%s", file)
	}
	if !strings.Contains(file, "high.example.org:8443 {") {
		t.Errorf("an https base_url on another port should still get a certificate:\n%s", file)
	}
}

func TestEveryWikiGetsItsOwnSiteAndItsOwnRoot(t *testing.T) {
	file := FarmCaddyfile(threeWikis, Workers{}, "", "")

	for _, wiki := range threeWikis {
		if !strings.Contains(file, wiki.Host+" {") {
			t.Errorf("%s has no site block:\n%s", wiki.Host, file)
		}
		if !strings.Contains(file, "root * "+wiki.Directory) {
			t.Errorf("%s does not serve out of its own directory:\n%s", wiki.Host, file)
		}
	}
}

// FrankenPHP picks a worker by resolved script path, so a farm needs one worker per Instance.
func TestEveryWikiGetsItsOwnWorker(t *testing.T) {
	file := FarmCaddyfile(threeWikis, Workers{}, "", "")

	for _, wiki := range threeWikis {
		if !strings.Contains(file, "file "+wiki.Directory+"/worker.php") {
			t.Errorf("%s has no worker of its own:\n%s", wiki.Host, file)
		}
	}
	if got := strings.Count(file, "worker {"); got != 3 {
		t.Errorf("expected three workers, found %d", got)
	}
}

func TestAFarmKeepsCertificatesOn(t *testing.T) {
	file := FarmCaddyfile(threeWikis, Workers{}, "", "")

	if strings.Contains(file, "auto_https off") {
		t.Fatal("a farm is served on real names, which is the whole reason to obtain certificates")
	}
}

// The admin endpoint is reachable by PHP running in any wiki, because that PHP is threads inside this process.
func TestAFarmHasNoAdminEndpointUnlessAsked(t *testing.T) {
	if !strings.Contains(FarmCaddyfile(threeWikis, Workers{}, "", ""), "admin off") {
		t.Fatal("the generated configuration must turn the admin API off")
	}

	asked := FarmCaddyfile(threeWikis, Workers{}, "localhost:2019", "")
	if !strings.Contains(asked, "admin localhost:2019") || strings.Contains(asked, "admin off") {
		t.Fatalf("--admin should be the only way to get one:\n%s", asked)
	}
}

func TestClassicModeServesAFarmWithoutWorkers(t *testing.T) {
	file := FarmCaddyfile(threeWikis, Workers{Classic: true}, "", "")

	if strings.Contains(file, "worker {") {
		t.Fatal("classic mode runs no workers")
	}
	if !strings.Contains(file, "try_files {path} {path}/index.php index.php") {
		t.Fatalf("classic mode resolves to index.php:\n%s", file)
	}
}

func TestTheRulesEveryWikiNeedsAreInEverySiteBlock(t *testing.T) {
	file := FarmCaddyfile(threeWikis, Workers{}, "", "")

	for what, expected := range map[string]int{
		"private denied by the server": 3,
		"the upload limit":             3,
		"the webfinger redirect":       3,
	} {
		snippet := map[string]string{
			"private denied by the server": "respond 403",
			"the upload limit":             "max_size 512MB",
			"the webfinger redirect":       "redir * /?api/webfinger&{query} 301",
		}[what]
		if got := strings.Count(file, snippet); got != expected {
			t.Errorf("%s appears %d times, expected %d", what, got, expected)
		}
	}
}

func TestAnEmptyFarmIsStillAValidConfiguration(t *testing.T) {
	file := FarmCaddyfile(nil, Workers{}, "", "")

	if !strings.Contains(file, "admin off") {
		t.Fatalf("an empty farm should still be a configuration Caddy can load:\n%s", file)
	}
	if strings.Contains(file, "worker {") {
		t.Fatal("no wikis means no workers")
	}
}

// Caddy binds port 80 to redirect to https.
func TestAFarmOnItsOwnPortDoesNotNeedPortEighty(t *testing.T) {
	onAnotherPort := FarmCaddyfile([]program.Wiki{
		{Directory: "/srv/wikis/high", Host: "high.example.org", Address: "high.example.org:8443"},
	}, Workers{}, "", "")

	if !strings.Contains(onAnotherPort, "auto_https disable_redirects") {
		t.Errorf("nothing is served on 443, so nothing should bind 80:\n%s", onAnotherPort)
	}

	if strings.Contains(FarmCaddyfile(threeWikis, Workers{}, "", ""), "disable_redirects") {
		t.Error("a farm on 443 should keep redirecting http to https")
	}
}

// A farm behind a relay answers plain HTTP on one address, each wiki still matched on its own name.
func TestAFarmGivenAnAddressServesPlainHTTPThere(t *testing.T) {
	file := FarmCaddyfile(threeWikis, Workers{}, "", "42.42.43.123:8130")

	for _, wiki := range threeWikis {
		if !strings.Contains(file, "http://"+wiki.Host+":8130 {\n\tbind 42.42.43.123\n") {
			t.Errorf("%s should be served over http on 8130, bound to the address given:\n%s", wiki.Host, file)
		}
	}
	if !strings.Contains(file, "auto_https off") {
		t.Errorf("behind a relay the farm should ask no certificate authority for anything:\n%s", file)
	}
}

// Only a port leaves the farm listening on every address.
func TestAFarmGivenOnlyAPortBindsEveryAddress(t *testing.T) {
	file := FarmCaddyfile(threeWikis, Workers{}, "", ":8130")

	if !strings.Contains(file, "http://alpha.example.org:8130 {\n\troot") {
		t.Errorf("a bare port should not add a bind:\n%s", file)
	}
}

// A closed wiki behind a relay answers on the same address as the open ones.
func TestAClosedWikiBehindARelayAnswersOnTheRelayAddress(t *testing.T) {
	closed := []program.Wiki{{Directory: "/srv/wikis/shut", Host: "shut.example.org", Address: "shut.example.org", Closed: true}}
	file := FarmCaddyfile(closed, Workers{}, "", ":8130")

	if !strings.Contains(file, "http://shut.example.org:8130 {") {
		t.Errorf("a closed wiki should answer its 503 on the relay's port:\n%s", file)
	}
}

// A worker that cannot start is named by FrankenPHP, and that one wiki is the one closed.
func TestTheWikiWhoseWorkerFailsIsTheOneClosed(t *testing.T) {
	failure := errors.New("loading new config: frankenphp app module: start: failed to initialize workers: too many consecutive failures: worker /srv/wikis/beta/worker.php has not reached frankenphp_handle_request()")

	broken, found := brokenWorker(failure, threeWikis)
	if !found || threeWikis[broken].Host != "beta.example.org" {
		t.Fatalf("expected beta to be named, got %d %v", broken, found)
	}

	closed := append([]program.Wiki(nil), threeWikis...)
	closed[1].Closed = true
	if _, found := brokenWorker(failure, closed); found {
		t.Error("a wiki already closed has no worker left to blame")
	}
	if _, found := brokenWorker(errors.New("listen tcp :8130: bind: address already in use"), threeWikis); found {
		t.Error("a failure that names no worker closes nothing")
	}
}

// Behind a relay the request arrives as plain HTTP, so PHP is told the https its base_url states.
func TestAWikiBehindARelayKnowsItIsServedOverHTTPS(t *testing.T) {
	file := FarmCaddyfile([]program.Wiki{
		{Directory: "/srv/wikis/secure", Host: "secure.example.org", Address: "secure.example.org"},
		{Directory: "/srv/wikis/plain", Host: "plain.example.org", Address: "http://plain.example.org"},
	}, Workers{}, "", ":8130")

	secure := file[strings.Index(file, "http://secure.example.org:8130 {"):strings.Index(file, "http://plain.example.org:8130 {")]
	plain := file[strings.Index(file, "http://plain.example.org:8130 {"):]
	if strings.Count(secure, "env HTTPS on") != 2 {
		t.Errorf("both PHP handlers of an https wiki should say so:\n%s", secure)
	}
	if strings.Contains(plain, "env HTTPS on") {
		t.Errorf("an http wiki is not served over https:\n%s", plain)
	}
	if strings.Contains(FarmCaddyfile(threeWikis, Workers{}, "", ""), "env HTTPS on") {
		t.Error("a farm that terminates TLS itself needs no override")
	}
}
