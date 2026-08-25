// Package release is how a binary finds, verifies and installs the one that replaces it.
//
// It goes to the wiki's own repository and nowhere else (ADR-0016): a second distributor would be
// a second thing every wiki trusts, and `yeswiki_repository` exists so a private or air-gapped
// mirror can be that host. Nothing here talks to GitHub.
package release

import (
	"crypto/ed25519"
	"encoding/json"
	"fmt"
	"io"
	"net/http"
	"net/url"
	"runtime"
	"strings"
	"time"
)

// DefaultRepository is the host ADR-0016 made the only distributor.
const DefaultRepository = "https://repository.yeswiki.net"

// IndexName is what the repository serves per channel, beside the extension and theme listings.
const IndexName = "binary.json"

// Index is what a channel publishes about the executable it distributes.
//
// Deliberately flat and greppable: an operator mirroring this by hand should be able to read it,
// and a shell script should be able to pull a url out of it without a JSON library.
type Index struct {
	Version   string              `json:"version"`
	Released  string              `json:"released"`
	Platforms map[string]Platform `json:"platforms"`
}

// Platform is one executable: where it is, what it should hash to, and where its signature is.
type Platform struct {
	URL       string `json:"url"`
	SHA256    string `json:"sha256"`
	Signature string `json:"signature"`
	Bytes     int64  `json:"bytes"`
}

// Name is how a platform is spelled in an index, matching the artefact names the build produces.
func Name(goos, goarch string) string {
	arch := goarch
	switch goarch {
	case "amd64":
		arch = "x86_64"
	case "arm64":
		arch = "aarch64"
	}

	return goos + "-" + arch
}

// ThisPlatform is the index key for the binary asking.
func ThisPlatform() string {
	return Name(runtime.GOOS, runtime.GOARCH)
}

// Fetcher is the HTTP the client needs, so a test can answer without a network.
type Fetcher interface {
	Get(url string) (*http.Response, error)
}

// Client reads a repository. A zero Client uses a plain http.Client with a timeout and the
// signing key compiled into this binary.
type Client struct {
	Repository string
	Channel    string
	HTTP       Fetcher

	// Key overrides the compiled-in public key. Only a test sets this: a shipped binary trusts
	// one key, and a key that can be passed in is a key an attacker can pass in.
	Key ed25519.PublicKey
}

// key is what this client verifies with: its own if it was given one, otherwise the compiled-in
// half, otherwise the refusal that says this binary cannot verify anything.
func (c Client) key() (ed25519.PublicKey, error) {
	if c.Key != nil {
		return c.Key, nil
	}

	return PublicKey()
}

func (c Client) fetcher() Fetcher {
	if c.HTTP != nil {
		return c.HTTP
	}

	return &http.Client{Timeout: 30 * time.Second}
}

// IndexURL is where this channel's index lives, which a mirror can serve unchanged.
func (c Client) IndexURL() (string, error) {
	repository := strings.TrimSpace(c.Repository)
	if repository == "" {
		repository = DefaultRepository
	}
	if _, err := url.Parse(repository); err != nil {
		return "", fmt.Errorf("%s is not a repository address: %w", repository, err)
	}

	parts := []string{strings.TrimSuffix(repository, "/")}
	if channel := strings.Trim(strings.TrimSpace(c.Channel), "/"); channel != "" {
		parts = append(parts, strings.ToLower(channel))
	}
	parts = append(parts, IndexName)

	return strings.Join(parts, "/"), nil
}

// Latest is what the channel is offering, or a sentence saying why that could not be read.
func (c Client) Latest() (Index, error) {
	address, err := c.IndexURL()
	if err != nil {
		return Index{}, err
	}

	response, err := c.fetcher().Get(address)
	if err != nil {
		return Index{}, fmt.Errorf("could not reach %s: %w", address, err)
	}
	defer response.Body.Close()

	if response.StatusCode != http.StatusOK {
		return Index{}, fmt.Errorf("%s answered %s", address, response.Status)
	}

	// A repository that has never published a binary answers the extension index, or an HTML
	// error page a proxy substituted. Both decode into a zero Index without complaining, so the
	// read is capped and the shape is checked rather than trusted.
	body, err := io.ReadAll(io.LimitReader(response.Body, 1<<20))
	if err != nil {
		return Index{}, fmt.Errorf("could not read %s: %w", address, err)
	}

	var index Index
	if err := json.Unmarshal(body, &index); err != nil {
		return Index{}, fmt.Errorf("%s is not a binary index: %w", address, err)
	}
	if strings.TrimSpace(index.Version) == "" || len(index.Platforms) == 0 {
		return Index{}, fmt.Errorf("%s names no version or no platform, so it is not a binary index", address)
	}

	return index, nil
}

// For answers the entry this binary's platform should install, or says which platforms exist.
func (i Index) For(platform string) (Platform, error) {
	entry, found := i.Platforms[platform]
	if !found {
		offered := make([]string, 0, len(i.Platforms))
		for name := range i.Platforms {
			offered = append(offered, name)
		}

		return Platform{}, fmt.Errorf("%s publishes nothing for %s, only %s",
			i.Version, platform, strings.Join(offered, ", "))
	}
	if strings.TrimSpace(entry.URL) == "" {
		return Platform{}, fmt.Errorf("%s names no url for %s", i.Version, platform)
	}
	if strings.TrimSpace(entry.Signature) == "" {
		return Platform{}, fmt.Errorf("%s publishes %s with no signature, and an unsigned executable is never installed", i.Version, platform)
	}

	return entry, nil
}

// Resolve turns a possibly relative url in the index into one that can be fetched, so a mirror
// can publish relative paths and stay a mirror.
func (c Client) Resolve(reference string) (string, error) {
	if strings.HasPrefix(reference, "http://") || strings.HasPrefix(reference, "https://") {
		return reference, nil
	}

	index, err := c.IndexURL()
	if err != nil {
		return "", err
	}
	base, err := url.Parse(index)
	if err != nil {
		return "", err
	}
	relative, err := url.Parse(reference)
	if err != nil {
		return "", fmt.Errorf("%s is not a url: %w", reference, err)
	}

	return base.ResolveReference(relative).String(), nil
}
