/* Appended to the vendored leaflet-providers: a map asking for a background the library no longer knows gets OpenStreetMap instead of no map at all. */
;(() => {
  const provider = L.tileLayer.provider
  L.tileLayer.provider = (name, options) => {
    try {
      return provider(name, options)
    } catch {
      console.warn(
        `Unknown map background "${name}", OpenStreetMap.Mapnik is used instead`,
      )
      return provider('OpenStreetMap.Mapnik', options)
    }
  }
})()
