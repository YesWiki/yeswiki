package commands

import (
	"crypto/ed25519"
	"crypto/rand"
	"crypto/sha256"
	"encoding/hex"
	"encoding/json"
	"errors"
	"net/http"
	"net/http/httptest"
	"os"
	"path/filepath"
	"strings"
	"testing"

	"github.com/YesWiki/yeswiki/binary/internal/program"
	"github.com/YesWiki/yeswiki/binary/internal/release"
)

// aChannel serves one release the way repository.yeswiki.net will, signed with a key the test
// holds. Nothing here reaches GitHub, which is ADR-0016's point.
func aChannel(t *testing.T, version string, executable []byte) release.Client {
	t.Helper()

	public, private, err := ed25519.GenerateKey(rand.Reader)
	if err != nil {
		t.Fatal(err)
	}
	digest := sha256.Sum256(executable)

	mux := http.NewServeMux()
	var server *httptest.Server

	mux.HandleFunc("/ectoplasme/binary.json", func(w http.ResponseWriter, _ *http.Request) {
		_ = json.NewEncoder(w).Encode(release.Index{
			Version: version,
			Platforms: map[string]release.Platform{
				release.ThisPlatform(): {
					URL:       server.URL + "/ectoplasme/yeswiki",
					SHA256:    hex.EncodeToString(digest[:]),
					Signature: server.URL + "/ectoplasme/yeswiki.sig",
					Bytes:     int64(len(executable)),
				},
			},
		})
	})
	mux.HandleFunc("/ectoplasme/yeswiki", func(w http.ResponseWriter, _ *http.Request) {
		_, _ = w.Write(executable)
	})
	mux.HandleFunc("/ectoplasme/yeswiki.sig", func(w http.ResponseWriter, _ *http.Request) {
		_, _ = w.Write([]byte(release.Sign(executable, private) + "\n"))
	})

	server = httptest.NewServer(mux)
	t.Cleanup(server.Close)

	return release.Client{Repository: server.URL, Channel: "ectoplasme", Key: public}
}

// selfReplacing is the options a self-update needs: a Program root it may write, and a binary at a
// known path to be replaced.
func selfReplacing(t *testing.T, root, executable string) Options {
	t.Helper()

	settings := options(t, filepath.Join(t.TempDir(), "wiki"), root)
	settings.Self = func() (string, error) { return executable, nil }
	settings.Out = func(string) {}

	return settings
}

func TestAWikiOnVersionNReplacesItselfWithNPlusOne(t *testing.T) {
	root := t.TempDir()
	executable := filepath.Join(t.TempDir(), "yeswiki")
	if err := os.WriteFile(executable, []byte("version 4.5.0"), 0o755); err != nil {
		t.Fatal(err)
	}

	client := aChannel(t, "4.6.0", []byte("version 4.6.0"))

	replaced, err := SelfUpdate(selfReplacing(t, root, executable), client, "4.5.0")
	if err != nil {
		t.Fatal(err)
	}
	if !replaced {
		t.Fatal("a newer version was offered and nothing was replaced")
	}

	installed, _ := os.ReadFile(executable)
	if string(installed) != "version 4.6.0" {
		t.Fatalf("the executable is %q", installed)
	}
	if info, _ := os.Stat(executable); info.Mode().Perm() != 0o755 {
		t.Fatalf("the new executable is %v", info.Mode().Perm())
	}
}

func TestNothingIsFetchedWhenTheRunningVersionIsWhatIsOffered(t *testing.T) {
	root := t.TempDir()
	executable := filepath.Join(t.TempDir(), "yeswiki")
	if err := os.WriteFile(executable, []byte("version 4.6.0"), 0o755); err != nil {
		t.Fatal(err)
	}

	replaced, err := SelfUpdate(selfReplacing(t, root, executable), aChannel(t, "4.6.0", []byte("x")), "4.6.0")
	if err != nil {
		t.Fatal(err)
	}
	if replaced {
		t.Fatal("the running version was installed over itself")
	}

	running, _ := os.ReadFile(executable)
	if string(running) != "version 4.6.0" {
		t.Fatal("the executable was rewritten for no reason")
	}
}

// Done when: the same binary in a container with a read-only Program root refuses with a message
// that names the path, and migrations still run.
func TestAReadOnlyProgramRootRefusesToSelfUpdateAndSaysWhatStillApplies(t *testing.T) {
	if os.Geteuid() == 0 {
		t.Skip("root writes to a read-only directory, so there is nothing to refuse")
	}

	root := filepath.Join(t.TempDir(), "program")
	if err := os.Mkdir(root, 0o500); err != nil {
		t.Fatal(err)
	}
	executable := filepath.Join(t.TempDir(), "yeswiki")
	if err := os.WriteFile(executable, []byte("the image's binary"), 0o755); err != nil {
		t.Fatal(err)
	}

	_, err := SelfUpdate(selfReplacing(t, root, executable), aChannel(t, "4.6.0", []byte("new")), "4.5.0")
	if err == nil {
		t.Fatal("a read-only deployment replaced its own executable")
	}
	if !errors.Is(err, release.ErrReadOnly) {
		t.Fatalf("the refusal is not the read-only one: %v", err)
	}
	if !strings.Contains(err.Error(), root) {
		t.Errorf("the refusal does not name the path: %v", err)
	}
	if !strings.Contains(err.Error(), "yeswiki migrate") {
		t.Errorf("the refusal does not name the command that still applies: %v", err)
	}

	running, _ := os.ReadFile(executable)
	if string(running) != "the image's binary" {
		t.Fatal("the image's binary was replaced")
	}
}

// Migration is separable, and the read-only case is exactly why: the executable comes from the
// image and the schema still has to be taken across, as a job that runs once.
func TestMigrationStillRunsWhereSelfUpdateRefuses(t *testing.T) {
	root := t.TempDir()
	farm := t.TempDir()
	wiki := installedWiki(t, farm, "alpha", "alpha.example.org")
	php := &migratingPHP{}

	settings := options(t, wiki, root)
	if err := Upgrade(settings, php, false); err != nil {
		t.Fatal(err)
	}

	if len(php.ran) != 1 || php.ran[0] != "alpha:migrate" {
		t.Fatalf("ran %v", php.ran)
	}
	if named, found := program.NamedBy(wiki); !found || filepath.Base(named) != "program-4.5.0" {
		t.Fatalf("the wiki was not pointed at the new program: %s %v", named, found)
	}
}

// The hand-over is what makes `upgrade` one command: the new executable writes the new Program,
// because the old process has the old one compiled into it.
func TestTheNewBinaryIsHandedTheMigration(t *testing.T) {
	root := t.TempDir()
	executable := filepath.Join(t.TempDir(), "yeswiki")
	if err := os.WriteFile(executable, []byte("version 4.5.0"), 0o755); err != nil {
		t.Fatal(err)
	}

	handedOver := []string{}
	original := handOver
	handOver = func(path string, argv []string, _ []string) error {
		handedOver = append([]string{path}, argv[1:]...)

		return nil
	}
	t.Cleanup(func() { handOver = original })

	continued, err := ReplaceAndContinue(
		selfReplacing(t, root, executable),
		aChannel(t, "4.6.0", []byte("version 4.6.0")),
		"4.5.0",
		[]string{"migrate", "--farm"},
	)
	if err != nil {
		t.Fatal(err)
	}
	if !continued {
		t.Fatal("the executable was replaced and the caller was told nothing happened")
	}

	if len(handedOver) != 3 || handedOver[0] != executable {
		t.Fatalf("handed over %v", handedOver)
	}
	if handedOver[1] != "migrate" {
		t.Fatalf("the new binary was asked to %q, and asking it to upgrade would send it looking for a newer release than the one just installed", handedOver[1])
	}
	if installed, _ := os.ReadFile(executable); string(installed) != "version 4.6.0" {
		t.Fatal("the hand-over happened without the replacement")
	}
}

func TestNothingIsHandedOverWhenThereWasNothingToInstall(t *testing.T) {
	root := t.TempDir()
	executable := filepath.Join(t.TempDir(), "yeswiki")
	_ = os.WriteFile(executable, []byte("version 4.6.0"), 0o755)

	handed := false
	original := handOver
	handOver = func(string, []string, []string) error { handed = true; return nil }
	t.Cleanup(func() { handOver = original })

	handedOver, err := ReplaceAndContinue(selfReplacing(t, root, executable), aChannel(t, "4.6.0", []byte("x")), "4.6.0", []string{"migrate"})
	if err != nil {
		t.Fatal(err)
	}
	if handed {
		t.Fatal("the process was replaced for an upgrade that did not happen")
	}

	// The caller decides what to do next on this, and it decides wrong if this lies. A binary
	// that is already current has migrated nothing, so `upgrade` still owes the wikis a
	// migration -- reporting a hand-over here would end the command with the work undone.
	if handedOver {
		t.Fatal("nothing was installed, and the caller was told the new binary took over")
	}
}

// Done when: the old Program is gone afterwards -- pruned at the next serve, keeping one spare.
func TestServingAfterAnUpgradePrunesTheProgramsNothingUses(t *testing.T) {
	root := t.TempDir()
	farm := t.TempDir()
	wiki := installedWiki(t, farm, "alpha", "alpha.example.org")

	for _, old := range []string{"4.1.0", "4.2.0", "4.3.0"} {
		if _, err := program.Ensure(options(t, wiki, root).Source, root, old); err != nil {
			t.Fatal(err)
		}
	}

	settings := options(t, wiki, root)
	settings.Out = func(string) {}
	if err := Upgrade(settings, &migratingPHP{}, false); err != nil {
		t.Fatal(err)
	}

	server := &recordingServer{}
	if err := Serve(settings, server); err != nil {
		t.Fatal(err)
	}

	if _, err := os.Stat(program.Dir(root, "4.5.0")); err != nil {
		t.Fatal("the Program being served was pruned")
	}
	if _, err := os.Stat(program.Dir(root, "4.3.0")); err != nil {
		t.Error("the newest spare should survive, so `upgrade --back-to` has somewhere to go")
	}
	for _, gone := range []string{"4.1.0", "4.2.0"} {
		if _, err := os.Stat(program.Dir(root, gone)); !os.IsNotExist(err) {
			t.Errorf("program-%s should have been pruned", gone)
		}
	}
}

// The third refusal: a wiki whose schema may be behind the code is not served half-migrated.
func TestServingAWikiThatWasMigratedAgainstAnotherProgramIsRefused(t *testing.T) {
	root := t.TempDir()
	farm := t.TempDir()
	wiki := installedWiki(t, farm, "alpha", "alpha.example.org")

	elsewhere, err := program.Ensure(options(t, wiki, root).Source, root, "4.4.0")
	if err != nil {
		t.Fatal(err)
	}
	if err := program.PointAt(wiki, elsewhere); err != nil {
		t.Fatal(err)
	}

	err = Serve(options(t, wiki, root), &recordingServer{})
	if err == nil {
		t.Fatal("a wiki whose schema may be behind the code was served")
	}
	if !strings.Contains(err.Error(), "yeswiki migrate") {
		t.Errorf("the refusal does not name the command that fixes it: %v", err)
	}
	if !strings.Contains(err.Error(), "program-4.4.0") {
		t.Errorf("the refusal does not say what it was migrated against: %v", err)
	}
}
