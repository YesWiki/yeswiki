function MastodonShare(e) {
  // Get the source text
  const src = e.getAttribute('data-src');
  // Get the Mastodon domain
  const domain = prompt('Enter your Mastodon domain', 'mastodon.social');
  if (domain === '' || domain == null) {
    return;
  }
  // Build the URL
  const url = 'https://' + domain + '/share?text=' + src;
  // Open a window on the share page
  window.open(url, '_blank');
}
