package yeswiki

import (
	"encoding/json"
	"io/fs"
	"strings"
)

// Version is the Program's version, written into the Program tree by build-program.sh.
var Version = versionFromProgram()

func versionFromProgram() string {
	content, err := fs.ReadFile(Program(), "VERSION")
	if err != nil {
		return "dev"
	}

	if stated := strings.TrimSpace(string(content)); stated != "" {
		return stated
	}

	return "dev"
}

// Build is what a binary was built from: every input that decides what came out.
//
// `spc` resolves its dependency sources at build time with no lockfile, so two builds of one tag
// are not bit-identical and this is what can be stated instead. build-static.sh writes it into the
// Program, so a binary in the field can be asked rather than guessed at.
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

// BuildInfo reads the manifest out of the embedded Program. A locally built or dynamically linked
// binary has none, and says so rather than inventing one.
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
