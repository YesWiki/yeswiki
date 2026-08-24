package commands

import (
	"errors"
	"io/fs"
	"os"
	"path/filepath"
	"strings"
	"testing"
	"testing/fstest"

	"github.com/YesWiki/yeswiki/binary/internal/program"
)

type recordingPHP struct {
	instance   string
	programDir string
	arguments  []string
	err        error
}

func (r *recordingPHP) Console(instance, programDir string, arguments []string) error {
	r.instance, r.programDir, r.arguments = instance, programDir, arguments

	return r.err
}

type recordingServer struct {
	instance   string
	programDir string
}

func (r *recordingServer) Serve(instance, programDir string) error {
	r.instance, r.programDir = instance, programDir

	return nil
}

func options(t *testing.T, directory, root string) Options {
	t.Helper()

	return Options{
		Directory:   directory,
		ProgramRoot: root,
		Version:     "4.5.0",
		Source:      fstest.MapFS{"index.php": {Data: []byte("<?php")}},
		Env:         func(string) string { return "" },
		Home:        func() (string, error) { return "", errors.New("no home in a test") },
		Wd:          os.Getwd,
	}
}

func TestSetupWritesTheProgramProvisionsTheInstanceAndInstalls(t *testing.T) {
	root := t.TempDir()
	instance := filepath.Join(t.TempDir(), "mywiki")
	php := &recordingPHP{}

	if err := Setup(options(t, instance, root), php, []string{"--driver=sqlite"}); err != nil {
		t.Fatal(err)
	}

	if !program.Complete(filepath.Join(root, "program-4.5.0"), "4.5.0") {
		t.Fatal("the program was not written")
	}
	if _, err := os.Stat(filepath.Join(instance, "index.php")); err != nil {
		t.Fatal("the instance was not provisioned")
	}
	if php.instance != instance {
		t.Fatalf("the installer ran against %q", php.instance)
	}
	if len(php.arguments) != 2 || php.arguments[0] != "core:install" || php.arguments[1] != "--driver=sqlite" {
		t.Fatalf("the installer was called with %v", php.arguments)
	}
}

func TestSetupRefusesToInstallOverAWikiThatIsAlreadyThere(t *testing.T) {
	root := t.TempDir()
	instance := filepath.Join(t.TempDir(), "mywiki")
	if err := os.MkdirAll(instance, 0o755); err != nil {
		t.Fatal(err)
	}
	config := []byte("<?php\n$yeswikiConfig = ['base_url' => 'http://already.test/?'];\n")
	if err := os.WriteFile(filepath.Join(instance, "yeswiki.config.php"), config, 0o644); err != nil {
		t.Fatal(err)
	}

	err := Setup(options(t, instance, root), &recordingPHP{}, nil)
	if err == nil || !strings.Contains(err.Error(), "already holds a wiki") {
		t.Fatalf("got %v", err)
	}
}

func TestServeRebuildsAProgramThatWasDeleted(t *testing.T) {
	root := t.TempDir()
	instance := filepath.Join(t.TempDir(), "mywiki")
	if err := os.MkdirAll(instance, 0o755); err != nil {
		t.Fatal(err)
	}
	config := []byte("<?php\n$yeswikiConfig = ['base_url' => 'http://mywiki.test/?'];\n")
	if err := os.WriteFile(filepath.Join(instance, "yeswiki.config.php"), config, 0o644); err != nil {
		t.Fatal(err)
	}

	server := &recordingServer{}
	if err := Serve(options(t, instance, root), server); err != nil {
		t.Fatal(err)
	}
	if err := os.RemoveAll(filepath.Join(root, "program-4.5.0")); err != nil {
		t.Fatal(err)
	}
	if err := Serve(options(t, instance, root), server); err != nil {
		t.Fatal(err)
	}

	if !program.Complete(filepath.Join(root, "program-4.5.0"), "4.5.0") {
		t.Fatal("serve must write a program that is not there")
	}
	if server.instance != instance {
		t.Fatalf("served %q", server.instance)
	}
}

func TestServeRefusesAFolderThatIsNotAWikiYet(t *testing.T) {
	err := Serve(options(t, t.TempDir(), t.TempDir()), &recordingServer{})
	if err == nil || !strings.Contains(err.Error(), "yeswiki setup") {
		t.Fatalf("the message must say what to do, got %v", err)
	}
}

func TestAMissingProgramIsFatalAndSaysSo(t *testing.T) {
	opts := options(t, t.TempDir(), t.TempDir())
	opts.Source = brokenFS{}

	err := Setup(opts, &recordingPHP{}, nil)
	if err == nil || !errors.Is(err, program.Missing) {
		t.Fatalf("got %v", err)
	}
}

type brokenFS struct{}

func (brokenFS) Open(string) (fs.File, error) { return nil, errors.New("no program in this binary") }

func TestBothRootsAreStatedForThePhpProcess(t *testing.T) {
	environment := Environment("/wikis/mine", "/opt/yeswiki/program-4.5.0")

	for _, expected := range []string{
		program.EnvInstance + "=/wikis/mine",
		program.EnvProgram + "=/opt/yeswiki/program-4.5.0",
		program.EnvConfigFile + "=/wikis/mine/yeswiki.config.php",
	} {
		found := false
		for _, entry := range environment {
			if entry == expected {
				found = true
			}
		}
		if !found {
			t.Fatalf("%s is not stated", expected)
		}
	}
}

type recordingFarm struct {
	farm       string
	wikis      []program.Wiki
	programDir string
}

func (r *recordingFarm) ServeFarm(farm string, wikis []program.Wiki, programDir string) error {
	r.farm, r.wikis, r.programDir = farm, wikis, programDir

	return nil
}

func installedWiki(t *testing.T, farm, name, host string) string {
	t.Helper()

	directory := filepath.Join(farm, name)
	if err := os.MkdirAll(directory, 0o755); err != nil {
		t.Fatal(err)
	}
	configuration := "<?php\nreturn ['base_url' => 'https://" + host + "/?'];\n"
	if err := os.WriteFile(filepath.Join(directory, "yeswiki.config.php"), []byte(configuration), 0o644); err != nil {
		t.Fatal(err)
	}

	return directory
}

func TestAFarmServesEveryWikiInTheDirectory(t *testing.T) {
	farm := t.TempDir()
	installedWiki(t, farm, "alpha", "alpha.example.org")
	installedWiki(t, farm, "beta", "beta.example.org")
	server := &recordingFarm{}

	if err := ServeFarm(options(t, farm, t.TempDir()), server); err != nil {
		t.Fatal(err)
	}

	if len(server.wikis) != 2 {
		t.Fatalf("expected both wikis, got %v", server.wikis)
	}
	if server.farm != farm {
		t.Errorf("the farm directory is %s, expected %s", server.farm, farm)
	}
}

// Each wiki needs the entry point its own worker resolves to before the farm can be served.
func TestServingAFarmProvisionsEveryWikisEntryPoints(t *testing.T) {
	farm := t.TempDir()
	alpha := installedWiki(t, farm, "alpha", "alpha.example.org")

	if err := ServeFarm(options(t, farm, t.TempDir()), &recordingFarm{}); err != nil {
		t.Fatal(err)
	}

	for _, entry := range []string{"index.php", "worker.php"} {
		if _, err := os.Stat(filepath.Join(alpha, entry)); err != nil {
			t.Errorf("%s was not provisioned: %v", entry, err)
		}
	}
}

func TestWhatIsSkippedIsSaidOutLoud(t *testing.T) {
	farm := t.TempDir()
	installedWiki(t, farm, "alpha", "alpha.example.org")
	if err := os.MkdirAll(filepath.Join(farm, "halfway"), 0o755); err != nil {
		t.Fatal(err)
	}

	said := []string{}
	settings := options(t, farm, t.TempDir())
	settings.Out = func(message string) { said = append(said, message) }

	if err := ServeFarm(settings, &recordingFarm{}); err != nil {
		t.Fatal(err)
	}

	if !strings.Contains(strings.Join(said, "\n"), "skipping halfway") {
		t.Errorf("a directory that is not a wiki should be named, not silently dropped: %v", said)
	}
}

func TestAnEmptyFarmSaysHowToMakeAWiki(t *testing.T) {
	said := []string{}
	settings := options(t, t.TempDir(), t.TempDir())
	settings.Out = func(message string) { said = append(said, message) }

	if err := ServeFarm(settings, &recordingFarm{}); err != nil {
		t.Fatal(err)
	}

	if !strings.Contains(strings.Join(said, "\n"), "yeswiki setup") {
		t.Errorf("an empty farm should say what to do next: %v", said)
	}
}

func TestInstallingBesideOtherWikisSaysToReloadTheFarm(t *testing.T) {
	farm := t.TempDir()
	installedWiki(t, farm, "alpha", "alpha.example.org")

	said := []string{}
	settings := options(t, filepath.Join(farm, "beta"), t.TempDir())
	settings.Out = func(message string) { said = append(said, message) }

	if err := Setup(settings, &recordingPHP{}, nil); err != nil {
		t.Fatal(err)
	}

	if !strings.Contains(strings.Join(said, "\n"), "systemctl reload yeswiki") {
		t.Errorf("a wiki installed into a farm is not served until it is reloaded: %v", said)
	}
}

func TestInstallingOnItsOwnSaysNothingAboutSystemd(t *testing.T) {
	said := []string{}
	settings := options(t, filepath.Join(t.TempDir(), "onlywiki"), t.TempDir())
	settings.Out = func(message string) { said = append(said, message) }

	if err := Setup(settings, &recordingPHP{}, nil); err != nil {
		t.Fatal(err)
	}

	if strings.Contains(strings.Join(said, "\n"), "systemctl") {
		t.Errorf("a wiki with no neighbours is not a farm: %v", said)
	}
}

type migratingPHP struct {
	failOn string
	ran    []string
}

func (m *migratingPHP) Console(instance, programDir string, arguments []string) error {
	m.ran = append(m.ran, filepath.Base(instance)+":"+strings.Join(arguments, " "))
	if m.failOn != "" && filepath.Base(instance) == m.failOn {
		return errors.New("migration 20240101 failed")
	}

	return nil
}

func TestUpgradeTakesEveryWikiAcross(t *testing.T) {
	farm := t.TempDir()
	alpha := installedWiki(t, farm, "alpha", "alpha.example.org")
	beta := installedWiki(t, farm, "beta", "beta.example.org")
	root := t.TempDir()
	php := &migratingPHP{}

	settings := options(t, farm, root)
	if err := Upgrade(settings, php, true); err != nil {
		t.Fatal(err)
	}

	if len(php.ran) != 2 {
		t.Fatalf("both wikis should have been migrated: %v", php.ran)
	}
	for _, ran := range php.ran {
		if !strings.HasSuffix(ran, ":migrate") {
			t.Errorf("expected a migrate, got %q", ran)
		}
	}

	programDir, _ := program.Ensure(settings.Source, root, settings.Version)
	for _, instance := range []string{alpha, beta} {
		if named, found := program.NamedBy(instance); !found || named != programDir {
			t.Errorf("%s still names %q, not the new program", instance, named)
		}
	}
}

// A wiki is served again only once it has been migrated and repointed.
func TestAWikiStaysClosedUntilTheFarmReloads(t *testing.T) {
	farm := t.TempDir()
	alpha := installedWiki(t, farm, "alpha", "alpha.example.org")

	if err := Upgrade(options(t, farm, t.TempDir()), &migratingPHP{}, true); err != nil {
		t.Fatal(err)
	}

	if !program.DoorClosed(alpha) {
		t.Fatal("a migrated wiki must not be served by the old program still running")
	}
}

func TestAFailedMigrationStopsTheRunAndNamesTheWiki(t *testing.T) {
	farm := t.TempDir()
	installedWiki(t, farm, "alpha", "alpha.example.org")
	beta := installedWiki(t, farm, "beta", "beta.example.org")
	installedWiki(t, farm, "gamma", "gamma.example.org")

	err := Upgrade(options(t, farm, t.TempDir()), &migratingPHP{failOn: "beta"}, true)
	if err == nil {
		t.Fatal("a failed migration must stop the run")
	}
	if !strings.Contains(err.Error(), "beta.example.org") {
		t.Errorf("the error should name the wiki: %v", err)
	}
	if !strings.Contains(err.Error(), "migration 20240101 failed") {
		t.Errorf("the error should say why: %v", err)
	}

	if named, found := program.NamedBy(beta); found {
		t.Errorf("a wiki that failed to migrate must not be pointed at a program, but it names %q", named)
	}
	if !program.DoorClosed(beta) {
		t.Error("a wiki that failed to migrate must stay closed")
	}
}

// Running it again after fixing whatever broke should pick up where it stopped, which it can.
func TestUpgradingAgainSkipsTheWikisAlreadyAcross(t *testing.T) {
	farm := t.TempDir()
	installedWiki(t, farm, "alpha", "alpha.example.org")
	installedWiki(t, farm, "beta", "beta.example.org")
	root := t.TempDir()

	if err := Upgrade(options(t, farm, root), &migratingPHP{}, true); err != nil {
		t.Fatal(err)
	}

	again := &migratingPHP{}
	if err := Upgrade(options(t, farm, root), again, true); err != nil {
		t.Fatal(err)
	}

	if len(again.ran) != 0 {
		t.Errorf("nothing should have been migrated twice: %v", again.ran)
	}
}

// The farm must refuse to serve a wiki that never crossed.
func TestEnrolClosesAWikiThatRunsAnotherProgram(t *testing.T) {
	farm := t.TempDir()
	alpha := installedWiki(t, farm, "alpha", "alpha.example.org")
	if err := program.PointAt(alpha, filepath.Join(t.TempDir(), "some-older-program")); err != nil {
		t.Fatal(err)
	}
	installedWiki(t, farm, "beta", "beta.example.org")

	settings := options(t, farm, t.TempDir())
	programDir, _ := program.Ensure(settings.Source, settings.ProgramRoot, settings.Version)

	wikis, err := Enrol(settings, farm, programDir)
	if err != nil {
		t.Fatal(err)
	}

	for _, wiki := range wikis {
		if wiki.Host == "alpha.example.org" && !wiki.Closed {
			t.Error("a wiki naming another program must not be served")
		}
		if wiki.Host == "beta.example.org" && wiki.Closed {
			t.Error("a wiki with no program of its own yet is provisioned with this one, and served")
		}
	}
}

func TestRollingBackPointsEveryWikiAtTheOldProgramAndOpensIt(t *testing.T) {
	farm := t.TempDir()
	alpha := installedWiki(t, farm, "alpha", "alpha.example.org")
	root := t.TempDir()
	settings := options(t, farm, root)

	if err := Upgrade(settings, &migratingPHP{}, true); err != nil {
		t.Fatal(err)
	}

	old := filepath.Join(t.TempDir(), "program-before")
	if err := os.MkdirAll(filepath.Join(old, "src", "commands"), 0o755); err != nil {
		t.Fatal(err)
	}
	if err := os.WriteFile(filepath.Join(old, "src", "commands", "console"), []byte("<?php"), 0o755); err != nil {
		t.Fatal(err)
	}

	if err := RollBack(settings, old); err != nil {
		t.Fatal(err)
	}

	if named, _ := program.NamedBy(alpha); named != old {
		t.Errorf("alpha names %q, expected the old program", named)
	}
	if program.DoorClosed(alpha) {
		t.Error("a wiki that has gone back should be served again")
	}
}

func TestRollingBackToSomethingThatIsNotAProgramIsRefused(t *testing.T) {
	farm := t.TempDir()
	installedWiki(t, farm, "alpha", "alpha.example.org")

	err := RollBack(options(t, farm, t.TempDir()), filepath.Join(t.TempDir(), "not-a-program"))
	if err == nil {
		t.Fatal("pointing wikis at a directory with no program in it must be refused")
	}
	if !strings.Contains(err.Error(), "does not hold a program") {
		t.Errorf("the error should say what is wrong: %v", err)
	}
}

// One `setup --from-wiki` is an install and then a clone.
func TestSetupFromAWikiInstallsThenClones(t *testing.T) {
	instance := filepath.Join(t.TempDir(), "mywiki")
	php := &recordingSteps{}

	err := Setup(options(t, instance, t.TempDir()), php, []string{
		"--driver=sqlite", "--from-wiki=https://old.example.org", "--remote-admin=WikiAdmin",
	})
	if err != nil {
		t.Fatal(err)
	}

	if len(php.steps) != 2 {
		t.Fatalf("expected an install and a clone, got %v", php.steps)
	}
	if !strings.HasPrefix(php.steps[0], "core:install") || !strings.HasPrefix(php.steps[1], "core:clone") {
		t.Fatalf("wrong order: %v", php.steps)
	}
	if strings.Contains(php.steps[0], "--from-wiki") {
		t.Error("the installer should not be handed the clone's options")
	}
	if !strings.Contains(php.steps[1], "--from-wiki=https://old.example.org") ||
		!strings.Contains(php.steps[1], "--remote-admin=WikiAdmin") {
		t.Errorf("the clone was not told where to clone from: %v", php.steps)
	}
	if strings.Contains(php.steps[1], "--driver=sqlite") {
		t.Error("the clone should not be handed the installer's options")
	}
}

func TestAnOrdinarySetupRunsOnlyTheInstaller(t *testing.T) {
	php := &recordingSteps{}

	if err := Setup(options(t, filepath.Join(t.TempDir(), "mywiki"), t.TempDir()), php, []string{"--driver=sqlite"}); err != nil {
		t.Fatal(err)
	}

	if len(php.steps) != 1 {
		t.Fatalf("a wiki with nothing to clone from should only be installed: %v", php.steps)
	}
}

// A wiki that installed but could not be filled is not a wiki anybody wants silently.
func TestAFailedCloneIsReportedAgainstTheWikiThatWasInstalled(t *testing.T) {
	instance := filepath.Join(t.TempDir(), "mywiki")
	php := &recordingSteps{failFrom: 1}

	err := Setup(options(t, instance, t.TempDir()), php, []string{"--from-wiki=https://old.example.org"})
	if err == nil {
		t.Fatal("a failed clone must be reported")
	}
	if !strings.Contains(err.Error(), "installed but not filled") {
		t.Errorf("the error should say what state the wiki is in: %v", err)
	}
}

type recordingSteps struct {
	steps    []string
	failFrom int
}

func (r *recordingSteps) Console(_, _ string, arguments []string) error {
	r.steps = append(r.steps, strings.Join(arguments, " "))
	if r.failFrom > 0 && len(r.steps) > r.failFrom {
		return errors.New("the remote wiki refused these credentials")
	}

	return nil
}

// Every command writes the shim, because the binary can move and the shim names it absolutely.
func TestEveryCommandLeavesAShimBackgroundJobsCanUse(t *testing.T) {
	root := t.TempDir()
	settings := options(t, filepath.Join(t.TempDir(), "mywiki"), root)
	settings.Self = func() (string, error) { return "/usr/local/bin/yeswiki", nil }

	if err := Setup(settings, &recordingPHP{}, nil); err != nil {
		t.Fatal(err)
	}

	written, err := os.ReadFile(program.ShimPath(root))
	if err != nil {
		t.Fatalf("no shim was written: %v", err)
	}
	if !strings.Contains(string(written), "/usr/local/bin/yeswiki") {
		t.Errorf("the shim does not name this binary:\n%s", written)
	}
}

// A console the binary starts must be told where the shim is, or the wiki it boots looks for a php that is not on this machine.
func TestTheEnvironmentPointsAtTheShim(t *testing.T) {
	environment := Environment("/srv/wikis/mine", "/opt/yeswiki/program-v4.6.6")

	found := ""
	for _, entry := range environment {
		if strings.HasPrefix(entry, program.EnvAsyncPHP+"=") {
			found = strings.TrimPrefix(entry, program.EnvAsyncPHP+"=")
		}
	}

	if found != "/opt/yeswiki/php" {
		t.Errorf("%s is %q, expected the shim beside the programs", program.EnvAsyncPHP, found)
	}
}

// Serving a farm resolves the Program too, and a farm is exactly where background jobs matter.
func TestServingAFarmAlsoLeavesTheShim(t *testing.T) {
	farm := t.TempDir()
	installedWiki(t, farm, "alpha", "alpha.example.org")
	root := t.TempDir()

	settings := options(t, farm, root)
	settings.Self = func() (string, error) { return "/usr/local/bin/yeswiki", nil }

	if err := ServeFarm(settings, &recordingFarm{}); err != nil {
		t.Fatal(err)
	}

	if _, err := os.Stat(program.ShimPath(root)); err != nil {
		t.Fatalf("a farm with no shim serves wikis that cannot archive themselves: %v", err)
	}
}
