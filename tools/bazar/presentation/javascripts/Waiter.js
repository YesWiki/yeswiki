const cacheResolveReject = {}
const isReady = {}

const resolve = (name) => {
  isReady[name] = true
  if (name in cacheResolveReject
        && Array.isArray(cacheResolveReject[name])) {
    const listOfResolveReject = cacheResolveReject[name]
    cacheResolveReject[name] = []
    listOfResolveReject.forEach(({ base, resolve }) => resolve(base?.[name]))
  }
}

const waitFor = async(name, base) => {
  if (isReady?.[name]) {
    return base?.[name]
  }
  isReady[name] = false // define it
  if (!(name in cacheResolveReject)) {
    cacheResolveReject[name] = []
  }
  const promise = new Promise((resolve, reject) => {
    cacheResolveReject[name].push({ base, resolve, reject })
  })
  return await promise.then((...args) => Promise.resolve(...args)) // force .then()
}

export default { waitFor, resolve }
