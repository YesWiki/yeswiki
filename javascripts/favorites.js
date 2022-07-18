const FavoritesHelper = {
  propertyName: "https://yeswiki.net/vocabulary/favorite",
  updateElem: function (elem, mode, withMessage = true){
    if (mode == "add"){
      $(elem).addClass('user-favorite');
      $(elem).find('i')
        .removeClass('far fa-star')
        .addClass('fas fa-star');
      $(elem).tooltip("destroy");
      $(elem).attr("title",_t('FAVORITES_REMOVE'));
      $(elem).removeData("original-title");
      if (withMessage) {
        toastMessage(
          _t('FAVORITES_ADDED'),
          3000,
          "alert alert-success"
        );
      }
    } else {
      $(elem).removeClass('user-favorite');
      $(elem).find('i')
        .removeClass('fas fa-star')
        .addClass('far fa-star');
        $(elem).tooltip("destroy");
      $(elem).attr("title",_t('FAVORITES_ADD'));
      $(elem).removeData("original-title");
      if (withMessage) {
        toastMessage(
          _t('FAVORITES_REMOVED'),
          3000,
          "alert alert-warning"
        );
      }
      // remove linked favorite 1.5s after
      setTimeout(function(){
        $(elem).each(function(){
          if (!$(this).hasClass('user-favorite')){
            let linkedFavoriteId = $(this).attr("data-linkedFavoriteid");
            if (linkedFavoriteId != undefined && linkedFavoriteId.length > 0){
              let linkedFavorite = $(`#${linkedFavoriteId}`);
              if (linkedFavorite != undefined && linkedFavorite.length > 0){
                $(linkedFavorite).remove();
                $(this).remove();
              } else {
                console.warn(`#linkedFavoriteId was waited but not found : ${JSON.stringify(linkedFavorite)}`);
              }
            }
          }
        });
      }, 1500);
    }
  },
  addFavorite: function (resource, user, elem, checkNotEmpty = false){
    $.ajax({
      method: "GET",
      url: wiki.url(`api/triples/${resource}`),
      data: {
        property: FavoritesHelper.propertyName,
        user: user
      },
      success: function(data){
        if (!Array.isArray(data) || data.length == 0){
          if (checkNotEmpty){
            toastMessage(
              _t('FAVORITES_ERROR',{error:"not created"}),
              3000,
              "alert alert-danger"
            );
          } else {
            $.ajax({
              method: "POST",
              url: wiki.url(`api/triples/${resource}`),
              data: {
                property: FavoritesHelper.propertyName,
                user: user
              },
              success: function(){
                FavoritesHelper.addFavorite(resource, user, elem,true);
              },
              error: function(xhr,status,error){
                toastMessage(
                  _t('FAVORITES_ERROR',{error:error}),
                  3000,
                  "alert alert-danger"
                );
              }
            });
          }
        } else {
          FavoritesHelper.updateElem($(`[data-resource=${resource}]`),"add");
        }
      },
      error: function(xhr,status,error){
        toastMessage(
          _t('FAVORITES_ERROR',{error:error}),
          3000,
          "alert alert-danger"
        );
      }
    });
  },
  deleteFavorite: function (resource, user, elem, checkEmpty = false){
    $.ajax({
      method: "GET",
      url: wiki.url(`api/triples/${resource}`),
      data: {
        property: FavoritesHelper.propertyName,
        user: user
      },
      success: function(data){
        if (Array.isArray(data) && data.length > 0){
          if (checkEmpty){
            toastMessage(
              _t('FAVORITES_ERROR',{error:"not deleted"}),
              3000,
              "alert alert-danger"
            );
          } else {
            $.ajax({
              method: "POST",
              url: wiki.url(`api/triples/${resource}/delete`),
              data: {
                property: FavoritesHelper.propertyName,
                user: user
              },
              success: function(){
                FavoritesHelper.deleteFavorite(resource, user, elem,true);
              },
              error: function(xhr,status,error){
                toastMessage(
                  _t('FAVORITES_ERROR',{error:error}),
                  3000,
                  "alert alert-danger"
                );
              }
            });
          }
        } else {
          FavoritesHelper.updateElem($(`[data-resource=${resource}]`),"delete");
        }
      },
      error: function(xhr,status,error){
        toastMessage(
          _t('FAVORITES_ERROR',{error:error}),
          3000,
          "alert alert-danger"
        );
      }
    });
  },
  manageFavorites: function(event){
    event.preventDefault()
    let target = event.target;
    if ($(target).prop('tagName') == 'I'){
      target = $(target).parent();
    }
    let resource = $(target).data("resource");
    let user = $(target).data("user");
    if ($(target).hasClass('user-favorite')){
      FavoritesHelper.deleteFavorite(resource,user,target);
    } else {
      FavoritesHelper.addFavorite(resource,user,target);
    }
  },
  deleteFirstFavorite: function(user,tagsValue, previousTag = null){
    let tags = tagsValue;
    if (previousTag || (Array.isArray(tags) && tags.length > 0)){
      let lastTag = previousTag ? previousTag : tags.pop();
      let resource = lastTag.resource;
      $.ajax({
        method: "GET",
        url: wiki.url(`api/triples/${resource}`),
        data: {
          property: FavoritesHelper.propertyName,
          user: user
        },
        success: function(data){
          if (Array.isArray(data) && data.length > 0){
            if (previousTag){
              toastMessage(
                _t('FAVORITES_ERROR',{error:"not deleted"}),
                3000,
                "alert alert-danger"
              );
            } else {
              $.ajax({
                method: "POST",
                url: wiki.url(`api/triples/${resource}/delete`),
                data: {
                  property: FavoritesHelper.propertyName,
                  user: user
                },
                success: function(){
                  FavoritesHelper.deleteFirstFavorite(user,tags,lastTag);
                },
                error: function(xhr,status,error){
                  toastMessage(
                    _t('FAVORITES_ERROR',{error:error}),
                    3000,
                    "alert alert-danger"
                  );
                }
              });
            }
          } else {
            let elem = $(`[data-resource=${resource}]`);
            FavoritesHelper.updateElem(elem,"delete",false);
            FavoritesHelper.deleteFirstFavorite(user,tags);
          }
        },
        error: function(xhr,status,error){
          toastMessage(
            _t('FAVORITES_ERROR',{error:error}),
            3000,
            "alert alert-danger"
          );
        }
      });
    } else {
      toastMessage(
        _t('FAVORITES_ALL_DELETED'),
        3000,
        "alert alert-success"
      );
    }
  },
  deleteAll: function(user){
    $.ajax({
      method: "GET",
      url: wiki.url(`api/triples`),
      data: {
        property: FavoritesHelper.propertyName,
        user: user
      },
      success: function(data){
        if (Array.isArray(data) && data.length > 0){
          FavoritesHelper.deleteFirstFavorite(user,data);
        }
      },
      error: function(xhr,status,error){
        toastMessage(
          _t('FAVORITES_ERROR',{error:error}),
          3000,
          "alert alert-danger"
        );
      }
    });
  },
  init: function (){
    $("a.favorites").addClass("eventSet").on("click",FavoritesHelper.manageFavorites);
  }
};

FavoritesHelper.init();
$(document).on("yw-modal-open",function(){
  $("a.favorites:not(.eventSet)").addClass("eventSet").on("click",FavoritesHelper.manageFavorites);
});