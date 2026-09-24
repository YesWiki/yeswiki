package yeswiki

import "testing"

func TestTheFirstBundleThatExistsIsTheOneUsed(t *testing.T) {
	present := map[string]bool{
		"/etc/pki/tls/certs/ca-bundle.crt": true,
		"/etc/ssl/cert.pem":                true,
	}

	got := certificateBundle(func(path string) bool { return present[path] })
	if got != "/etc/pki/tls/certs/ca-bundle.crt" {
		t.Errorf("chose %q, want the Fedora bundle ahead of the Alpine one", got)
	}
}

func TestAMachineWithNoBundleGetsNothingRatherThanAGuess(t *testing.T) {
	if got := certificateBundle(func(string) bool { return false }); got != "" {
		t.Errorf("chose %q on a machine with no bundle at all", got)
	}
}
