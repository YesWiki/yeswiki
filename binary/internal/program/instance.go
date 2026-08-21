package program

import (
	"fmt"
	"os"
	"path/filepath"
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

// ResolveInstance turns the directory a command was given into an absolute path, defaulting to the
// working directory.
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

	entry := filepath.Join(instance, "index.php")
	if _, err := os.Stat(entry); err == nil {
		return nil
	}

	return os.WriteFile(entry, []byte(IndexPHP(programDir)), 0o644)
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

// Configured reports whether an Instance has been installed already.
func Configured(instance string) bool {
	content, err := os.ReadFile(filepath.Join(instance, "yeswiki.config.php"))

	return err == nil && strings.Contains(string(content), "'base_url'")
}

func phpString(value string) string {
	return "'" + strings.ReplaceAll(strings.ReplaceAll(value, `\`, `\\`), `'`, `\'`) + "'"
}
