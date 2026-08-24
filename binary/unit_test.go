package yeswiki

import (
	"strings"
	"testing"
)

func aUnit() Unit {
	return Unit{Binary: "/usr/local/bin/yeswiki", Farm: "/srv/wikis"}
}

// The admin endpoint is unauthenticated by design and PHP runs as threads inside this process, so any wiki's code can reach it.
func TestTheShippedUnitNeverAsksForAnAdminEndpoint(t *testing.T) {
	if strings.Contains(aUnit().Service(), "--admin") {
		t.Fatal("a unit that starts at boot must not expose the admin API")
	}
}

func TestTheUnitCanBindTheRealPortsWithoutBeingRoot(t *testing.T) {
	service := aUnit().Service()

	for _, directive := range []string{
		"User=yeswiki",
		"AmbientCapabilities=CAP_NET_BIND_SERVICE",
		"CapabilityBoundingSet=CAP_NET_BIND_SERVICE",
		"NoNewPrivileges=true",
		"PrivateTmp=true",
		"ProtectSystem=full",
		"ProtectHome=true",
		"ReadWritePaths=/srv/wikis",
		"StateDirectory=yeswiki",
	} {
		if !strings.Contains(service, directive) {
			t.Errorf("%s is missing:\n%s", directive, service)
		}
	}
}

func TestTheUnitServesTheFarmAndReloadsIt(t *testing.T) {
	service := aUnit().Service()

	if !strings.Contains(service, "ExecStart=/usr/local/bin/yeswiki serve --farm /srv/wikis") {
		t.Errorf("the unit does not serve the farm:\n%s", service)
	}
	if !strings.Contains(service, "ExecReload=/bin/sh -c 'kill -HUP $MAINPID'") {
		t.Errorf("a wiki added to the farm needs a reload:\n%s", service)
	}
	if strings.Contains(service, "/bin/kill") {
		t.Error("/bin/kill is not on every distribution, NixOS among them")
	}
}

// The certificate store outlives any one start, and the service does not run as a user with a home.
func TestTheCertificateStoreIsSomewhereThatSurvives(t *testing.T) {
	service := aUnit().Service()

	if !strings.Contains(service, "Environment=XDG_DATA_HOME=/var/lib/yeswiki") {
		t.Errorf("Caddy would put its certificates in a home this user does not have:\n%s", service)
	}
}

func TestAnotherAccountCanBeNamed(t *testing.T) {
	unit := aUnit()
	unit.User = "wikis"

	service := unit.Service()
	if !strings.Contains(service, "User=wikis") || !strings.Contains(service, "Group=wikis") {
		t.Errorf("the named account is not used:\n%s", service)
	}
	if !strings.Contains(unit.Steps(), "useradd --system --home-dir /srv/wikis --shell /usr/sbin/nologin wikis") {
		t.Errorf("the steps should create the account that was named:\n%s", unit.Steps())
	}
}

func TestTheProgramRootIsPassedOnWhenItWasStated(t *testing.T) {
	if strings.Contains(aUnit().Service(), "YESWIKI_PROGRAM_ROOT") {
		t.Fatal("an unstated program root should not be pinned in the unit")
	}

	unit := aUnit()
	unit.ProgramRoot = "/opt/yeswiki"
	if !strings.Contains(unit.Service(), "Environment=YESWIKI_PROGRAM_ROOT=/opt/yeswiki") {
		t.Fatalf("the stated program root is missing:\n%s", unit.Service())
	}
}

func TestTheStepsSayHowToStartAndHowToAddAWiki(t *testing.T) {
	steps := aUnit().Steps()

	for _, expected := range []string{
		"/etc/systemd/system/yeswiki.service",
		"systemctl daemon-reload",
		"systemctl enable --now yeswiki",
		"systemctl reload yeswiki",
	} {
		if !strings.Contains(steps, expected) {
			t.Errorf("%q is missing from the steps:\n%s", expected, steps)
		}
	}
}
