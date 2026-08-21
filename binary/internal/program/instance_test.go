package program

import (
	"os"
	"path/filepath"
	"strings"
	"testing"
)

func TestTheWorkingDirectoryIsTheInstanceWhenNoneIsGiven(t *testing.T) {
	instance, err := ResolveInstance("", func() (string, error) { return "/somewhere/mywiki", nil })
	if err != nil || instance != "/somewhere/mywiki" {
		t.Fatalf("got %q, %v", instance, err)
	}
}

func TestAGivenDirectoryIsResolvedAbsolute(t *testing.T) {
	working := t.TempDir()
	if err := os.Chdir(working); err != nil {
		t.Fatal(err)
	}

	instance, err := ResolveInstance("mywiki", os.Getwd)
	if err != nil {
		t.Fatal(err)
	}
	if !filepath.IsAbs(instance) {
		t.Fatalf("%q is relative, and every later path is measured against it", instance)
	}
}

func TestProvisioningCreatesTheDataFoldersAndTheEntryPoint(t *testing.T) {
	instance := filepath.Join(t.TempDir(), "mywiki")
	programDir := "/opt/yeswiki/program-4.5.0"

	if err := ProvisionInstance(instance, programDir); err != nil {
		t.Fatal(err)
	}

	for _, folder := range dataFolders {
		if info, err := os.Stat(filepath.Join(instance, folder)); err != nil || !info.IsDir() {
			t.Fatalf("%s is missing", folder)
		}
	}

	entry, err := os.ReadFile(filepath.Join(instance, "index.php"))
	if err != nil {
		t.Fatal(err)
	}
	for _, expected := range []string{programDir, EnvProgram, EnvInstance, EnvConfigFile} {
		if !strings.Contains(string(entry), expected) {
			t.Fatalf("index.php does not state %s: %s", expected, entry)
		}
	}
}

func TestProvisioningLeavesAnExistingEntryPointAlone(t *testing.T) {
	instance := filepath.Join(t.TempDir(), "mywiki")
	if err := ProvisionInstance(instance, "/opt/yeswiki/program-4.5.0"); err != nil {
		t.Fatal(err)
	}

	custom := []byte("<?php // this operator edited their own entry point\n")
	if err := os.WriteFile(filepath.Join(instance, "index.php"), custom, 0o644); err != nil {
		t.Fatal(err)
	}
	if err := ProvisionInstance(instance, "/opt/yeswiki/program-4.6.0"); err != nil {
		t.Fatal(err)
	}

	entry, err := os.ReadFile(filepath.Join(instance, "index.php"))
	if err != nil {
		t.Fatal(err)
	}
	if string(entry) != string(custom) {
		t.Fatal("setup runs more than once, and must not overwrite what somebody wrote")
	}
}

func TestAnInstanceIsConfiguredOnlyWhenItHasABaseUrl(t *testing.T) {
	instance := t.TempDir()
	if Configured(instance) {
		t.Fatal("an empty folder is nobody's wiki")
	}

	if err := os.WriteFile(filepath.Join(instance, "yeswiki.config.php"), []byte("<?php\n$yeswikiConfig = [];\n"), 0o644); err != nil {
		t.Fatal(err)
	}
	if Configured(instance) {
		t.Fatal("an install that never finished leaves a config file with no base_url behind")
	}

	if err := os.WriteFile(filepath.Join(instance, "yeswiki.config.php"), []byte("<?php\n$yeswikiConfig = ['base_url' => 'http://x/?'];\n"), 0o644); err != nil {
		t.Fatal(err)
	}
	if !Configured(instance) {
		t.Fatal("a wiki with a base_url is installed")
	}
}
