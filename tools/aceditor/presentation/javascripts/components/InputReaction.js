import InputMultiInput from "./InputMultiInput.js";

export default {
  mixins: [InputMultiInput],
  methods: {
    parseNewValues(newValues) {
      if (newValues.labels) {
        this.elements = [];
        let labels = newValues.labels.split(",");
        let images = newValues.images ? newValues.images.split(",") : [];
        for (var i = 0; i < labels.length; i++) {
          this.elements.push({
            label: labels[i],
            image: images.length >= i ? images[i] : "",
          });
        }
      }
    },
    getValues() {
      return {
        labels: this.elements
          .map((g) => g.label)
          .filter((e) => e != "")
          .join(","),
        images: this.elements
          .map((g) => g.image)
          .filter((e) => e != "")
          .join(","),
      };
    },
  },
};
