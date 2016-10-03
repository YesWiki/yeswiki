;(function () {

  var vSortable = {}
  var Sortable = typeof require === 'function'
      ? require('sortablejs')
      : window.Sortable

  if (!Sortable) {
    throw new Error('[vue-sortable] cannot locate Sortable.js.')
  }

  // exposed global options
  vSortable.config = {}

  vSortable.install = function (Vue) {
    Vue.directive('sortable', {
      bind: function (el, options) {
        var that = this;
        options = eval('(' + options.expression + ')') || {}
        this.sortable = new Sortable(el, options)
        this.sortable.option("onUpdate", function (e) {
            //that.value.splice(e.newIndex, 0, that.value.splice(e.oldIndex, 1)[0]);
            console.log('sortable onUpdate')
            that.set(e.newIndex)

        })
      },
      update: function (newValue, oldValue) {
        console.log('vuejs sortable update');
        //$(this.el).val(value).trigger('change')
      },
      unbind: function () {
        //exit
      }
    })
  }

  if (typeof exports == "object") {
    module.exports = vSortable
  } else if (typeof define == "function" && define.amd) {
    define([], function () {
      return vSortable
    })
  } else if (window.Vue) {
    window.vSortable = vSortable
    Vue.use(vSortable)
  }

})()
