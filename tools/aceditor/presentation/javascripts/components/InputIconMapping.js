export default {
  computed: {
    iconOptions() {
      if (!this.selectedForm || !this.values.iconfield) return []
      var config = this.selectedForm.prepared.find(e => e.id == this.values.iconfield)
      return config ? config.values.label : []
    }
  },
  template: `
    <!-- Icons -->
    <div class="form-group">
      <label class="control-label">Champ pour la couleur</label>
      <select v-model="values.iconfield" class="form-control">
        <option value=""></option>
        <option v-for="field in selectedForm.prepared.filter(a => typeof a.values == 'object')" :value="field.id">{{ field.id }}</option>
      </select>
    </div>
    <div class="filter-group-form" v-if="values.iconfield" v-for="mapping in iconMapping">
      <div class="col-sm-6 form-group">
        <label class="control-label">Valeur</label>
        <select v-model="mapping.id" class="form-control">
          <option v-for="(optionName, optionId) in iconOptions" :value="optionId">{{ optionName }}</option>
        </select>
      </div>
      <div class="col-sm-5 form-group">
        <label class="control-label">Icone</label>
        <icon-picker v-model="mapping.icon"></icon-picker>
      </div>
      <div class="form-group col-sm-1">
        <button class="btn btn-default btn-icon" @click="removeIconMapping(mapping)">
          <i class="btn-remove-group fa fa-times"></i>
        </button>
      </div>
    </div>
    <button v-if="values.iconfield" @click="addEmptyIconMapping" class="btn btn-info btn-icon btn-add-group">
      <i class="fa fa-plus"></i>
    </button>
    `
}
