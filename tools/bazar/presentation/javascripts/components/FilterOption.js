export default {
  props: ['option'],
  template: `
    <div class="option-container">
      <div class="checkbox">
        <label>
          <input class="filter-checkbox" type="checkbox"
                v-model="option.checked"
                :value="option.value">
          <span>
            <span v-html="option.label"></span>
            <span class="nb" v-if="option.nb">({{ option.nb }})</span>
          </span>
        </label>
      </div>
      <div class="children">
        <FilterOption v-for="childOption, id in option.children" :key="id" :option="childOption" />
      </div>
    </div>
  `
}
