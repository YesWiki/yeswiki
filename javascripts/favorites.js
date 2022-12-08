const FavoritesHelper = {
  propertyName: 'https://yeswiki.net/vocabulary/favorite',
  updateElem(elem, mode, withMessage = true) {
    if (mode == 'add') {
      $(elem).addClass('user-favorite')
      $(elem).find('i')
        .removeClass('far fa-star')
        .addClass('fas fa-star')
      $(elem).tooltip('destroy')
      $(elem).attr('title', _t('FAVORITES_REMOVE'))
      $(elem).removeData('original-title')
      if (withMessage) {
        toastMessage(
          _t('FAVORITES_ADDED'),
          3000,
          'alert alert-success'
        )
      }
    } else {
      $(elem).removeClass('user-favorite')
      $(elem).find('i')
        .removeClass('fas fa-star')
        .addClass('far fa-star')
      $(elem).tooltip('destroy')
      $(elem).attr('title', _t('FAVORITES_ADD'))
      $(elem).removeData('original-title')
      if (withMessage) {
        toastMessage(
          _t('FAVORITES_REMOVED'),
          3000,
          'alert alert-warning'
        )
      }
      // remove linked favorite 1.5s after
      setTimeout(() => {
        $(elem).each(function() {
          if (!$(this).hasClass('user-favorite')) {
            const linkedFavoriteId = $(this).attr('data-linkedFavoriteid')
            if (linkedFavoriteId != undefined && linkedFavoriteId.length > 0) {
              const linkedFavorite = $(`#${linkedFavoriteId}`)
              if (linkedFavorite != undefined && linkedFavorite.length > 0) {
                $(linkedFavorite).remove()
                $(this).remove()
              } else {
                console.warn(`#linkedFavoriteId was waited but not found : ${JSON.stringify(linkedFavorite)}`)
              }
            }
          }
        })
      }, 1500)
    }
  },
  addFavorite(resource, user, elem, checkNotEmpty = false) {
    $.ajax({
      method: 'GET',
      url: wiki.url(`api/triples/${resource}`),
      data: {
        property: FavoritesHelper.propertyName,
        user
      },
      success(data) {
        if (!Array.isArray(data) || data.length == 0) {
          if (checkNotEmpty) {
            toastMessage(
              _t('FAVORITES_ERROR', { error: 'not created' }),
              3000,
              'alert alert-danger'
            )
          } else {
            $.ajax({
              method: 'POST',
              url: wiki.url(`api/triples/${resource}`),
              data: {
                property: FavoritesHelper.propertyName,
                user
              },
              success() {
                FavoritesHelper.addFavorite(resource, user, elem, true)
              },
              error(xhr, status, error) {
                toastMessage(
                  _t('FAVORITES_ERROR', { error }),
                  3000,
                  'alert alert-danger'
                )
              }
            })
          }
        } else {
          FavoritesHelper.updateElem($(`[data-resource="${resource}"]`), 'add')
        }
      },
      error(xhr, status, error) {
        toastMessage(
          _t('FAVORITES_ERROR', { error }),
          3000,
          'alert alert-danger'
        )
      }
    })
  },
  deleteFavorite(resource, user, elem, checkEmpty = false) {
    $.ajax({
      method: 'GET',
      url: wiki.url(`api/triples/${resource}`),
      data: {
        property: FavoritesHelper.propertyName,
        user
      },
      success(data) {
        if (Array.isArray(data) && data.length > 0) {
          if (checkEmpty) {
            toastMessage(
              _t('FAVORITES_ERROR', { error: 'not deleted' }),
              3000,
              'alert alert-danger'
            )
          } else {
            $.ajax({
              method: 'POST',
              url: wiki.url(`api/triples/${resource}/delete`),
              data: {
                property: FavoritesHelper.propertyName,
                user
              },
              success() {
                FavoritesHelper.deleteFavorite(resource, user, elem, true)
              },
              error(xhr, status, error) {
                toastMessage(
                  _t('FAVORITES_ERROR', { error }),
                  3000,
                  'alert alert-danger'
                )
              }
            })
          }
        } else {
          FavoritesHelper.updateElem($(`[data-resource="${resource}"]`), 'delete')
        }
      },
      error(xhr, status, error) {
        toastMessage(
          _t('FAVORITES_ERROR', { error }),
          3000,
          'alert alert-danger'
        )
      }
    })
  },
  manageFavorites(event) {
    event.preventDefault()
    let { target } = event
    if ($(target).prop('tagName') == 'I') {
      target = $(target).parent()
    }
    const resource = $(target).data('resource')
    const user = $(target).data('user')
    if ($(target).hasClass('user-favorite')) {
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
      $.ajax({
        method: 'GET',
        url: wiki.url(`api/triples/${resource}`),
        data: {
          property: FavoritesHelper.propertyName,
          user
        },
        success(data) {
          if (Array.isArray(data) && data.length > 0) {
            if (previousTag) {
              toastMessage(
                _t('FAVORITES_ERROR', { error: 'not deleted' }),
                3000,
                'alert alert-danger'
              )
            } else {
              $.ajax({
                method: 'POST',
                url: wiki.url(`api/triples/${resource}/delete`),
                data: {
                  property: FavoritesHelper.propertyName,
                  user
                },
                success() {
                  FavoritesHelper.deleteFirstFavorite(user, tags, lastTag)
                },
                error(xhr, status, error) {
                  toastMessage(
                    _t('FAVORITES_ERROR', { error }),
                    3000,
                    'alert alert-danger'
                  )
                }
              })
            }
          } else {
            const elem = $(`[data-resource="${resource}"]`)
            FavoritesHelper.updateElem(elem, 'delete', false)
            FavoritesHelper.deleteFirstFavorite(user, tags)
          }
        },
        error(xhr, status, error) {
          toastMessage(
            _t('FAVORITES_ERROR', { error }),
            3000,
            'alert alert-danger'
          )
        }
      })
    } else {
      toastMessage(
        _t('FAVORITES_ALL_DELETED'),
        3000,
        'alert alert-success'
      )
    }
  },
  deleteAll(user) {
    $.ajax({
      method: 'GET',
      url: wiki.url('api/triples'),
      data: {
        property: FavoritesHelper.propertyName,
        user
      },
      success(data) {
        if (Array.isArray(data) && data.length > 0) {
          FavoritesHelper.deleteFirstFavorite(user, data)
        }
      },
      error(xhr, status, error) {
        toastMessage(
          _t('FAVORITES_ERROR', { error }),
          3000,
          'alert alert-danger'
        )
      }
    })
  },
  init() {
    $('a.favorites').addClass('eventSet').on('click', FavoritesHelper.manageFavorites)
  }
}

FavoritesHelper.init()
$(document).on('yw-modal-open', () => {
  $('a.favorites:not(.eventSet)').addClass('eventSet').on('click', FavoritesHelper.manageFavorites)
})
