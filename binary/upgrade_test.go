package yeswiki

import (
	"strings"
	"testing"

	"github.com/YesWiki/yeswiki/binary/internal/program"
)

// A wiki whose door is closed answers 503 without a reload.
func TestAClosedDoorNeedsNoReload(t *testing.T) {
	file := FarmCaddyfile(threeWikis, Workers{}, "")

	if got := strings.Count(file, "@upgrading file "+program.DoorMarker); got != 3 {
		t.Fatalf("every wiki should check its own marker, found %d:\n%s", got, file)
	}
	if !strings.Contains(file, "This wiki is being upgraded") {
		t.Error("a closed door should say what is happening, not just fail")
	}
}

// A wiki that has not crossed to the Program this process runs must not be served by it.
func TestAWikiThatHasNotCrossedIsNotServed(t *testing.T) {
	behind := []program.Wiki{
		{Directory: "/srv/wikis/alpha", Host: "alpha.example.org", Address: "alpha.example.org"},
		{Directory: "/srv/wikis/beta", Host: "beta.example.org", Address: "beta.example.org", Closed: true, Why: "This wiki has not been upgraded yet."},
	}

	file := FarmCaddyfile(behind, Workers{}, "")

	if !strings.Contains(file, "This wiki has not been upgraded yet.") {
		t.Fatalf("the wiki left behind should be answered, not served:\n%s", file)
	}
	if strings.Contains(file, "root * /srv/wikis/beta") {
		t.Error("a wiki that has not crossed must not be served out of its directory")
	}
	if strings.Contains(file, "file /srv/wikis/beta/worker.php") {
		t.Error("a wiki that has not crossed must not get a worker running the new program")
	}
	if !strings.Contains(file, "root * /srv/wikis/alpha") {
		t.Error("the wiki that did cross should still be served")
	}
}
