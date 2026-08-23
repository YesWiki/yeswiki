package yeswiki

import (
	"os"
	"path/filepath"
	"strings"
	"testing"
)

func TestABareServeReachesNoCertificateAuthority(t *testing.T) {
	file := Caddyfile("/wikis/mine", Listen{}, Workers{})

	if !strings.Contains(file, "auto_https off") {
		t.Fatal("a first run must not involve a certificate authority")
	}
	if !strings.Contains(file, "http://localhost:8080 {") {
		t.Fatalf("the default site is not localhost:8080:\n%s", file)
	}
}

func TestNamingADomainTurnsCertificatesOn(t *testing.T) {
	file := Caddyfile("/wikis/mine", Listen{Domain: "wiki.example.org"}, Workers{})

	if strings.Contains(file, "auto_https off") {
		t.Fatal("a named domain is the one case where Caddy should get a certificate")
	}
	if !strings.Contains(file, "wiki.example.org {") {
		t.Fatalf("the domain is not the site address:\n%s", file)
	}
}

func TestTheRulesThatMustSurviveAreThere(t *testing.T) {
	file := Caddyfile("/wikis/mine", Listen{}, Workers{})

	for what, expected := range map[string]string{
		"the front controller fallback":  "try_files {path} {path}/ /index.php",
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

	file, err := CaddyfileFor(instance, Listen{}, Workers{})
	if err != nil {
		t.Fatal(err)
	}
	if file != own {
		t.Fatalf("the instance's own Caddyfile was not used:\n%s", file)
	}
}

func TestWithoutAnOverrideTheShippedRulesAreUsed(t *testing.T) {
	file, err := CaddyfileFor(t.TempDir(), Listen{}, Workers{})
	if err != nil {
		t.Fatal(err)
	}
	if !strings.Contains(file, "handle @private") {
		t.Fatal("the shipped rules are not what came back")
	}
}

func TestPrivateIsRefusedBeforeAnythingRewritesTheRequest(t *testing.T) {
	file := Caddyfile("/wikis/mine", Listen{}, Workers{})

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

func TestWorkersAreOnByDefaultAndPointAtTheProgram(t *testing.T) {
	file := Caddyfile("/wikis/mine", Listen{}, Workers{Program: "/opt/yeswiki/program-4.5.0"})

	if !strings.Contains(file, "frankenphp {") {
		t.Fatalf("worker mode is the reason to use FrankenPHP at all:\n%s", file)
	}
	if !strings.Contains(file, "file /opt/yeswiki/program-4.5.0/worker.php") {
		t.Fatalf("the worker script is not the Program's:\n%s", file)
	}
}

func TestClassicModeStaysAvailable(t *testing.T) {
	file := Caddyfile("/wikis/mine", Listen{}, Workers{Classic: true, Program: "/opt/yeswiki/program-4.5.0"})

	if strings.Contains(file, "frankenphp {") {
		t.Fatalf("--classic must rebuild the wiki per request, which is how a field report gets compared:\n%s", file)
	}
}

func TestTheWorkerCountIsStatedOnlyWhenAsked(t *testing.T) {
	file := Caddyfile("/wikis/mine", Listen{}, Workers{Program: "/p", Count: 4})
	if !strings.Contains(file, "num 4") {
		t.Fatalf("the count was not carried:\n%s", file)
	}

	file = Caddyfile("/wikis/mine", Listen{}, Workers{Program: "/p"})
	if strings.Contains(file, "num ") {
		t.Fatalf("with no count, FrankenPHP picks one for the machine:\n%s", file)
	}
}
