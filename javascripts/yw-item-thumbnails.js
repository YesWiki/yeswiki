// Asks for the thumbnails the server-side item lists could not wait for, one at a time, and shows each as it arrives.
const pending = [...document.querySelectorAll('img[data-yw-thumbnail]')]
let token = null

async function next() {
  const img = pending.shift()
  if (!img) return
  const {
    filename,
    width,
    height,
    mode,
    token: own,
  } = JSON.parse(img.dataset.ywThumbnail)
  token = token ?? own
  try {
    const response = await fetch(
      wiki.url(
        `?api/images/${encodeURIComponent(filename)}/cache/${width}/${height}/${mode}`,
      ),
      { method: 'POST', body: new URLSearchParams({ csrftoken: token }) },
    )
    const data = await response.json()
    if (data?.newToken) token = data.newToken
    img.src =
      response.ok && data?.cachefilename
        ? `${wiki.baseUrl.replace(/\?$/, '')}${data.cachefilename}`
        : img.dataset.ywOriginal
  } catch {
    img.src = img.dataset.ywOriginal
  }
  img.removeAttribute('data-yw-thumbnail')
  next()
}

next()
