package release

import (
	"crypto/ed25519"
	"crypto/sha256"
	"encoding/base64"
	"encoding/hex"
	"errors"
	"fmt"
	"os"
	"strings"
)

// SigningKey is the public half of the offline release key, base64 of the raw 32 bytes (ADR-0016).
const SigningKey = "U7onFH3MVm9QDahMQyxm2meiq7162JKdkf6gcpjWs2U="

// ErrNoKey is a binary that cannot verify anything, which is a refusal and not a warning.
var ErrNoKey = errors.New("this binary carries no release signing key, so it cannot verify an update and will not install one")

// ErrBadSignature is a download that is not what the project signed.
var ErrBadSignature = errors.New("the signature does not match")

// PublicKey is the compiled-in key, decoded, or the reason there is none.
func PublicKey() (ed25519.PublicKey, error) {
	return decodeKey(SigningKey)
}

func decodeKey(encoded string) (ed25519.PublicKey, error) {
	trimmed := strings.TrimSpace(encoded)
	if trimmed == "" {
		return nil, ErrNoKey
	}

	raw, err := base64.StdEncoding.DecodeString(trimmed)
	if err != nil {
		return nil, fmt.Errorf("the compiled-in signing key is not base64: %w", err)
	}
	if len(raw) != ed25519.PublicKeySize {
		return nil, fmt.Errorf("the compiled-in signing key is %d bytes, not the %d an ed25519 public key is", len(raw), ed25519.PublicKeySize)
	}

	return ed25519.PublicKey(raw), nil
}

// Verify checks a downloaded executable against what the index says and what the project signed.
//
// Both halves are required and neither substitutes for the other. The digest catches a truncated
// or corrupted download; only the signature says the project produced it. A failure here deletes
// the download and never falls back to installing anyway.
func Verify(path string, entry Platform, signature []byte, key ed25519.PublicKey) error {
	content, err := os.ReadFile(path)
	if err != nil {
		return err
	}

	if expected := strings.TrimSpace(entry.SHA256); expected != "" {
		digest := sha256.Sum256(content)
		if got := hex.EncodeToString(digest[:]); !strings.EqualFold(got, expected) {
			return fmt.Errorf("%s hashes to %s, and the index says %s: nothing was installed", path, got, expected)
		}
	}

	if entry.Bytes > 0 && int64(len(content)) != entry.Bytes {
		return fmt.Errorf("%s is %d bytes, and the index says %d: nothing was installed", path, len(content), entry.Bytes)
	}

	if key == nil {
		return ErrNoKey
	}
	if !ed25519.Verify(key, content, signature) {
		return fmt.Errorf("%w, so %s was not produced by this project: nothing was installed", ErrBadSignature, path)
	}

	return nil
}

// DecodeSignature reads a detached signature, which is base64 on one line so that it can be
// pasted, mailed and diffed. Raw 64 bytes are accepted too, for a signature made by another tool.
func DecodeSignature(content []byte) ([]byte, error) {
	if len(content) == ed25519.SignatureSize {
		return content, nil
	}

	trimmed := strings.TrimSpace(string(content))
	// A signature file written beside a checksum file often carries the filename after the value,
	// the way sha256sum does. Take the first field and ignore what documents it.
	if field, _, found := strings.Cut(trimmed, " "); found {
		trimmed = field
	}
	trimmed = strings.TrimSpace(trimmed)

	raw, err := base64.StdEncoding.DecodeString(trimmed)
	if err != nil {
		return nil, fmt.Errorf("the signature is neither 64 raw bytes nor base64: %w", err)
	}
	if len(raw) != ed25519.SignatureSize {
		return nil, fmt.Errorf("the signature is %d bytes, not the %d an ed25519 signature is", len(raw), ed25519.SignatureSize)
	}

	return raw, nil
}

// Sign is what the offline signing helper does: the private half never comes near this binary in
// normal use, but the same code signs and verifies so the two cannot drift.
func Sign(content []byte, key ed25519.PrivateKey) string {
	return base64.StdEncoding.EncodeToString(ed25519.Sign(key, content))
}
