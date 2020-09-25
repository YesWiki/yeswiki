export default {
  template: `
    <h3>Filtres / Facettes</h3>
    <!-- Facettes Config -->
    <div class="filter-group-form" v-for="filter in filterGroups">
      <div class="col-sm-4 form-group">
        <label class="control-label">Champ</label>
        <select v-model="filter.field" class="form-control">
          <option v-for="field in selectedForm.prepared" v-if="field.label" :value="field.id">{{ field.label }} - {{ field.id }}</option>
        </select>
      </div>
      <div class="col-sm-4 form-group">
        <label class="control-label">Titre</label>
        <input type="text" v-model="filter.title" class="form-control" />
      </div>
      <div class="col-sm-3 form-group">
        <label class="control-label">Icone</label>
        <div class="input-group">
          <icon-picker v-model="filter.icon"></icon-picker>
        </div>
      </div>
      <div class="form-group col-sm-1">
        <button class="btn btn-default btn-icon" @click="removeFilterGroup(filter)">
          <i class="btn-remove-group fa fa-times"></i>
        </button>
      </div>
    </div>
    <!-- Add Facette -->
    <button @click="addEmptyFilterGroup" class="btn btn-info btn-add-group">Ajouter une Facette</button>`
}
