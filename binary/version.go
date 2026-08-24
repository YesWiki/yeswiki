package yeswiki

import (
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
