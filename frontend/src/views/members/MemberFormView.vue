<script setup lang="ts">
import { ref, computed, onMounted, watch } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { useMembersStore } from '@/stores/members'
import type { StoreMemberPayload, UpdateMemberPayload, Gender } from '@/types/member'

const route  = useRoute()
const router = useRouter()
const store  = useMembersStore()

// Is this an edit (has :id param) or a create?
const isEdit = computed(() => !!route.params.id)
const memberId = computed(() => Number(route.params.id))

// ── Form state ────────────────────────────────────────────────────────────────
const fullName              = ref('')
const dateOfBirth           = ref('')
const gender                = ref<Gender>('male')
const phone                 = ref('')
const emergencyContactName  = ref('')
const emergencyContactPhone = ref('')
const registrationDate      = ref(new Date().toISOString().slice(0, 10))
const photoFile             = ref<File | null>(null)
const photoPreview          = ref<string | null>(null)

// Shown when the user needs to confirm a duplicate phone submission
const showDuplicateDialog = ref(false)

// ── Lifecycle ─────────────────────────────────────────────────────────────────
onMounted(async () => {
  store.clearErrors()
  if (isEdit.value) {
    await store.fetchMember(memberId.value)
    populateForm()
  }
})

watch(() => store.duplicatePhoneWarning, (isDuplicate) => {
  if (isDuplicate) showDuplicateDialog.value = true
})

// ── Helpers ───────────────────────────────────────────────────────────────────
function populateForm(): void {
  const m = store.currentMember
  if (!m) return
  fullName.value              = m.full_name
  dateOfBirth.value           = m.date_of_birth
  gender.value                = m.gender
  phone.value                 = m.phone
  emergencyContactName.value  = m.emergency_contact_name
  emergencyContactPhone.value = m.emergency_contact_phone
  registrationDate.value      = m.registration_date
  photoPreview.value          = m.photo_url
}

function onPhotoChange(event: Event): void {
  const input = event.target as HTMLInputElement
  const file  = input.files?.[0]
  if (!file) return

  // Client-side pre-validation (mirrors server-side rule)
  const allowedTypes = ['image/jpeg', 'image/png']
  const maxBytes     = 5 * 1024 * 1024 // 5 MB

  if (!allowedTypes.includes(file.type)) {
    store.fieldErrors.photo = 'Profile photo must be a JPEG or PNG file.'
    return
  }
  if (file.size > maxBytes) {
    store.fieldErrors.photo = 'Profile photo must not exceed 5 MB.'
    return
  }

  delete store.fieldErrors.photo
  photoFile.value = file
  photoPreview.value = URL.createObjectURL(file)
}

function removePhoto(): void {
  photoFile.value    = null
  photoPreview.value = null
}

// ── Submit ────────────────────────────────────────────────────────────────────
async function handleSubmit(confirmDuplicate = false): Promise<void> {
  store.clearErrors()
  showDuplicateDialog.value = false

  if (isEdit.value) {
    const payload: UpdateMemberPayload = {
      full_name:               fullName.value,
      date_of_birth:           dateOfBirth.value,
      gender:                  gender.value,
      phone:                   phone.value,
      emergency_contact_name:  emergencyContactName.value,
      emergency_contact_phone: emergencyContactPhone.value,
    }
    const updated = await store.updateMember(
      memberId.value,
      payload,
      photoFile.value ?? undefined,
    )
    if (updated) router.push({ name: 'member-detail', params: { id: updated.id } })
  } else {
    const payload: StoreMemberPayload = {
      full_name:               fullName.value,
      date_of_birth:           dateOfBirth.value,
      gender:                  gender.value,
      phone:                   phone.value,
      emergency_contact_name:  emergencyContactName.value,
      emergency_contact_phone: emergencyContactPhone.value,
      registration_date:       registrationDate.value,
      confirm_duplicate:       confirmDuplicate || undefined,
    }
    const created = await store.createMember(payload, photoFile.value ?? undefined)
    if (created) router.push({ name: 'member-detail', params: { id: created.id } })
  }
}

function confirmDuplicateAndSubmit(): void {
  handleSubmit(true)
}

function fieldError(field: string): string | undefined {
  return store.fieldErrors[field]
}
</script>

<template>
  <div class="page">
    <!-- ── Header ──────────────────────────────────────────────────────────── -->
    <div class="page-header">
      <div>
        <button class="btn-back" @click="router.back()" aria-label="Go back">← Back</button>
        <h1 class="page-title">{{ isEdit ? 'Edit Member' : 'Add Member' }}</h1>
      </div>
    </div>

    <!-- ── Loading skeleton (edit mode only) ──────────────────────────────── -->
    <div v-if="isEdit && store.loading" class="skeleton-form" aria-busy="true">
      <div class="skeleton skeleton-line" v-for="n in 8" :key="n" />
    </div>

    <!-- ── Form ───────────────────────────────────────────────────────────── -->
    <form
      v-else
      class="form-card"
      @submit.prevent="handleSubmit()"
      novalidate
      aria-label="Member form"
    >
      <!-- General error -->
      <div v-if="store.error" class="alert alert-error" role="alert">
        {{ store.error }}
      </div>

      <!-- ── Personal info ─────────────────────────────────────────────── -->
      <fieldset class="fieldset">
        <legend class="fieldset-legend">Personal Information</legend>

        <div class="field">
          <label for="full_name" class="label">Full Name <span class="required">*</span></label>
          <input
            id="full_name"
            v-model="fullName"
            type="text"
            class="input"
            :class="{ 'input-error': fieldError('full_name') }"
            maxlength="100"
            required
            aria-required="true"
            :aria-describedby="fieldError('full_name') ? 'full_name_err' : undefined"
          />
          <p v-if="fieldError('full_name')" id="full_name_err" class="field-error" role="alert">
            {{ fieldError('full_name') }}
          </p>
        </div>

        <div class="field-row">
          <div class="field">
            <label for="date_of_birth" class="label">Date of Birth <span class="required">*</span></label>
            <input
              id="date_of_birth"
              v-model="dateOfBirth"
              type="date"
              class="input"
              :class="{ 'input-error': fieldError('date_of_birth') }"
              required
              aria-required="true"
              :max="new Date().toISOString().slice(0, 10)"
            />
            <p v-if="fieldError('date_of_birth')" class="field-error" role="alert">
              {{ fieldError('date_of_birth') }}
            </p>
          </div>

          <div class="field">
            <label for="gender" class="label">Gender <span class="required">*</span></label>
            <select
              id="gender"
              v-model="gender"
              class="input"
              :class="{ 'input-error': fieldError('gender') }"
              required
            >
              <option value="male">Male</option>
              <option value="female">Female</option>
              <option value="other">Other</option>
            </select>
            <p v-if="fieldError('gender')" class="field-error" role="alert">
              {{ fieldError('gender') }}
            </p>
          </div>
        </div>

        <div class="field">
          <label for="phone" class="label">Phone Number <span class="required">*</span></label>
          <input
            id="phone"
            v-model="phone"
            type="tel"
            class="input"
            :class="{ 'input-error': fieldError('phone') }"
            maxlength="15"
            placeholder="+911234567890"
            required
            aria-required="true"
          />
          <p v-if="fieldError('phone')" class="field-error" role="alert">
            {{ fieldError('phone') }}
          </p>
        </div>
      </fieldset>

      <!-- ── Emergency contact ──────────────────────────────────────────── -->
      <fieldset class="fieldset">
        <legend class="fieldset-legend">Emergency Contact</legend>

        <div class="field-row">
          <div class="field">
            <label for="ec_name" class="label">Contact Name <span class="required">*</span></label>
            <input
              id="ec_name"
              v-model="emergencyContactName"
              type="text"
              class="input"
              :class="{ 'input-error': fieldError('emergency_contact_name') }"
              maxlength="100"
              required
            />
            <p v-if="fieldError('emergency_contact_name')" class="field-error" role="alert">
              {{ fieldError('emergency_contact_name') }}
            </p>
          </div>

          <div class="field">
            <label for="ec_phone" class="label">Contact Phone <span class="required">*</span></label>
            <input
              id="ec_phone"
              v-model="emergencyContactPhone"
              type="tel"
              class="input"
              :class="{ 'input-error': fieldError('emergency_contact_phone') }"
              maxlength="15"
              required
            />
            <p v-if="fieldError('emergency_contact_phone')" class="field-error" role="alert">
              {{ fieldError('emergency_contact_phone') }}
            </p>
          </div>
        </div>
      </fieldset>

      <!-- ── Registration date (create only) ───────────────────────────── -->
      <fieldset v-if="!isEdit" class="fieldset">
        <legend class="fieldset-legend">Registration</legend>
        <div class="field">
          <label for="registration_date" class="label">Registration Date</label>
          <input
            id="registration_date"
            v-model="registrationDate"
            type="date"
            class="input"
            :max="new Date().toISOString().slice(0, 10)"
          />
        </div>
      </fieldset>

      <!-- ── Profile photo ──────────────────────────────────────────────── -->
      <fieldset class="fieldset">
        <legend class="fieldset-legend">Profile Photo <span class="optional">(optional)</span></legend>
        <div class="photo-section">
          <div class="photo-preview" aria-label="Photo preview">
            <img
              v-if="photoPreview"
              :src="photoPreview"
              alt="Profile photo preview"
              class="photo-img"
            />
            <span v-else class="photo-placeholder" aria-hidden="true">No photo</span>
          </div>
          <div class="photo-controls">
            <label for="photo" class="btn btn-secondary" role="button">
              {{ photoPreview ? 'Change photo' : 'Upload photo' }}
              <input
                id="photo"
                type="file"
                accept="image/jpeg,image/png"
                class="visually-hidden"
                @change="onPhotoChange"
              />
            </label>
            <button
              v-if="photoPreview"
              type="button"
              class="btn btn-ghost"
              @click="removePhoto"
            >
              Remove
            </button>
            <p class="photo-hint">JPEG or PNG · Max 5 MB</p>
            <p v-if="fieldError('photo')" class="field-error" role="alert">
              {{ fieldError('photo') }}
            </p>
          </div>
        </div>
      </fieldset>

      <!-- ── Form actions ───────────────────────────────────────────────── -->
      <div class="form-actions">
        <button type="button" class="btn btn-secondary" @click="router.back()">
          Cancel
        </button>
        <button type="submit" class="btn btn-primary" :disabled="store.saving">
          <span v-if="store.saving">Saving…</span>
          <span v-else>{{ isEdit ? 'Save Changes' : 'Create Member' }}</span>
        </button>
      </div>
    </form>

    <!-- ── Duplicate phone confirmation dialog ─────────────────────────────── -->
    <Teleport to="body">
      <div
        v-if="showDuplicateDialog"
        class="dialog-backdrop"
        role="dialog"
        aria-modal="true"
        aria-labelledby="dup-dialog-title"
      >
        <div class="dialog">
          <h2 id="dup-dialog-title" class="dialog-title">Duplicate Phone Number</h2>
          <p class="dialog-body">
            A member with the phone number <strong>{{ phone }}</strong> is already
            registered in this gym. Do you want to create this member anyway?
          </p>
          <div class="dialog-actions">
            <button class="btn btn-secondary" @click="showDuplicateDialog = false">
              Cancel
            </button>
            <button class="btn btn-primary" @click="confirmDuplicateAndSubmit">
              Yes, create anyway
            </button>
          </div>
        </div>
      </div>
    </Teleport>
  </div>
</template>

<style scoped>
.page { padding: 1.5rem 2rem; max-width: 720px; margin: 0 auto; }
.page-header { margin-bottom: 1.5rem; }
.btn-back { background: none; border: none; color: #6b7280; cursor: pointer; font-size: 0.88rem; padding: 0; margin-bottom: 0.375rem; }
.btn-back:hover { color: #111827; }
.page-title { font-size: 1.5rem; font-weight: 700; margin: 0; }
.form-card { background: #fff; border: 1px solid #e5e7eb; border-radius: 10px; padding: 1.75rem; display: flex; flex-direction: column; gap: 1.5rem; }
.fieldset { border: none; margin: 0; padding: 0; }
.fieldset-legend { font-size: 0.95rem; font-weight: 600; color: #374151; margin-bottom: 1rem; padding-bottom: 0.375rem; border-bottom: 1px solid #f3f4f6; width: 100%; }
.optional { font-weight: 400; color: #9ca3af; font-size: 0.85rem; }
.field { display: flex; flex-direction: column; gap: 0.25rem; }
.field-row { display: grid; grid-template-columns: 1fr 1fr; gap: 1rem; }
.label { font-size: 0.88rem; font-weight: 500; color: #374151; }
.required { color: #ef4444; margin-left: 0.1em; }
.input { padding: 0.5rem 0.75rem; border: 1px solid #d1d5db; border-radius: 6px; font-size: 0.9rem; outline: none; width: 100%; box-sizing: border-box; }
.input:focus { border-color: #4f46e5; box-shadow: 0 0 0 2px #eef2ff; }
.input-error { border-color: #ef4444; }
.input-error:focus { box-shadow: 0 0 0 2px #fee2e2; }
.field-error { color: #dc2626; font-size: 0.82rem; margin: 0.1rem 0 0; }
.photo-section { display: flex; gap: 1.25rem; align-items: flex-start; }
.photo-preview { width: 80px; height: 80px; border-radius: 50%; background: #f3f4f6; border: 1px solid #e5e7eb; display: flex; align-items: center; justify-content: center; overflow: hidden; flex-shrink: 0; }
.photo-img { width: 100%; height: 100%; object-fit: cover; }
.photo-placeholder { font-size: 0.75rem; color: #9ca3af; text-align: center; }
.photo-controls { display: flex; flex-direction: column; gap: 0.5rem; }
.photo-hint { font-size: 0.8rem; color: #9ca3af; margin: 0; }
.visually-hidden { position: absolute; width: 1px; height: 1px; overflow: hidden; clip: rect(0 0 0 0); white-space: nowrap; }
.form-actions { display: flex; justify-content: flex-end; gap: 0.75rem; padding-top: 0.5rem; border-top: 1px solid #f3f4f6; }
.btn { display: inline-flex; align-items: center; gap: 0.375rem; padding: 0.5rem 1.125rem; border-radius: 6px; font-size: 0.9rem; font-weight: 500; cursor: pointer; border: none; text-decoration: none; transition: background 0.15s, opacity 0.15s; }
.btn:disabled { opacity: 0.5; cursor: not-allowed; }
.btn-primary { background: #4f46e5; color: #fff; }
.btn-primary:hover:not(:disabled) { background: #4338ca; }
.btn-secondary { background: #f3f4f6; color: #374151; border: 1px solid #d1d5db; }
.btn-secondary:hover { background: #e5e7eb; }
.btn-ghost { background: none; color: #6b7280; border: none; }
.btn-ghost:hover { color: #dc2626; background: #fee2e2; border-radius: 4px; }
.alert { padding: 0.875rem 1rem; border-radius: 6px; margin-bottom: 0.5rem; font-size: 0.9rem; }
.alert-error { background: #fee2e2; color: #991b1b; border: 1px solid #fca5a5; }
.skeleton-form { display: flex; flex-direction: column; gap: 1rem; padding: 1.75rem; border: 1px solid #e5e7eb; border-radius: 10px; }
.skeleton { background: linear-gradient(90deg, #f3f4f6 25%, #e5e7eb 50%, #f3f4f6 75%); background-size: 200%; animation: shimmer 1.4s infinite; }
.skeleton-line { height: 36px; border-radius: 4px; }
@keyframes shimmer { 0% { background-position: 200% 0; } 100% { background-position: -200% 0; } }
/* Dialog */
.dialog-backdrop { position: fixed; inset: 0; background: rgba(0,0,0,.45); display: flex; align-items: center; justify-content: center; z-index: 100; }
.dialog { background: #fff; border-radius: 10px; padding: 1.75rem; max-width: 440px; width: 90%; box-shadow: 0 20px 60px rgba(0,0,0,.18); }
.dialog-title { margin: 0 0 0.75rem; font-size: 1.1rem; font-weight: 700; }
.dialog-body { color: #374151; font-size: 0.92rem; line-height: 1.55; margin: 0 0 1.25rem; }
.dialog-actions { display: flex; justify-content: flex-end; gap: 0.75rem; }
@media (max-width: 580px) {
  .field-row { grid-template-columns: 1fr; }
  .page { padding: 1rem; }
}
</style>
