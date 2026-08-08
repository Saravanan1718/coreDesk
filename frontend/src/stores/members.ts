import { ref } from 'vue'
import { defineStore } from 'pinia'
import api from '@/lib/api'
import type {
  Member,
  PaginatedResponse,
  StoreMemberPayload,
  UpdateMemberPayload,
} from '@/types/member'
import type { AxiosError } from 'axios'

// In owner mode, the gym is always gym 1.
// When auth is added, this will come from the auth store.
const GYM_ID = 1

function membersBase(): string {
  return `/gyms/${GYM_ID}/members`
}

export const useMembersStore = defineStore('members', () => {
  // ── State ─────────────────────────────────────────────────────────────────
  const members = ref<Member[]>([])
  const currentMember = ref<Member | null>(null)

  const loading = ref(false)
  const saving = ref(false)
  const error = ref<string | null>(null)

  // Pagination meta from the last list call
  const meta = ref<PaginatedResponse<Member>['meta'] | null>(null)
  const links = ref<PaginatedResponse<Member>['links'] | null>(null)

  // Duplicate phone state — used to drive the confirmation dialog in the form
  const duplicatePhoneWarning = ref(false)

  // Field-level validation errors from the API (422 responses)
  const fieldErrors = ref<Record<string, string>>({})

  // ── Private helpers ───────────────────────────────────────────────────────
  function clearErrors(): void {
    error.value = null
    fieldErrors.value = {}
    duplicatePhoneWarning.value = false
  }

  function handleApiError(err: unknown): void {
    const axiosErr = err as AxiosError<{
      error?: { code?: string; message?: string; fields?: { field: string; issue: string }[] }
      errors?: Record<string, string[]>
      message?: string
    }>

    const status = axiosErr.response?.status
    const body = axiosErr.response?.data

    // 422 — Laravel Form Request validation errors
    if (status === 422 && body?.errors) {
      const flat: Record<string, string> = {}
      for (const [field, messages] of Object.entries(body.errors)) {
        flat[field] = Array.isArray(messages) ? messages[0] : messages
      }
      fieldErrors.value = flat
      error.value = 'Please fix the highlighted fields.'
      return
    }

    // 409 — duplicate phone warning (handled by the store caller via duplicatePhoneWarning)
    if (status === 409 && body?.error?.code === 'DUPLICATE_PHONE') {
      duplicatePhoneWarning.value = true
      return
    }

    error.value =
      body?.error?.message ??
      body?.message ??
      (err instanceof Error ? err.message : 'An unexpected error occurred.')
  }

  // ── Actions ───────────────────────────────────────────────────────────────

  /**
   * Fetch a paginated, optionally searched list of ACTIVE members.
   */
  async function fetchMembers(params: { search?: string; page?: number; per_page?: number } = {}): Promise<void> {
    loading.value = true
    clearErrors()
    try {
      const response = await api.get<PaginatedResponse<Member>>(membersBase(), { params })
      members.value = response.data.data
      meta.value = response.data.meta
      links.value = response.data.links
    } catch (err) {
      handleApiError(err)
    } finally {
      loading.value = false
    }
  }

  /**
   * Fetch a single member by numeric ID.
   */
  async function fetchMember(id: number): Promise<void> {
    loading.value = true
    clearErrors()
    currentMember.value = null
    try {
      const response = await api.get<{ data: Member }>(`${membersBase()}/${id}`)
      currentMember.value = response.data.data
    } catch (err) {
      handleApiError(err)
    } finally {
      loading.value = false
    }
  }

  /**
   * Create a new member.
   * Returns true on success, false on validation/duplicate error.
   * The caller should check duplicatePhoneWarning to show the confirmation dialog.
   */
  async function createMember(
    payload: StoreMemberPayload,
    photo?: File,
  ): Promise<Member | null> {
    saving.value = true
    clearErrors()
    try {
      const formData = buildFormData(payload, photo)
      const response = await api.post<{ data: Member }>(membersBase(), formData, {
        headers: { 'Content-Type': 'multipart/form-data' },
      })
      const created = response.data.data
      members.value.unshift(created)
      return created
    } catch (err) {
      handleApiError(err)
      return null
    } finally {
      saving.value = false
    }
  }

  /**
   * Update an existing member (PATCH).
   * Returns the updated member or null on error.
   */
  async function updateMember(
    id: number,
    payload: UpdateMemberPayload,
    photo?: File,
  ): Promise<Member | null> {
    saving.value = true
    clearErrors()
    try {
      const formData = buildFormData(payload, photo)
      // PATCH with FormData requires the _method override for Laravel
      formData.append('_method', 'PATCH')
      const response = await api.post<{ data: Member }>(`${membersBase()}/${id}`, formData, {
        headers: { 'Content-Type': 'multipart/form-data' },
      })
      const updated = response.data.data
      // Refresh the list entry in place
      const idx = members.value.findIndex((m) => m.id === id)
      if (idx !== -1) members.value[idx] = updated
      if (currentMember.value?.id === id) currentMember.value = updated
      return updated
    } catch (err) {
      handleApiError(err)
      return null
    } finally {
      saving.value = false
    }
  }

  /**
   * Soft-delete a member (sets status = inactive, never hard-deletes).
   * Returns true on success.
   */
  async function deactivateMember(id: number): Promise<boolean> {
    saving.value = true
    clearErrors()
    try {
      await api.post(`${membersBase()}/${id}/deactivate`)
      // Remove from the active list immediately (optimistic)
      members.value = members.value.filter((m) => m.id !== id)
      if (currentMember.value?.id === id) {
        currentMember.value = { ...currentMember.value, status: 'inactive' }
      }
      return true
    } catch (err) {
      handleApiError(err)
      return false
    } finally {
      saving.value = false
    }
  }

  /**
   * Trigger a CSV download of all members (active + inactive).
   */
  function exportCsv(): void {
    // Open the export URL directly — the browser handles the file download.
    window.open(`/api${membersBase()}/export`, '_blank')
  }

  // ── Utility ───────────────────────────────────────────────────────────────
  function buildFormData(payload: Record<string, unknown>, photo?: File): FormData {
    const fd = new FormData()
    for (const [key, value] of Object.entries(payload)) {
      if (value !== undefined && value !== null) {
        fd.append(key, String(value))
      }
    }
    if (photo) fd.append('photo', photo)
    return fd
  }

  return {
    // state
    members,
    currentMember,
    loading,
    saving,
    error,
    meta,
    links,
    duplicatePhoneWarning,
    fieldErrors,
    // actions
    fetchMembers,
    fetchMember,
    createMember,
    updateMember,
    deactivateMember,
    exportCsv,
    clearErrors,
  }
})
