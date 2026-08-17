const FavoritesHelper = {
  propertyName: 'https://yeswiki.net/vocabulary/favorite',
  getJson(url, params) {
    const query = new URLSearchParams(params).toString()
    return fetch(`${url}${url.includes('?') ? '&' : '?'}${query}`).then(
      (response) =>
        response.ok ? response.json() : Promise.reject(response.statusText),
    )
  },
  postForm(url, params) {
    return fetch(url, {
      method: 'POST',
      body: new URLSearchParams(params),
    }).then((response) =>
      response.ok ? response : Promise.reject(response.statusText),
    )
  },
  errorToast(error) {
    toastMessage(_t('FAVORITES_ERROR', { error }), 3000, 'alert alert-danger')
  },
  updateElem(elems, mode, withMessage = true) {
    elems.forEach((elem) => {
      const icon = elem.querySelector('i')
      if (mode === 'add') {
        elem.classList.add('user-favorite')
        if (icon) {
          icon.classList.remove('far')
          icon.classList.add('fas', 'fa-star')
        }
        elem.setAttribute('title', _t('FAVORITES_REMOVE'))
      } else {
        elem.classList.remove('user-favorite')
        if (icon) {
          icon.classList.remove('fas')
          icon.classList.add('far', 'fa-star')
        }
        elem.setAttribute('title', _t('FAVORITES_ADD'))
      }
    })
    if (mode === 'add') {
      if (withMessage) {
        toastMessage(_t('FAVORITES_ADDED'), 3000, 'alert alert-success')
      }
    } else {
      if (withMessage) {
        toastMessage(_t('FAVORITES_REMOVED'), 3000, 'alert alert-warning')
      }
      setTimeout(() => {
        elems.forEach((elem) => {
          if (!elem.classList.contains('user-favorite')) {
            const linkedFavoriteId = elem.getAttribute('data-linkedFavoriteid')
            if (linkedFavoriteId && linkedFavoriteId.length > 0) {
              const linkedFavorite = document.getElementById(linkedFavoriteId)
              if (linkedFavorite) {
                linkedFavorite.remove()
                elem.remove()
              } else {
                console.warn(`#${linkedFavoriteId} was waited but not found`)
              }
            }
          }
        })
      }, 1500)
    }
  },
  elemsFor(resource) {
    return Array.from(
      document.querySelectorAll(`[data-resource="${resource}"]`),
    )
  },
  addFavorite(resource, user, elem, checkNotEmpty = false) {
    FavoritesHelper.getJson(wiki.url(`?api/triples/${resource}`), {
      property: FavoritesHelper.propertyName,
      user,
    })
      .then((data) => {
        if (!Array.isArray(data) || data.length === 0) {
          if (checkNotEmpty) {
            FavoritesHelper.errorToast('not created')
          } else {
            FavoritesHelper.postForm(wiki.url(`?api/triples/${resource}`), {
              property: FavoritesHelper.propertyName,
              user,
            })
              .then(() => {
                FavoritesHelper.addFavorite(resource, user, elem, true)
              })
              .catch(FavoritesHelper.errorToast)
          }
        } else {
          FavoritesHelper.updateElem(FavoritesHelper.elemsFor(resource), 'add')
        }
      })
      .catch(FavoritesHelper.errorToast)
  },
  deleteFavorite(resource, user, elem, checkEmpty = false) {
    FavoritesHelper.getJson(wiki.url(`?api/triples/${resource}`), {
      property: FavoritesHelper.propertyName,
      user,
    })
      .then((data) => {
        if (Array.isArray(data) && data.length > 0) {
          if (checkEmpty) {
            FavoritesHelper.errorToast('not deleted')
          } else {
            FavoritesHelper.postForm(
              wiki.url(`?api/triples/${resource}/delete`),
              {
                property: FavoritesHelper.propertyName,
                user,
              },
            )
              .then(() => {
                FavoritesHelper.deleteFavorite(resource, user, elem, true)
              })
              .catch(FavoritesHelper.errorToast)
          }
        } else {
          FavoritesHelper.updateElem(
            FavoritesHelper.elemsFor(resource),
            'delete',
          )
        }
      })
      .catch(FavoritesHelper.errorToast)
  },
  manageFavorites(event) {
    event.preventDefault()
    let { target } = event
    if (target.tagName === 'I') {
      target = target.parentElement
    }
    const { resource, user } = target.dataset
    if (target.classList.contains('user-favorite')) {
      FavoritesHelper.deleteFavorite(resource, user, target)
    } else {
      FavoritesHelper.addFavorite(resource, user, target)
    }
  },
  deleteFirstFavorite(user, tagsValue, previousTag = null) {
    const tags = tagsValue
    if (previousTag || (Array.isArray(tags) && tags.length > 0)) {
      const lastTag = previousTag || tags.pop()
      const { resource } = lastTag
      FavoritesHelper.getJson(wiki.url(`?api/triples/${resource}`), {
        property: FavoritesHelper.propertyName,
        user,
      })
        .then((data) => {
          if (Array.isArray(data) && data.length > 0) {
            if (previousTag) {
              FavoritesHelper.errorToast('not deleted')
            } else {
              FavoritesHelper.postForm(
                wiki.url(`?api/triples/${resource}/delete`),
                {
                  property: FavoritesHelper.propertyName,
                  user,
                },
              )
                .then(() => {
                  FavoritesHelper.deleteFirstFavorite(user, tags, lastTag)
                })
                .catch(FavoritesHelper.errorToast)
            }
          } else {
            FavoritesHelper.updateElem(
              FavoritesHelper.elemsFor(resource),
              'delete',
              false,
            )
            FavoritesHelper.deleteFirstFavorite(user, tags)
          }
        })
        .catch(FavoritesHelper.errorToast)
    } else {
      toastMessage(_t('FAVORITES_ALL_DELETED'), 3000, 'alert alert-success')
    }
  },
  deleteAll(user) {
    FavoritesHelper.getJson(wiki.url('?api/triples'), {
      property: FavoritesHelper.propertyName,
      user,
    })
      .then((data) => {
        if (Array.isArray(data) && data.length > 0) {
          FavoritesHelper.deleteFirstFavorite(user, data)
        }
      })
      .catch(FavoritesHelper.errorToast)
  },
  bindLinks() {
    document.querySelectorAll('a.favorites:not(.eventSet)').forEach((link) => {
      link.classList.add('eventSet')
      link.addEventListener('click', FavoritesHelper.manageFavorites)
    })
  },
  init() {
    FavoritesHelper.bindLinks()
  },
}

FavoritesHelper.init()
document.addEventListener('yw-modal-open', () => {
  FavoritesHelper.bindLinks()
})
