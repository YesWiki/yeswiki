package yeswiki

import (
	"crypto/ed25519"
	"crypto/rand"
	"encoding/base64"
	"errors"
	"fmt"
	"os"
	"path/filepath"
	"strings"

	"github.com/spf13/cobra"

	"github.com/YesWiki/yeswiki/binary/internal/release"
)

// signCommand is the offline half of ADR-0016's signing key.
//
// The private half never reaches CI and is not a repository secret: it is held by the project and
// used on a machine of its own. This command is what uses it, and it is in the shipped binary
// rather than in a script so that whoever signs a release has the signer that matches the verifier
// by construction.
func signCommand() *cobra.Command {
	sign := &cobra.Command{
		Use:   "sign <file>...",
		Short: "Sign release artefacts with the project's offline key",
		Long: `Sign release artefacts with the project's offline ed25519 key.

Each file gets a detached <file>.sig beside it, base64 on one line so it can be pasted,
mailed and diffed. The repository serves those signatures beside the binaries, and every
installed binary verifies them offline against the public half compiled into it.

  yeswiki sign --generate ~/.yeswiki-signing
  yeswiki sign --key ~/.yeswiki-signing/yeswiki-release.key yeswiki-linux-x86_64

--generate writes a new pair and prints the public half to paste into
binary/internal/release/verify.go. Do that once. Losing the private half strands every
installed binary on its current version; leaking it owns all of them, so it belongs
somewhere offline and backed up, not in CI.`,
		Args: cobra.ArbitraryArgs,
	}
	sign.Flags().String("key", "", "The private key file to sign with")
	sign.Flags().String("generate", "", "Generate a new signing pair into this directory instead of signing")
	sign.Flags().Bool("verify", false, "Check the signatures beside these files against the compiled-in public key")

	sign.RunE = func(cmd *cobra.Command, arguments []string) error {
		if into, _ := cmd.Flags().GetString("generate"); strings.TrimSpace(into) != "" {
			return generateSigningPair(cmd, strings.TrimSpace(into))
		}
		if verifying, _ := cmd.Flags().GetBool("verify"); verifying {
			return verifySignatures(cmd, arguments)
		}

		keyFile, _ := cmd.Flags().GetString("key")
		if strings.TrimSpace(keyFile) == "" {
			return errors.New("--key names the private key to sign with, or --generate makes one")
		}
		if len(arguments) == 0 {
			return errors.New("name the files to sign")
		}

		return signFiles(cmd, strings.TrimSpace(keyFile), arguments)
	}

	return sign
}

const privateKeyName = "yeswiki-release.key"
const publicKeyName = "yeswiki-release.pub"

func generateSigningPair(cmd *cobra.Command, into string) error {
	if err := os.MkdirAll(into, 0o700); err != nil {
		return err
	}

	private := filepath.Join(into, privateKeyName)
	if _, err := os.Stat(private); err == nil {
		return fmt.Errorf("%s already exists, and overwriting a signing key strands every binary signed with it", private)
	}

	public, secret, err := ed25519.GenerateKey(rand.Reader)
	if err != nil {
		return err
	}

	if err := os.WriteFile(private, []byte(base64.StdEncoding.EncodeToString(secret)+"\n"), 0o600); err != nil {
		return err
	}
	encodedPublic := base64.StdEncoding.EncodeToString(public)
	if err := os.WriteFile(filepath.Join(into, publicKeyName), []byte(encodedPublic+"\n"), 0o644); err != nil {
		return err
	}

	out := cmd.OutOrStdout()
	fmt.Fprintf(out, "private key %s (0600, back this up offline -- losing it strands every installed binary)\n", private)
	fmt.Fprintf(out, "public key  %s\n\n", filepath.Join(into, publicKeyName))
	fmt.Fprintf(out, "Paste this into binary/internal/release/verify.go:\n\n")
	fmt.Fprintf(out, "const SigningKey = %q\n", encodedPublic)

	return nil
}

func signFiles(cmd *cobra.Command, keyFile string, files []string) error {
	key, err := readPrivateKey(keyFile)
	if err != nil {
		return err
	}

	for _, file := range files {
		content, err := os.ReadFile(file)
		if err != nil {
			return err
		}

		signature := release.Sign(content, key)
		beside := file + ".sig"
		if err := os.WriteFile(beside, []byte(signature+"  "+filepath.Base(file)+"\n"), 0o644); err != nil {
			return err
		}
		fmt.Fprintln(cmd.OutOrStdout(), "signed "+file+" -> "+beside)
	}

	return nil
}

func verifySignatures(cmd *cobra.Command, files []string) error {
	key, err := release.PublicKey()
	if err != nil {
		return err
	}

	for _, file := range files {
		raw, err := os.ReadFile(file + ".sig")
		if err != nil {
			return fmt.Errorf("no signature beside %s: %w", file, err)
		}
		signature, err := release.DecodeSignature(raw)
		if err != nil {
			return err
		}
		if err := release.Verify(file, release.Platform{}, signature, key); err != nil {
			return err
		}
		fmt.Fprintln(cmd.OutOrStdout(), "verified "+file)
	}

	return nil
}

func readPrivateKey(path string) (ed25519.PrivateKey, error) {
	content, err := os.ReadFile(path)
	if err != nil {
		return nil, err
	}

	raw, err := base64.StdEncoding.DecodeString(strings.TrimSpace(string(content)))
	if err != nil {
		return nil, fmt.Errorf("%s is not a base64 private key: %w", path, err)
	}
	if len(raw) != ed25519.PrivateKeySize {
		return nil, fmt.Errorf("%s is %d bytes, not the %d an ed25519 private key is", path, len(raw), ed25519.PrivateKeySize)
	}

	return ed25519.PrivateKey(raw), nil
}
