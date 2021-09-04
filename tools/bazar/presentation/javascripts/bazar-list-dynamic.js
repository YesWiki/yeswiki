document.querySelectorAll(".bazar-list.dynamic").forEach(domElement =>{
  new Vue({
    el: domElement,
    delimiters: ['${', '}'],
    data: {
      entries: []
    },
    mounted() {
      this.entries = JSON.parse(this.$el.dataset.entries)
    }
  })
})
