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
