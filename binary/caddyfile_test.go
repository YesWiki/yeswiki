package yeswiki

import (
	"os"
	"path/filepath"
	"strings"
	"testing"
)

func TestABareServeReachesNoCertificateAuthority(t *testing.T) {
	file := Caddyfile("/wikis/mine", Listen{}, Workers{}, "")

	if !strings.Contains(file, "auto_https off") {
		t.Fatal("a first run must not involve a certificate authority")
	}
	if !strings.Contains(file, "http://localhost:8080 {") {
		t.Fatalf("the default site is not localhost:8080:\n%s", file)
	}
}

func TestNamingADomainTurnsCertificatesOn(t *testing.T) {
	file := Caddyfile("/wikis/mine", Listen{Domain: "wiki.example.org"}, Workers{}, "")

	if strings.Contains(file, "auto_https off") {
		t.Fatal("a named domain is the one case where Caddy should get a certificate")
	}
	if !strings.Contains(file, "wiki.example.org {") {
		t.Fatalf("the domain is not the site address:\n%s", file)
	}
}

func TestTheRulesThatMustSurviveAreThere(t *testing.T) {
	file := Caddyfile("/wikis/mine", Listen{}, Workers{}, "")

	for what, expected := range map[string]string{
		"the front controller fallback":  "try_files {path} {path}/worker.php worker.php",
		"the webfinger redirect":         "redir * /?api/webfinger&{query} 301",
		"the actor rewrite":              `^/actors/(\d+)(/(?:outbox|inbox|followers|following))?$`,
		"the actor query string":         "env QUERY_STRING api/forms/{re.actor.1}/actor{re.actor.2}",
		"the actor request uri":          "env REQUEST_URI /?api/forms/{re.actor.1}/actor{re.actor.2}",
		"private denied by the server":   "respond 403",
		"the upload limit":               "max_size 512MB",
		"the body timeout":               "read_body 300s",
		"long caching on published asse": "path /cache/assets/*",
		"the document root":              "root * /wikis/mine",
	} {
		if !strings.Contains(file, expected) {
			t.Fatalf("%s is missing (%q):\n%s", what, expected, file)
		}
	}
}

func TestAnInstanceCanOverrideTheWholeThing(t *testing.T) {
	instance := t.TempDir()
	own := "# this operator knows what they are doing\n:9999 {\n\trespond \"mine\"\n}\n"
	if err := os.WriteFile(filepath.Join(instance, CaddyfileName), []byte(own), 0o644); err != nil {
		t.Fatal(err)
	}

	file, err := CaddyfileFor(instance, Listen{}, Workers{}, "")
	if err != nil {
		t.Fatal(err)
	}
	if file != own {
		t.Fatalf("the instance's own Caddyfile was not used:\n%s", file)
	}
}

func TestWithoutAnOverrideTheShippedRulesAreUsed(t *testing.T) {
	file, err := CaddyfileFor(t.TempDir(), Listen{}, Workers{}, "")
	if err != nil {
		t.Fatal(err)
	}
	if !strings.Contains(file, "handle @private") {
		t.Fatal("the shipped rules are not what came back")
	}
}

func TestPrivateIsRefusedBeforeAnythingRewritesTheRequest(t *testing.T) {
	file := Caddyfile("/wikis/mine", Listen{}, Workers{}, "")

	private := strings.Index(file, "handle @private")
	actor := strings.Index(file, "handle @actor")
	fallback := strings.Index(file, "try_files {path}")

	if private < 0 || actor < 0 || fallback < 0 {
		t.Fatalf("a rule is missing:\n%s", file)
	}
	if !(private < actor && actor < fallback) {
		t.Fatal("handle blocks run in written order, and the front-controller fallback rewrites every path it is given: it has to come last, or private/ and /actors/ never get their own answer")
	}
}

func TestWorkersAreOnByDefaultAndPointAtTheScriptRequestsResolveTo(t *testing.T) {
	file := Caddyfile("/wikis/mine", Listen{}, Workers{
		Program:  "/opt/yeswiki/program-4.5.0",
		Instance: "/wikis/mine",
	}, "")

	if !strings.Contains(file, "frankenphp {") {
		t.Fatalf("worker mode is the reason to use FrankenPHP at all:\n%s", file)
	}
	if !strings.Contains(file, "file /wikis/mine/worker.php") {
		t.Fatalf("the worker names a script no request resolves to, so it receives none:\n%s", file)
	}
	if strings.Contains(file, "file /opt/yeswiki/program-4.5.0/worker.php") {
		t.Fatalf("the Program's worker.php is not what try_files lands on:\n%s", file)
	}
}

// The bug this pairing exists to prevent: FrankenPHP looks a worker up by resolved script path
// (workersByPath[fc.scriptFilename]), so a worker whose file is not the front controller boots,
// holds an interpreter and serves nothing, while every request goes to a regular thread.
func TestTheWorkerFileIsTheFrontController(t *testing.T) {
	workers := Workers{Program: "/opt/yeswiki/program-4.5.0", Instance: "/wikis/mine"}
	file := Caddyfile("/wikis/mine", Listen{}, workers, "")

	if !strings.Contains(file, "file /wikis/mine/"+workers.FrontController()) {
		t.Fatalf("the worker does not name the front controller:\n%s", file)
	}
	if !strings.Contains(file, "try_files {path} {path}/"+workers.FrontController()+" "+workers.FrontController()) {
		t.Fatalf("try_files does not resolve to the worker's file:\n%s", file)
	}
}

func TestClassicModeServesTheOrdinaryEntryPoint(t *testing.T) {
	file := Caddyfile("/wikis/mine", Listen{}, Workers{Classic: true, Instance: "/wikis/mine"}, "")

	if !strings.Contains(file, "try_files {path} {path}/index.php index.php") {
		t.Fatalf("without a worker there is nothing to route to worker.php:\n%s", file)
	}
}

func TestClassicModeStaysAvailable(t *testing.T) {
	file := Caddyfile("/wikis/mine", Listen{}, Workers{Classic: true, Program: "/opt/yeswiki/program-4.5.0"}, "")

	if strings.Contains(file, "frankenphp {") {
		t.Fatalf("--classic must rebuild the wiki per request, which is how a field report gets compared:\n%s", file)
	}
}

func TestTheWorkerCountIsStatedOnlyWhenAsked(t *testing.T) {
	file := Caddyfile("/wikis/mine", Listen{}, Workers{Program: "/p", Count: 4}, "")
	if !strings.Contains(file, "num 4") {
		t.Fatalf("the count was not carried:\n%s", file)
	}

	file = Caddyfile("/wikis/mine", Listen{}, Workers{Program: "/p"}, "")
	if strings.Contains(file, "num ") {
		t.Fatalf("with no count, FrankenPHP picks one for the machine:\n%s", file)
	}
}

func TestListenAddressFromPort(t *testing.T) {
	address, err := listenAddress("", 8099)
	if err != nil {
		t.Fatalf("--port 8099: %v", err)
	}
	if address != "localhost:8099" {
		t.Errorf("--port 8099 gave %q, want localhost:8099", address)
	}
}

func TestListenAddressKeepsAnExplicitListen(t *testing.T) {
	address, err := listenAddress("0.0.0.0:9000", 0)
	if err != nil {
		t.Fatalf("--listen 0.0.0.0:9000: %v", err)
	}
	if address != "0.0.0.0:9000" {
		t.Errorf("--listen gave %q, want 0.0.0.0:9000", address)
	}
}

func TestListenAddressRefusesBoth(t *testing.T) {
	if _, err := listenAddress("0.0.0.0:9000", 8099); err == nil {
		t.Error("--port and --listen together must be refused, so neither silently wins")
	}
}

func TestCaddyfileServesTheChosenPort(t *testing.T) {
	address, err := listenAddress("", 8099)
	if err != nil {
		t.Fatalf("listenAddress: %v", err)
	}

	config := Caddyfile("/wikis/mine", Listen{Address: address}, Workers{Classic: true}, "")
	if !strings.Contains(config, "http://localhost:8099 {") {
		t.Errorf("the Caddyfile does not serve the chosen port:\n%s", config)
	}
}

func TestTheFrontControllerIsPhpServersOwn(t *testing.T) {
	config := Caddyfile("/wikis/mine", Listen{}, Workers{Classic: true}, "")

	if strings.Contains(config, "try_files {path} {path}/ ") {
		t.Error("`{path}/` is nginx's idiom, where a matched directory falls through to the " +
			"`index` directive. Caddy has no such fallthrough, so a request for / stops at the " +
			"directory and answers 404 instead of reaching index.php.")
	}
	if !strings.Contains(config, "try_files {path} {path}/index.php index.php") {
		t.Errorf("the front controller fallback is not php_server's own:\n%s", config)
	}
}

// Caddy's local admin API is unauthenticated by design, and under FrankenPHP the PHP that would
// abuse it runs inside this very process. It is off unless somebody asks.
func TestTheAdminApiIsOffUnlessAskedFor(t *testing.T) {
	if !strings.Contains(Caddyfile("/wikis/mine", Listen{}, Workers{}, ""), "admin off") {
		t.Error("a served wiki must not expose an admin API every wiki's PHP can reach")
	}

	asked := Caddyfile("/wikis/mine", Listen{}, Workers{}, "127.0.0.1:2019")
	if !strings.Contains(asked, "admin 127.0.0.1:2019") {
		t.Error("--admin must be honoured, or single-binary 07 cannot assert what served a request")
	}
	if strings.Contains(asked, "admin off") {
		t.Error("--admin and admin off must not both be emitted")
	}
}
