<template>
  <div>
    <div
      class="modal"
      style="display: block"
      tabindex="-1"
      role="dialog"
    >
      <div
        class="modal-dialog modal-dialog-scrollable modal-md"
        role="document"
      >
        <div class="modal-content">
          <!-- Modal Header -->
          <div class="modal-header">
            <h4 class="modal-title">
              <slot name="header"> </slot>
            </h4>

            <button
              type="button"
              class="btn-close"
              data-bs-dismiss="modal"
              @click="$emit('close')"
              aria-label="Close"
            ></button>
          </div>
          <!-- Modal body -->
          <div class="modal-body">
            <slot name="body"></slot>
          </div>
          <!-- Modal footer -->
          <div class="modal-footer">
            <slot name="footer"></slot>
            <button
              @click="$emit('close')"
              class="btn btn-danger"
              data-bs-dismiss="modal"
            >
              Close
            </button>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>
<script>
export default {};
</script>

<style scoped>
</style>
