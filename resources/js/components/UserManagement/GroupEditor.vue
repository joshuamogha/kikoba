<template>
    <div>
        <button @click="open" class="btn btn-primary btn-sm">
            <i class="bi bi-pencil"></i> Edit group
        </button>

        <LargeModal v-if="modal" @close="close">
            <template v-slot:header>
                <h4>Edit group</h4>
            </template>
            <template v-slot:body>
                <div class="row">
                    <div class="col-md-6">
                        <BaseInput v-model="form.name" label="Group name" :error="errors.name?.[0]" />
                    </div>
                    <div class="col-md-6">
                        <BaseInput v-model="form.registration_number" label="Registration number"
                            :error="errors.registration_number?.[0]" />
                    </div>
                    <div class="col-md-6">
                        <DatePicker v-model="form.established_date" label="Established" max-date="today"
                            :error="errors.established_date?.[0]" />
                    </div>
                    <div class="col-md-6">
                        <BaseInput v-model="form.maximum_members" type="number" min="2" label="Maximum members"
                            :error="errors.maximum_members?.[0]" />
                    </div>
                    <div class="col-md-12">
                        <BaseInput v-model="form.description" label="Description" :error="errors.description?.[0]" />
                    </div>
                </div>
                <small class="text-muted">The group type and status cannot be changed here.</small>
            </template>
            <template v-slot:footer>
                <button @click="update" class="btn btn-primary" :disabled="saving">Update</button>
            </template>
        </LargeModal>
    </div>
</template>

<script>
export default {
    props: {
        group: { type: Object, required: true },
    },

    data() {
        return {
            modal: false,
            saving: false,
            errors: {},
            form: {},
        };
    },

    methods: {
        open() {
            this.form = {
                name: this.group.name ?? "",
                registration_number: this.group.registration_number ?? "",
                established_date: this.group.established_date ?? "",
                maximum_members: this.group.maximum_members ?? "",
                description: this.group.description ?? "",
            };
            this.errors = {};
            this.modal = true;
        },

        close() {
            this.modal = false;
            this.errors = {};
        },

        update() {
            this.saving = true;

            axios
                .put(`/groups/${this.group.id}`, this.form)
                .then((response) => {
                    this.$toast.success(response.data.message);
                    setTimeout(() => window.location.reload(), 700);
                })
                .catch((error) => {
                    const data = error.response?.data;

                    if (error.response?.status === 422 && data?.errors) {
                        this.errors = data.errors;
                    } else {
                        this.$toast.error(data?.error ?? data?.message ?? "Something went wrong. Please try again.");
                    }

                    this.saving = false;
                });
        },
    },
};
</script>
