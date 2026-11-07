<template>
  <div class="search-input">
    <input
      type="text"
      class="form-control"
      v-model="searchQuery"
      @input="onSearch"
      placeholder="Search..."
    />
  </div>
</template>

<script>
import debounce from "../../utils/debounce";

export default {
  emits: ["search"],

  data() {
    return {
      searchQuery: "",
    };
  },

  created() {
    this.onSearch = debounce(() => this.$emit("search", this.searchQuery), 500);
  },
};
</script>
