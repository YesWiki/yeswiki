package program

import (
	"fmt"
	"os"
	"path/filepath"
	"regexp"
	"strings"
)

// EnvInstance names the Instance a command is working on.
const EnvInstance = "YESWIKI_INSTANCE_DIR"

// EnvProgram names the Program a command is running from.
const EnvProgram = "YESWIKI_PROGRAM_DIR"

// EnvConfigFile names the Instance's configuration file.
const EnvConfigFile = "YESWIKI_CONFIG_FILE"

// dataFolders are the Instance's own, created on sight, as bootstrap_paths.php also does.
var dataFolders = []string{"cache", "custom", "files", "private"}

// ResolveInstance turns the directory a command was given into an absolute path, defaulting to the working directory.
func ResolveInstance(argument string, workingDirectory func() (string, error)) (string, error) {
	if strings.TrimSpace(argument) == "" {
		dir, err := workingDirectory()
		if err != nil {
			return "", fmt.Errorf("no directory given and no working directory to fall back on: %w", err)
		}

		return dir, nil
	}

	return filepath.Abs(argument)
}

// ProvisionInstance creates an Instance's data folders and the index.php that points at a Program.
func ProvisionInstance(instance, programDir string) error {
	if err := os.MkdirAll(instance, 0o755); err != nil {
		return err
	}
	for _, folder := range dataFolders {
		if err := os.MkdirAll(filepath.Join(instance, folder), 0o755); err != nil {
			return err
		}
	}

	for name, content := range map[string]string{
		"index.php":  IndexPHP(programDir),
		"worker.php": WorkerPHP(programDir),
	} {
		entry := filepath.Join(instance, name)
		if _, err := os.Stat(entry); err == nil {
			continue
		}
		if err := os.WriteFile(entry, []byte(content), 0o644); err != nil {
			return err
		}
	}

	return nil
}

// WorkerPHP is the Instance entry point a worker runs, and what try_files resolves to under worker mode.
func WorkerPHP(programDir string) string {
	return fmt.Sprintf(`<?php

define('%s', %s);
putenv('%s=' . __DIR__);
putenv('%s=' . __DIR__ . '/yeswiki.config.php');
require %s . '/worker.php';
`, EnvProgram, phpString(programDir), EnvInstance, EnvConfigFile, EnvProgram)
}

// IndexPHP is the Instance entry point, stating both roots the way CreateInstanceCommand does.
func IndexPHP(programDir string) string {
	return fmt.Sprintf(`<?php

define('%s', %s);
putenv('%s=' . __DIR__);
putenv('%s=' . __DIR__ . '/yeswiki.config.php');
require %s . '/index.php';
`, EnvProgram, phpString(programDir), EnvInstance, EnvConfigFile, EnvProgram)
}

// DoorMarker is the file whose presence makes the farm answer 503 for a wiki, checked per request by the webserver.
const DoorMarker = "private/.upgrading"

// CloseDoor makes the farm answer 503 for this wiki, from the next request.
func CloseDoor(instance string) error {
	if err := os.MkdirAll(filepath.Join(instance, "private"), 0o755); err != nil {
		return err
	}

	return os.WriteFile(filepath.Join(instance, DoorMarker), []byte("upgrading\n"), 0o644)
}

// OpenDoor puts a wiki back into service.
func OpenDoor(instance string) error {
	err := os.Remove(filepath.Join(instance, DoorMarker))
	if os.IsNotExist(err) {
		return nil
	}

	return err
}

// DoorClosed reports whether this wiki is answering 503.
func DoorClosed(instance string) bool {
	_, err := os.Stat(filepath.Join(instance, DoorMarker))

	return err == nil
}

// PointAt rewrites an Instance's entry points at another Program and throws away what the old one compiled for it.
func PointAt(instance, programDir string) error {
	for name, content := range map[string]string{
		"index.php":  IndexPHP(programDir),
		"worker.php": WorkerPHP(programDir),
	} {
		if err := os.WriteFile(filepath.Join(instance, name), []byte(content), 0o644); err != nil {
			return err
		}
	}

	return ForgetCompiled(instance)
}

// ForgetCompiled removes what a Program compiled into an Instance.
func ForgetCompiled(instance string) error {
	for _, compiled := range []string{"cache/container", "cache/templates"} {
		if err := os.RemoveAll(filepath.Join(instance, compiled)); err != nil {
			return err
		}
	}

	return nil
}

// FindInstance is the wiki a command run from this directory is about.
func FindInstance(start string) (string, bool) {
	directory, err := filepath.Abs(start)
	if err != nil {
		return "", false
	}

	for {
		if Configured(directory) {
			return directory, true
		}

		parent := filepath.Dir(directory)
		if parent == directory {
			return "", false
		}
		directory = parent
	}
}

// NamedBy is the Program an Instance's entry point names, whether or not it is still there.
func NamedBy(instance string) (string, bool) {
	content, err := os.ReadFile(filepath.Join(instance, "index.php"))
	if err != nil {
		return "", false
	}

	found := regexp.MustCompile(`define\('YESWIKI_PROGRAM_DIR',\s*'((?:[^'\\]|\\.)*)'\)`).FindSubmatch(content)
	if found == nil {
		return "", false
	}

	return strings.NewReplacer(`\'`, `'`, `\\`, `\`).Replace(string(found[1])), true
}

// OfInstance is the Program an Instance names when that Program is still usable.
func OfInstance(instance string) (string, bool) {
	stated, found := NamedBy(instance)
	if !found {
		return "", false
	}

	if _, err := os.Stat(filepath.Join(stated, "src", "commands", "console")); err != nil {
		return "", false
	}

	return stated, true
}

// Configured reports whether an Instance has been installed already.
func Configured(instance string) bool {
	content, err := os.ReadFile(filepath.Join(instance, "yeswiki.config.php"))

	return err == nil && strings.Contains(string(content), "'base_url'")
}

func phpString(value string) string {
	return "'" + strings.ReplaceAll(strings.ReplaceAll(value, `\`, `\\`), `'`, `\'`) + "'"
}
