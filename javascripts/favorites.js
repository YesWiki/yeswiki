const FavoritesHelper = {
  propertyName: "https://yeswiki.net/vocabulary/favorite",
  updateElem: function (elem, mode){
    if (mode == "add"){
      $(elem).addClass('user-favorite');
      $(elem).find('i')
        .removeClass('far fa-star')
        .addClass('fas fa-star');
      $(elem).tooltip("destroy");
      $(elem).attr("title",_t('FAVORITES_REMOVE'));
      $(elem).removeData("original-title");
      toastMessage(
        _t('FAVORITES_ADDED'),
        3000,
        "alert alert-success"
      );
    } else {
      $(elem).removeClass('user-favorite');
      $(elem).find('i')
        .removeClass('fas fa-star')
        .addClass('far fa-star');
        $(elem).tooltip("destroy");
      $(elem).attr("title",_t('FAVORITES_ADD'));
      $(elem).removeData("original-title");
      toastMessage(
        _t('FAVORITES_REMOVED'),
        3000,
        "alert alert-warning"
      );
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
          FavoritesHelper.updateElem(elem,"add");
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
          FavoritesHelper.updateElem(elem,"delete");
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
  init: function (){
    // polyfill placeholder
    (function ($) {
      $("a.favorites").click(FavoritesHelper.manageFavorites);
    })(jQuery);
  }
};

FavoritesHelper.init();