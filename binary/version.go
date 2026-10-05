package yeswiki

import (
	"encoding/json"
	"io/fs"
	"strings"
)

// Version is the Program's version, injected into its composer.json by build-program.sh.
var Version = versionFromProgram()

func versionFromProgram() string {
	content, err := fs.ReadFile(Program(), "composer.json")
	if err != nil {
		return "dev"
	}

	return versionFromManifest(content)
}

// versionFromManifest answers a composer.json's "version", or "dev" when it states none.
func versionFromManifest(content []byte) string {
	var manifest struct {
		Version string `json:"version"`
	}
	if err := json.Unmarshal(content, &manifest); err != nil {
		return "dev"
	}

	if stated := strings.TrimSpace(manifest.Version); stated != "" {
		return stated
	}

	return "dev"
}

// Build is what a binary was built from, written into the Program by build-static.sh.
type Build struct {
	Version       string   `json:"version"`
	Commit        string   `json:"commit"`
	FrankenPHP    string   `json:"frankenphp"`
	PHP           string   `json:"php"`
	Arch          string   `json:"arch"`
	Extensions    []string `json:"extensions"`
	ExtensionLibs []string `json:"extension_libs"`
	CaddyModules  []string `json:"caddy_modules"`
	Compressed    bool     `json:"compressed"`
	Linkage       string   `json:"linkage"`
	SHA256        string   `json:"sha256,omitempty"`
	Bytes         int64    `json:"bytes,omitempty"`
}

// BuildInfo reads the manifest out of the embedded Program, and says when there is none.
func BuildInfo() (Build, bool) {
	content, err := fs.ReadFile(Program(), "BUILD.json")
	if err != nil {
		return Build{Version: Version}, false
	}

	var build Build
	if err := json.Unmarshal(content, &build); err != nil {
		return Build{Version: Version}, false
	}

	return build, true
}
