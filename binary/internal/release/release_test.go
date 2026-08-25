package release

import (
	"crypto/ed25519"
	"crypto/rand"
	"crypto/sha256"
	"encoding/hex"
	"encoding/json"
	"net/http"
	"net/http/httptest"
	"os"
	"path/filepath"
	"strings"
	"testing"
)

// aRepository serves an index, a binary and its signature, the way repository.yeswiki.net will.
func aRepository(t *testing.T, version string, content []byte, sign func([]byte) string) (*httptest.Server, Client, ed25519.PublicKey) {
	t.Helper()

	public, private, err := ed25519.GenerateKey(rand.Reader)
	if err != nil {
		t.Fatal(err)
	}

	digest := sha256.Sum256(content)
	mux := http.NewServeMux()

	var server *httptest.Server
	mux.HandleFunc("/ectoplasme/binary.json", func(w http.ResponseWriter, _ *http.Request) {
		index := Index{
			Version:  version,
			Released: "2026-08-25",
			Platforms: map[string]Platform{
				ThisPlatform(): {
					URL:       server.URL + "/ectoplasme/yeswiki",
					SHA256:    hex.EncodeToString(digest[:]),
					Signature: server.URL + "/ectoplasme/yeswiki.sig",
					Bytes:     int64(len(content)),
				},
			},
		}
		_ = json.NewEncoder(w).Encode(index)
	})
	mux.HandleFunc("/ectoplasme/yeswiki", func(w http.ResponseWriter, _ *http.Request) {
		_, _ = w.Write(content)
	})
	mux.HandleFunc("/ectoplasme/yeswiki.sig", func(w http.ResponseWriter, _ *http.Request) {
		signature := sign(content)
		if signature == "" {
			signature = Sign(content, private)
		}
		_, _ = w.Write([]byte(signature + "  yeswiki\n"))
	})

	server = httptest.NewServer(mux)
	t.Cleanup(server.Close)

	return server, Client{Repository: server.URL, Channel: "ectoplasme", Key: public}, public
}

func nothingSpecial([]byte) string { return "" }

func TestTheIndexIsReadFromTheChannelAndNowhereElse(t *testing.T) {
	_, client, _ := aRepository(t, "5.0.0-alpha2", []byte("a binary"), nothingSpecial)

	address, err := client.IndexURL()
	if err != nil {
		t.Fatal(err)
	}
	if !strings.HasSuffix(address, "/ectoplasme/binary.json") {
		t.Fatalf("the index is not where a mirror would put it: %s", address)
	}

	index, err := client.Latest()
	if err != nil {
		t.Fatal(err)
	}
	if index.Version != "5.0.0-alpha2" {
		t.Fatalf("read %q", index.Version)
	}
}

// The extension index and an HTML error page both decode into a zero Index without complaining,
// which would read as "no update available" rather than as the failure it is.
func TestSomethingThatIsNotABinaryIndexIsAFailureRatherThanNoUpdate(t *testing.T) {
	for _, body := range []string{`{"extensions":{"herse":{}}}`, "<html>502 Bad Gateway</html>", "{}"} {
		server := httptest.NewServer(http.HandlerFunc(func(w http.ResponseWriter, _ *http.Request) {
			_, _ = w.Write([]byte(body))
		}))
		client := Client{Repository: server.URL, Channel: "ectoplasme"}

		if _, err := client.Latest(); err == nil {
			t.Errorf("%q was accepted as a binary index", body)
		}
		server.Close()
	}
}

func TestAnIndexWithNoSignatureForThisPlatformIsRefused(t *testing.T) {
	index := Index{Version: "5.0.0", Platforms: map[string]Platform{
		ThisPlatform(): {URL: "https://example.org/yeswiki", SHA256: "abc"},
	}}

	if _, err := index.For(ThisPlatform()); err == nil {
		t.Fatal("an unsigned executable was accepted")
	} else if !strings.Contains(err.Error(), "unsigned") {
		t.Fatalf("the refusal does not say why: %v", err)
	}
}

// Done when: a corrupted download is refused and the running binary is untouched.
func TestACorruptedDownloadIsRefusedAndTheRunningBinaryIsUntouched(t *testing.T) {
	real := []byte("the real binary")
	_, client, _ := aRepository(t, "5.0.0-alpha2", real, nothingSpecial)

	// The index describes the real one; the server hands over something else.
	index, err := client.Latest()
	if err != nil {
		t.Fatal(err)
	}
	entry, err := index.For(ThisPlatform())
	if err != nil {
		t.Fatal(err)
	}

	directory := t.TempDir()
	executable := filepath.Join(directory, "yeswiki")
	if err := os.WriteFile(executable, []byte("the running binary"), 0o755); err != nil {
		t.Fatal(err)
	}

	corrupted := filepath.Join(directory, ".yeswiki.incoming")
	if err := os.WriteFile(corrupted, []byte("truncated"), 0o644); err != nil {
		t.Fatal(err)
	}

	signature, err := client.FetchSignature(entry)
	if err != nil {
		t.Fatal(err)
	}

	err = client.Install(corrupted, executable, entry, signature, func(string) {})
	if err == nil {
		t.Fatal("a corrupted download was installed")
	}
	if !strings.Contains(err.Error(), "nothing was installed") {
		t.Errorf("the refusal does not say nothing was installed: %v", err)
	}

	running, _ := os.ReadFile(executable)
	if string(running) != "the running binary" {
		t.Fatalf("the running binary was touched: %q", running)
	}
	if _, err := os.Stat(corrupted); !os.IsNotExist(err) {
		t.Error("the failed download was left on disk")
	}
}

// A download that hashes correctly but was signed by somebody else is the case transport trust
// cannot catch, and the only one the key exists for.
func TestSomethingSignedByAnotherKeyIsRefused(t *testing.T) {
	content := []byte("a binary somebody else built")
	_, otherKey, err := ed25519.GenerateKey(rand.Reader)
	if err != nil {
		t.Fatal(err)
	}

	_, client, _ := aRepository(t, "5.0.0-alpha2", content, func(what []byte) string {
		return Sign(what, otherKey)
	})

	index, _ := client.Latest()
	entry, _ := index.For(ThisPlatform())

	directory := t.TempDir()
	executable := filepath.Join(directory, "yeswiki")
	_ = os.WriteFile(executable, []byte("the running binary"), 0o755)

	downloaded, err := client.Download(entry, executable, func(string) {})
	if err != nil {
		t.Fatal(err)
	}

	signature, err := client.FetchSignature(entry)
	if err != nil {
		t.Fatal(err)
	}

	err = client.Install(downloaded, executable, entry, signature, func(string) {})
	if err == nil {
		t.Fatal("a binary signed by another key was installed")
	}
	running, _ := os.ReadFile(executable)
	if string(running) != "the running binary" {
		t.Fatal("the running binary was replaced by one this project did not sign")
	}
}

func TestAGoodDownloadReplacesTheExecutableByRenameAndKeepsItsMode(t *testing.T) {
	content := []byte("the new binary")
	_, client, _ := aRepository(t, "5.0.0-alpha2", content, nothingSpecial)

	index, _ := client.Latest()
	entry, _ := index.For(ThisPlatform())

	directory := t.TempDir()
	executable := filepath.Join(directory, "yeswiki")
	if err := os.WriteFile(executable, []byte("old"), 0o755); err != nil {
		t.Fatal(err)
	}

	downloaded, err := client.Download(entry, executable, func(string) {})
	if err != nil {
		t.Fatal(err)
	}
	if filepath.Dir(downloaded) != directory {
		t.Fatalf("the download went to %s, not beside the executable: a rename across filesystems is not atomic", filepath.Dir(downloaded))
	}

	signature, err := client.FetchSignature(entry)
	if err != nil {
		t.Fatal(err)
	}
	if err := client.Install(downloaded, executable, entry, signature, func(string) {}); err != nil {
		t.Fatal(err)
	}

	installed, _ := os.ReadFile(executable)
	if string(installed) != "the new binary" {
		t.Fatalf("the executable is %q", installed)
	}
	info, _ := os.Stat(executable)
	if info.Mode().Perm() != 0o755 {
		t.Fatalf("the new executable is %v, not executable the way the old one was", info.Mode().Perm())
	}
	if left, _ := filepath.Glob(filepath.Join(directory, ".yeswiki.incoming-*")); len(left) > 0 {
		t.Errorf("the download was left behind: %v", left)
	}
}

// Done when: the same binary with a read-only Program root refuses with a message naming the path.
func TestAReadOnlyProgramRootRefusesAndNamesThePath(t *testing.T) {
	if os.Geteuid() == 0 {
		t.Skip("root writes to a read-only directory, so there is nothing to refuse")
	}

	root := filepath.Join(t.TempDir(), "program")
	if err := os.Mkdir(root, 0o500); err != nil {
		t.Fatal(err)
	}

	err := CanReplaceItself(root, filepath.Join(t.TempDir(), "yeswiki"))
	if err == nil {
		t.Fatal("a read-only program root was accepted")
	}
	if !strings.Contains(err.Error(), root) {
		t.Errorf("the refusal does not name the path: %v", err)
	}
	if !strings.Contains(err.Error(), "yeswiki migrate") {
		t.Errorf("the refusal does not say what still applies there: %v", err)
	}
}

// Writability is a fact about the deployment, established by writing. Not by /.dockerenv, not by
// a cgroup shape, not by KUBERNETES_SERVICE_HOST -- all of which have been wrong before.
func TestWritabilityIsDecidedByWritingRatherThanBySniffingForAContainer(t *testing.T) {
	writable := t.TempDir()
	if err := CanReplaceItself(writable, filepath.Join(writable, "yeswiki")); err != nil {
		t.Fatalf("a writable deployment was refused: %v", err)
	}

	t.Setenv("KUBERNETES_SERVICE_HOST", "10.0.0.1")
	if err := CanReplaceItself(writable, filepath.Join(writable, "yeswiki")); err != nil {
		t.Fatalf("a writable container was refused on a heuristic: %v", err)
	}
}

func TestABinaryWithNoCompiledInKeyRefusesRatherThanAcceptingAnything(t *testing.T) {
	if _, err := decodeKey(""); err != ErrNoKey {
		t.Fatalf("an empty key answered %v, and it has to be a refusal", err)
	}

	content := []byte("anything at all")
	if err := Verify(writeTemp(t, content), Platform{}, []byte("no signature"), nil); err != ErrNoKey {
		t.Fatalf("verification with no key answered %v", err)
	}
}

func TestTheSameVersionIsNotAnUpgrade(t *testing.T) {
	_, client, _ := aRepository(t, "5.0.0-alpha2", []byte("x"), nothingSpecial)

	_, _, newer, err := client.Available("5.0.0-alpha2")
	if err != nil {
		t.Fatal(err)
	}
	if newer {
		t.Fatal("the running version was offered to itself")
	}

	if _, _, newer, _ = client.Available("5.0.0-alpha1"); !newer {
		t.Fatal("a newer version was not offered")
	}
}

func TestASignatureIsAcceptedRawOrBase64AndWithOrWithoutItsFilename(t *testing.T) {
	_, private, err := ed25519.GenerateKey(rand.Reader)
	if err != nil {
		t.Fatal(err)
	}
	encoded := Sign([]byte("content"), private)

	for _, written := range []string{encoded, encoded + "\n", encoded + "  yeswiki-linux-x86_64\n"} {
		decoded, err := DecodeSignature([]byte(written))
		if err != nil {
			t.Fatalf("%q was refused: %v", written, err)
		}
		if len(decoded) != ed25519.SignatureSize {
			t.Fatalf("%q decoded to %d bytes", written, len(decoded))
		}
	}

	if _, err := DecodeSignature([]byte("not a signature at all")); err == nil {
		t.Error("a signature that is neither raw nor base64 was accepted")
	}
}

func writeTemp(t *testing.T, content []byte) string {
	t.Helper()
	path := filepath.Join(t.TempDir(), "artefact")
	if err := os.WriteFile(path, content, 0o644); err != nil {
		t.Fatal(err)
	}

	return path
}

// The index in testdata is the actual output of yeswiki-build-repo's BinaryPublisher, copied
// unchanged. Two repositories write and read this file and neither imports the other, so the only
// thing keeping them in agreement is that this fixture is the real thing.
func TestTheIndexTheRepositoryBuilderWritesIsTheOneThisReads(t *testing.T) {
	content, err := os.ReadFile(filepath.Join("testdata", "binary.json"))
	if err != nil {
		t.Fatal(err)
	}

	var index Index
	if err := json.Unmarshal(content, &index); err != nil {
		t.Fatal(err)
	}

	if index.Version != "5.0.0-alpha2" {
		t.Errorf("version did not survive: %q", index.Version)
	}
	if index.Released == "" {
		t.Error("released did not survive")
	}

	entry, err := index.For("linux-x86_64")
	if err != nil {
		t.Fatal(err)
	}
	if entry.URL == "" || entry.Signature == "" || entry.SHA256 == "" || entry.Bytes == 0 {
		t.Fatalf("a field did not survive the round trip: %+v", entry)
	}
	if !strings.HasSuffix(entry.Signature, ".sig") {
		t.Errorf("the signature is not beside the artefact: %s", entry.Signature)
	}
	// Versioned rather than overwritten, so an index read and the download that follows it cannot
	// straddle a release.
	if !strings.Contains(entry.URL, "/"+index.Version+"/") {
		t.Errorf("the artefact url is not versioned: %s", entry.URL)
	}
}
