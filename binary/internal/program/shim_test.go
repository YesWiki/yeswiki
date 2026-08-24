package program

import (
	"os"
	"os/exec"
	"path/filepath"
	"strings"
	"testing"
)

func TestTheShimRunsTheBinaryAsAPhpThatTakesAScript(t *testing.T) {
	root := t.TempDir()

	path, err := WriteShim(root, "/usr/local/bin/yeswiki")
	if err != nil {
		t.Fatal(err)
	}

	written, err := os.ReadFile(path)
	if err != nil {
		t.Fatal(err)
	}
	script := string(written)

	if !strings.HasPrefix(script, "#!/bin/sh\n") {
		t.Errorf("the shim needs a shebang to be executable:\n%s", script)
	}
	if !strings.Contains(script, "php-cli \"$@\"") {
		t.Errorf("the shim must pass the script and its arguments through:\n%s", script)
	}
	if !strings.Contains(script, "'/usr/local/bin/yeswiki'") {
		t.Errorf("the shim must name this binary:\n%s", script)
	}
}

func TestTheShimIsExecutable(t *testing.T) {
	path, err := WriteShim(t.TempDir(), "/usr/local/bin/yeswiki")
	if err != nil {
		t.Fatal(err)
	}

	stat, err := os.Stat(path)
	if err != nil {
		t.Fatal(err)
	}
	if stat.Mode().Perm()&0o111 == 0 {
		t.Errorf("a shim nothing may execute starts no background job: mode %v", stat.Mode())
	}
}

// A path with a quote in it is not a reason for the shell to run something else.
func TestAnAwkwardPathIsStillOneWord(t *testing.T) {
	root := t.TempDir()
	awkward := "/home/o'brien/yes wiki/yeswiki"

	path, err := WriteShim(root, awkward)
	if err != nil {
		t.Fatal(err)
	}

	written, _ := os.ReadFile(path)
	if !strings.Contains(string(written), `'/home/o'\''brien/yes wiki/yeswiki'`) {
		t.Errorf("the path is not quoted safely:\n%s", written)
	}
}

// The shim names the binary absolutely, so a binary that moved leaves every wiki unable to start a job until it is rewritten.
func TestTheShimIsRewrittenWhenTheBinaryMoves(t *testing.T) {
	root := t.TempDir()

	if _, err := WriteShim(root, "/old/place/yeswiki"); err != nil {
		t.Fatal(err)
	}
	if _, err := WriteShim(root, "/new/place/yeswiki"); err != nil {
		t.Fatal(err)
	}

	written, _ := os.ReadFile(ShimPath(root))
	if strings.Contains(string(written), "/old/place") {
		t.Errorf("the shim still names where the binary used to be:\n%s", written)
	}
}

func TestABinaryThatCannotBeFoundIsSaidSoRatherThanShimmedToNothing(t *testing.T) {
	if _, err := WriteShim(t.TempDir(), "  "); err == nil {
		t.Fatal("a shim naming nothing would fail at the worst moment, silently")
	}
}

// What the shim is for: something that takes a script path first, the way php does.
func TestTheShimActuallyRunsAScript(t *testing.T) {
	if _, err := exec.LookPath("sh"); err != nil {
		t.Skip("no shell to run the shim with")
	}

	root := t.TempDir()
	pretend := filepath.Join(root, "pretend-yeswiki")
	if err := os.WriteFile(pretend, []byte("#!/bin/sh\necho \"$@\"\n"), 0o755); err != nil {
		t.Fatal(err)
	}

	shim, err := WriteShim(root, pretend)
	if err != nil {
		t.Fatal(err)
	}

	out, err := exec.Command(shim, "/some/console", "helloworld:hello").Output()
	if err != nil {
		t.Fatal(err)
	}

	if strings.TrimSpace(string(out)) != "php-cli /some/console helloworld:hello" {
		t.Errorf("the shim did not stand in for php: %q", out)
	}
}
