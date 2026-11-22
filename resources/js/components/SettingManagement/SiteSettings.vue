<template>
    <div class="card">
        <div class="card-header">
            <div class="d-flex flex-wrap justify-content-between align-items-center gap-2">
                <h4 class="mb-0">Site settings</h4>
                <button @click="save" class="btn btn-primary btn-sm" :disabled="saving || !loaded"><i class="bi bi-check-lg"></i> Save settings</button>
            </div>
            <ul class="nav nav-tabs card-header-tabs mt-3">
                <li v-for="item in tabs" :key="item.key" class="nav-item">
                    <a href="#" class="nav-link" :class="{ active: tab === item.key }" @click.prevent="tab = item.key"><i :class="`bi ${item.icon}`"></i> {{ item.label }}</a>
                </li>
            </ul>
        </div>

        <div v-if="loaded" class="card-body">
            <!-- General -->
            <div v-show="tab === 'general'" class="row">
                <div class="col-md-6"><BaseInput v-model="form.name" label="Site name" :error="errors.name?.[0]" /></div>
                <div class="col-md-6"><BaseInput v-model="form.short_name" label="Short name (sidebar, optional)" :error="errors.short_name?.[0]" /></div>
                <div class="col-md-12"><BaseInput v-model="form.motto" label="Motto / tagline" :error="errors.motto?.[0]" /></div>
                <div class="col-md-12">
                    <label class="form-label">Description</label>
                    <textarea v-model="form.description" rows="3" class="form-control" :class="{ 'is-invalid': errors.description }"></textarea>
                    <div v-if="errors.description" class="invalid-feedback">{{ errors.description[0] }}</div>
                </div>
                <div class="col-md-6 mt-2"><BaseInput v-model="form.copyright_holder" label="Copyright holder" :error="errors.copyright_holder?.[0]" /></div>
                <div class="col-md-6 mt-2"><BaseInput v-model="form.footer_text" label="Footer text" :error="errors.footer_text?.[0]" /></div>
                <div class="col-md-4" v-for="file in files" :key="file.key">
                    <label class="form-label">{{ file.label }}</label>
                    <div class="border rounded p-2 text-center mb-2" style="min-height: 90px">
                        <img v-if="previews[file.key]" :src="previews[file.key]" :alt="file.label" style="max-height: 70px; max-width: 100%" />
                        <span v-else class="text-muted small">No file</span>
                    </div>
                    <input type="file" class="form-control form-control-sm" :accept="file.accept" @change="pick(file.key, $event)" />
                    <div class="form-text">{{ file.hint }}</div>
                    <button v-if="previews[file.key]" @click="removeFile(file.key)" class="btn btn-link btn-sm text-danger p-0">Remove</button>
                    <div v-if="errors[file.key]" class="text-danger small">{{ errors[file.key][0] }}</div>
                </div>
            </div>

            <!-- Contacts -->
            <div v-show="tab === 'contacts'" class="row">
                <div class="col-md-6"><BaseInput v-model="form.support_email" label="Support email" :error="errors.support_email?.[0]" /></div>
                <div class="col-md-3"><BaseInput v-model="form.support_phone" label="Phone" :error="errors.support_phone?.[0]" /></div>
                <div class="col-md-3"><BaseInput v-model="form.whatsapp" label="WhatsApp" :error="errors.whatsapp?.[0]" /></div>
                <div class="col-md-12"><BaseInput v-model="form.address" label="Address" :error="errors.address?.[0]" /></div>
                <div class="col-md-6"><BaseInput v-model="form.website" label="Website" placeholder="https://" :error="errors.website?.[0]" /></div>
                <div class="col-md-6"><BaseInput v-model="form.facebook_url" label="Facebook" placeholder="https://" :error="errors.facebook_url?.[0]" /></div>
                <div class="col-md-6"><BaseInput v-model="form.instagram_url" label="Instagram" placeholder="https://" :error="errors.instagram_url?.[0]" /></div>
                <div class="col-md-6"><BaseInput v-model="form.x_url" label="X (Twitter)" placeholder="https://" :error="errors.x_url?.[0]" /></div>
                <div class="col-md-6"><BaseInput v-model="form.youtube_url" label="YouTube" placeholder="https://" :error="errors.youtube_url?.[0]" /></div>
                <div class="col-md-6"><BaseInput v-model="form.linkedin_url" label="LinkedIn" placeholder="https://" :error="errors.linkedin_url?.[0]" /></div>
            </div>

            <!-- Brand -->
            <div v-show="tab === 'brand'">
                <div class="row">
                    <div v-for="color in colors" :key="color.key" class="col-md-4 mb-3">
                        <label class="form-label">{{ color.label }}</label>
                        <div class="input-group">
                            <input type="color" class="form-control form-control-color" :value="colorValue(color.key)" @input="form[color.key] = $event.target.value" />
                            <input type="text" class="form-control" :value="form[color.key] ?? ''" :placeholder="defaults[color.key]" maxlength="7" @input="form[color.key] = $event.target.value || null" />
                            <button class="btn btn-outline-secondary" type="button" title="Use the default" @click="form[color.key] = null"><i class="bi bi-arrow-counterclockwise"></i></button>
                        </div>
                        <div v-if="errors[color.key]" class="text-danger small">{{ errors[color.key][0] }}</div>
                    </div>
                </div>
                <label class="form-label">Preview</label>
                <div class="border rounded p-3" :style="{ background: colorValue('brand_background'), color: colorValue('brand_text') }">
                    <h5 :style="{ color: colorValue('brand_primary') }">{{ form.name || defaults.name }}</h5>
                    <p class="mb-2">{{ form.motto || "Your motto appears here." }}</p>
                    <span class="btn btn-sm me-2" :style="{ background: colorValue('brand_primary'), color: '#fff' }">Primary button</span>
                    <span class="btn btn-sm me-2" :style="{ background: colorValue('brand_secondary'), color: '#fff' }">Secondary</span>
                    <span class="badge" :style="{ background: colorValue('brand_accent'), color: colorValue('brand_primary') }">Accent</span>
                </div>
            </div>

            <!-- SEO -->
            <div v-show="tab === 'seo'" class="row">
                <div class="col-md-12">
                    <BaseInput v-model="form.seo_title" label="SEO title (up to 70 characters)" :error="errors.seo_title?.[0]" />
                    <div class="form-text mb-2">{{ (form.seo_title ?? "").length }}/70</div>
                </div>
                <div class="col-md-12">
                    <label class="form-label">SEO description (up to 320 characters)</label>
                    <textarea v-model="form.seo_description" rows="3" class="form-control" :class="{ 'is-invalid': errors.seo_description }"></textarea>
                    <div class="form-text">{{ (form.seo_description ?? "").length }}/320</div>
                    <div v-if="errors.seo_description" class="invalid-feedback">{{ errors.seo_description[0] }}</div>
                </div>
                <div class="col-md-12 mt-2"><BaseInput v-model="form.seo_keywords" label="Keywords (comma separated)" :error="errors.seo_keywords?.[0]" /></div>
                <div class="col-md-6 mt-2">
                    <label class="form-label">Share image (shown when the site is shared)</label>
                    <div v-if="previews.og_image" class="mb-2"><img :src="previews.og_image" alt="Share image" style="max-height: 90px; max-width: 100%" /></div>
                    <input type="file" class="form-control form-control-sm" accept="image/png,image/jpeg,image/webp" @change="pick('og_image', $event)" />
                    <button v-if="previews.og_image" @click="removeFile('og_image')" class="btn btn-link btn-sm text-danger p-0">Remove</button>
                    <div v-if="errors.og_image" class="text-danger small">{{ errors.og_image[0] }}</div>
                </div>
                <div class="col-md-6 mt-4">
                    <div class="form-check form-switch">
                        <input id="noindex" v-model="form.noindex_dashboard" type="checkbox" class="form-check-input" />
                        <label for="noindex" class="form-check-label">Hide the dashboard and sign-in pages from search engines</label>
                    </div>
                </div>
                <div class="col-12 mt-3">
                    <div class="border rounded p-3 bg-body-tertiary">
                        <div class="small text-muted">Search result preview</div>
                        <div class="fw-semibold" style="color: #1a0dab">{{ form.seo_title || `${form.name || defaults.name} · ${form.motto || "Savings group, SACCOS and VICOBA management"}` }}</div>
                        <div class="small text-success">{{ origin }}</div>
                        <div class="small">{{ form.seo_description || form.description || defaults.description }}</div>
                    </div>
                </div>
            </div>

            <!-- Other -->
            <div v-show="tab === 'other'" class="row">
                <div class="col-md-12"><BaseInput v-model="form.maintenance_banner" label="Banner message (shown at the top of every page when filled)" :error="errors.maintenance_banner?.[0]" /></div>
                <div class="col-md-6"><BaseInput v-model="form.terms_url" label="Terms and conditions link" placeholder="https://" :error="errors.terms_url?.[0]" /></div>
                <div class="col-md-6"><BaseInput v-model="form.privacy_url" label="Privacy policy link" placeholder="https://" :error="errors.privacy_url?.[0]" /></div>
            </div>
        </div>
        <div v-else class="card-body text-center text-muted py-4">Loading...</div>
    </div>
</template>

<script>
const FIELDS = [
    "name", "short_name", "motto", "description", "footer_text", "copyright_holder", "support_email", "support_phone", "whatsapp", "address", "website",
    "facebook_url", "instagram_url", "x_url", "youtube_url", "linkedin_url", "brand_primary", "brand_secondary", "brand_accent", "brand_background", "brand_text",
    "seo_title", "seo_description", "seo_keywords", "maintenance_banner", "terms_url", "privacy_url",
];

export default {
    data() {
        return {
            tab: "general",
            tabs: [
                { key: "general", label: "General", icon: "bi-info-circle" },
                { key: "contacts", label: "Contacts", icon: "bi-telephone" },
                { key: "brand", label: "Brand colours", icon: "bi-palette" },
                { key: "seo", label: "SEO", icon: "bi-search" },
                { key: "other", label: "Other", icon: "bi-three-dots" },
            ],
            files: [
                { key: "logo", label: "Logo", accept: "image/png,image/jpeg,image/webp", hint: "PNG, JPG or WebP, up to 2 MB." },
                { key: "favicon", label: "Favicon", accept: "image/png,image/x-icon,image/jpeg,image/webp", hint: "Square PNG or ICO, up to 512 KB." },
            ],
            colors: [
                { key: "brand_primary", label: "Primary" },
                { key: "brand_secondary", label: "Secondary" },
                { key: "brand_accent", label: "Accent" },
                { key: "brand_background", label: "Background" },
                { key: "brand_text", label: "Text" },
            ],
            form: {},
            defaults: {},
            previews: {},
            uploads: {},
            removed: {},
            errors: {},
            loaded: false,
            saving: false,
            origin: window.location.origin,
        };
    },

    created() {
        this.load();
    },

    methods: {
        load() {
            axios
                .get("/site-settings")
                .then((response) => this.fill(response.data.data))
                .catch((error) => this.$toast.error(error.response?.data?.message ?? "Something went wrong. Please try again."));
        },

        fill(data) {
            this.defaults = data.defaults;
            this.form = { ...Object.fromEntries(FIELDS.map((field) => [field, data[field] ?? null])), noindex_dashboard: data.noindex_dashboard ?? true };
            this.previews = { logo: data.logo_url, favicon: data.favicon_url, og_image: data.og_image_url };
            this.uploads = {};
            this.removed = {};
            this.errors = {};
            this.loaded = true;
        },

        colorValue(key) {
            return /^#[0-9a-fA-F]{6}$/.test(this.form[key] ?? "") ? this.form[key] : this.defaults[key];
        },

        pick(key, event) {
            const file = event.target.files[0];

            if (!file) {
                return;
            }

            this.uploads[key] = file;
            this.removed[key] = false;
            this.previews[key] = URL.createObjectURL(file);
        },

        removeFile(key) {
            delete this.uploads[key];
            this.removed[key] = true;
            this.previews[key] = null;
        },

        save() {
            const body = new FormData();

            FIELDS.forEach((field) => {
                body.append(field, this.form[field] ?? "");
            });
            body.append("noindex_dashboard", this.form.noindex_dashboard ? "1" : "0");
            Object.entries(this.uploads).forEach(([key, file]) => body.append(key, file));
            Object.entries(this.removed).forEach(([key, value]) => value && body.append(`remove_${key}`, "1"));

            this.saving = true;
            this.errors = {};

            axios
                .post("/site-settings", body)
                .then((response) => {
                    this.$toast.success(response.data.message);
                    this.fill(response.data.data);
                })
                .catch((error) => {
                    if (error.response?.status === 422) {
                        this.errors = error.response.data.errors ?? {};
                        this.$toast.error("Please correct the highlighted fields.");
                    } else {
                        this.$toast.error(error.response?.data?.message ?? "Something went wrong. Please try again.");
                    }
                })
                .finally(() => (this.saving = false));
        },
    },
};
</script>
