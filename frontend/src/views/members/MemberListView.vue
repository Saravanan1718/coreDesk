<script setup lang="ts">
import { ref, onMounted, watch } from 'vue'
import { useRouter } from 'vue-router'
import { useMembersStore } from '@/stores/members'

const router = useRouter()
const store = useMembersStore()

const searchQuery = ref('')
const currentPage = ref(1)
const perPage = ref(50)

// Debounce timer handle
let searchTimer: ReturnType<typeof setTimeout>

// ── Lifecycle ─────────────────────────────────────────────────────────────────
onMounted(() => {
  loadMembers()
})

// Re-fetch when search input changes (300 ms debounce)
watch(searchQuery, () => {
  clearTimeout(searchTimer)
  searchTimer = setTimeout(() => {
    currentPage.value = 1
    loadMembers()
  }, 300)
})

// ── Helpers ───────────────────────────────────────────────────────────────────
function loadMembers(): void {
  store.fetchMembers({
    search: searchQuery.value || undefined,
    page: currentPage.value,
    per_page: perPage.value,
  })
}

function goToPage(page: number): void {
  currentPage.value = page
  loadMembers()
}

function viewMember(id: number): void {
  router.push({ name: 'member-detail', params: { id } })
}

function editMember(id: number): void {
  router.push({ name: 'member-edit', params: { id } })
}

async function deactivateMember(id: number, name: string): Promise<void> {
  if (!confirm(`Deactivate ${name}? They will no longer appear in search results or membership assignments.`)) return
  await store.deactivateMember(id)
}

function formatDate(dateStr: string): string {
  return new Date(dateStr).toLocaleDateString('en-GB', {
    day: '2-digit',
    month: 'short',
    year: 'numeric',
  })
}
</script>

<template>
  <div class="page">
    <!-- ── Header ──────────────────────────────────────────────────────────── -->
    <div class="page-header">
      <div>
        <h1 class="page-title">Members</h1>
        <p class="page-subtitle">
          {{ store.meta?.total ?? '—' }} active member{{ store.meta?.total === 1 ? '' : 's' }}
        </p>
      </div>
      <div class="header-actions">
        <button class="btn btn-secondary" @click="store.exportCsv()">Export CSV</button>
        <router-link :to="{ name: 'member-create' }" class="btn btn-primary">
          + Add Member
        </router-link>
      </div>
    </div>

    <!-- ── Search ─────────────────────────────────────────────────────────── -->
    <div class="search-bar">
      <input
        v-model="searchQuery"
        type="search"
        placeholder="Search by name or phone…"
        class="input"
        aria-label="Search members"
      />
    </div>

    <!-- ── Error banner ───────────────────────────────────────────────────── -->
    <div v-if="store.error" class="alert alert-error" role="alert">
      {{ store.error }}
    </div>

    <!-- ── Table ──────────────────────────────────────────────────────────── -->
    <div class="table-wrapper">
      <table class="table" aria-label="Members list">
        <thead>
          <tr>
            <th scope="col">Name</th>
            <th scope="col">Phone</th>
            <th scope="col">Gender</th>
            <th scope="col">Registered</th>
            <th scope="col">Status</th>
            <th scope="col"><span class="sr-only">Actions</span></th>
          </tr>
        </thead>
        <tbody>
          <!-- Loading skeleton -->
          <tr v-if="store.loading" v-for="n in 5" :key="n" aria-hidden="true">
            <td colspan="6">
              <div class="skeleton" style="height:20px; border-radius:4px;" />
            </td>
          </tr>

          <!-- Empty state -->
          <tr v-else-if="store.members.length === 0">
            <td colspan="6" class="empty-state">
              {{ searchQuery ? 'No members match your search.' : 'No members yet. Add one to get started.' }}
            </td>
          </tr>

          <!-- Data rows -->
          <tr
            v-else
            v-for="member in store.members"
            :key="member.id"
            class="table-row"
            @click="viewMember(member.id)"
            :aria-label="`View ${member.full_name}`"
          >
            <td class="td-name">
              <div class="member-avatar" :aria-hidden="true">
                <img
                  v-if="member.photo_url"
                  :src="member.photo_url"
                  :alt="member.full_name"
                  class="avatar-img"
                />
                <span v-else class="avatar-initials">
                  {{ member.full_name.charAt(0).toUpperCase() }}
                </span>
              </div>
              {{ member.full_name }}
            </td>
            <td>{{ member.phone }}</td>
            <td class="td-capitalize">{{ member.gender }}</td>
            <td>{{ formatDate(member.registration_date) }}</td>
            <td>
              <span class="badge" :class="member.status === 'active' ? 'badge-green' : 'badge-gray'">
                {{ member.status }}
              </span>
            </td>
            <td class="td-actions" @click.stop>
              <button
                class="btn-icon"
                :aria-label="`Edit ${member.full_name}`"
                @click="editMember(member.id)"
              >
                ✏️
              </button>
              <button
                v-if="member.status === 'active'"
                class="btn-icon btn-icon-danger"
                :aria-label="`Deactivate ${member.full_name}`"
                :disabled="store.saving"
                @click="deactivateMember(member.id, member.full_name)"
              >
                🚫
              </button>
            </td>
          </tr>
        </tbody>
      </table>
    </div>

    <!-- ── Pagination ─────────────────────────────────────────────────────── -->
    <nav v-if="store.meta && store.meta.last_page > 1" class="pagination" aria-label="Members pagination">
      <button
        class="btn btn-secondary btn-sm"
        :disabled="currentPage === 1"
        @click="goToPage(currentPage - 1)"
        aria-label="Previous page"
      >
        ← Prev
      </button>

      <span class="pagination-info">
        Page {{ store.meta.current_page }} of {{ store.meta.last_page }}
        &nbsp;·&nbsp; {{ store.meta.total }} total
      </span>

      <button
        class="btn btn-secondary btn-sm"
        :disabled="currentPage >= store.meta.last_page"
        @click="goToPage(currentPage + 1)"
        aria-label="Next page"
      >
        Next →
      </button>
    </nav>
  </div>
</template>

<style scoped>
.page { padding: 1.5rem 2rem; max-width: 1200px; margin: 0 auto; }
.page-header { display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 1.5rem; }
.page-title { font-size: 1.5rem; font-weight: 700; margin: 0 0 0.25rem; }
.page-subtitle { color: #6b7280; margin: 0; font-size: 0.9rem; }
.header-actions { display: flex; gap: 0.75rem; align-items: center; }
.search-bar { margin-bottom: 1.25rem; }
.input { width: 100%; max-width: 420px; padding: 0.5rem 0.875rem; border: 1px solid #d1d5db; border-radius: 6px; font-size: 0.95rem; outline: none; }
.input:focus { border-color: #4f46e5; box-shadow: 0 0 0 2px #eef2ff; }
.table-wrapper { overflow-x: auto; border: 1px solid #e5e7eb; border-radius: 8px; }
.table { width: 100%; border-collapse: collapse; font-size: 0.9rem; }
.table thead th { background: #f9fafb; padding: 0.75rem 1rem; text-align: left; font-weight: 600; color: #374151; border-bottom: 1px solid #e5e7eb; white-space: nowrap; }
.table-row { cursor: pointer; transition: background 0.1s; }
.table-row:hover { background: #f9fafb; }
.table tbody td { padding: 0.75rem 1rem; border-bottom: 1px solid #f3f4f6; vertical-align: middle; }
.table tbody tr:last-child td { border-bottom: none; }
.td-name { display: flex; align-items: center; gap: 0.625rem; white-space: nowrap; }
.td-capitalize { text-transform: capitalize; }
.td-actions { white-space: nowrap; }
.member-avatar { width: 32px; height: 32px; border-radius: 50%; background: #e0e7ff; display: flex; align-items: center; justify-content: center; overflow: hidden; flex-shrink: 0; }
.avatar-img { width: 100%; height: 100%; object-fit: cover; }
.avatar-initials { font-size: 0.85rem; font-weight: 600; color: #4f46e5; }
.badge { display: inline-flex; padding: 0.2em 0.65em; border-radius: 9999px; font-size: 0.78rem; font-weight: 600; text-transform: capitalize; }
.badge-green { background: #d1fae5; color: #065f46; }
.badge-gray { background: #f3f4f6; color: #6b7280; }
.btn { display: inline-flex; align-items: center; gap: 0.375rem; padding: 0.5rem 1rem; border-radius: 6px; font-size: 0.9rem; font-weight: 500; cursor: pointer; border: none; text-decoration: none; transition: background 0.15s, opacity 0.15s; }
.btn:disabled { opacity: 0.5; cursor: not-allowed; }
.btn-sm { padding: 0.375rem 0.75rem; font-size: 0.82rem; }
.btn-primary { background: #4f46e5; color: #fff; }
.btn-primary:hover { background: #4338ca; }
.btn-secondary { background: #f3f4f6; color: #374151; border: 1px solid #d1d5db; }
.btn-secondary:hover { background: #e5e7eb; }
.btn-icon { background: none; border: none; cursor: pointer; padding: 0.25rem 0.375rem; border-radius: 4px; font-size: 1rem; line-height: 1; }
.btn-icon:hover { background: #f3f4f6; }
.btn-icon-danger:hover { background: #fee2e2; }
.btn-icon:disabled { opacity: 0.4; cursor: not-allowed; }
.skeleton { background: linear-gradient(90deg, #f3f4f6 25%, #e5e7eb 50%, #f3f4f6 75%); background-size: 200% 100%; animation: shimmer 1.4s infinite; }
@keyframes shimmer { 0% { background-position: 200% 0; } 100% { background-position: -200% 0; } }
.empty-state { text-align: center; padding: 3rem 1rem; color: #9ca3af; }
.pagination { display: flex; justify-content: center; align-items: center; gap: 1rem; margin-top: 1.25rem; }
.pagination-info { color: #6b7280; font-size: 0.88rem; }
.alert { padding: 0.875rem 1rem; border-radius: 6px; margin-bottom: 1rem; font-size: 0.9rem; }
.alert-error { background: #fee2e2; color: #991b1b; border: 1px solid #fca5a5; }
.sr-only { position: absolute; width: 1px; height: 1px; padding: 0; margin: -1px; overflow: hidden; clip: rect(0,0,0,0); border: 0; }
</style>
