package yeswiki

import "os"

// systemBundles are where Linux distributions keep their trusted certificates, as one PEM file.
var systemBundles = []string{
	"/etc/ssl/certs/ca-certificates.crt",
	"/etc/pki/tls/certs/ca-bundle.crt",
	"/etc/pki/ca-trust/extracted/pem/tls-ca-bundle.pem",
	"/etc/ssl/ca-bundle.pem",
	"/etc/ssl/cert.pem",
}

// certificateBundle is the first system bundle that exists, or nothing.
func certificateBundle(exists func(string) bool) string {
	for _, bundle := range systemBundles {
		if exists(bundle) {
			return bundle
		}
	}

	return ""
}

// init points the embedded OpenSSL at the system's certificates, which it would otherwise look for at Alpine's path only.
func init() {
	if os.Getenv("SSL_CERT_FILE") != "" || os.Getenv("SSL_CERT_DIR") != "" {
		return
	}

	bundle := certificateBundle(func(path string) bool {
		info, err := os.Stat(path)

		return err == nil && !info.IsDir()
	})
	if bundle != "" {
		_ = os.Setenv("SSL_CERT_FILE", bundle)
	}
}
