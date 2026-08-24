package program

import (
	"fmt"
	"os"
	"path/filepath"
	"strings"
)

// EnvAsyncPHP names the executable YesWiki starts background jobs with.
const EnvAsyncPHP = "ASYNC_PHP_BINARY"

// shimName is the file written beside the Programs, not inside one.
const shimName = "php"

// ShimPath is where the shim lives for a given Program root.
func ShimPath(root string) string {
	return filepath.Join(root, shimName)
}

// WriteShim writes the little script that lets YesWiki start a background job.
func WriteShim(root, executable string) (string, error) {
	if strings.TrimSpace(executable) == "" {
		return "", fmt.Errorf("no path to this binary, so background jobs cannot be started")
	}

	if err := os.MkdirAll(root, 0o755); err != nil {
		return "", err
	}

	path := ShimPath(root)
	script := "#!/bin/sh\nexec " + shellQuoted(executable) + " php-cli \"$@\"\n"

	if err := os.WriteFile(path, []byte(script), 0o755); err != nil {
		return "", err
	}

	return path, nil
}

// shellQuoted is a path the shell reads as one word, whatever is in it.
func shellQuoted(value string) string {
	return "'" + strings.ReplaceAll(value, "'", `'\''`) + "'"
}
