// ── Member domain types ───────────────────────────────────────────────────────
// Mirrors MemberResource.php — keep in sync if the API shape changes.

export type MemberStatus = 'active' | 'inactive'
export type Gender = 'male' | 'female' | 'other'

export interface Member {
  id: number
  ulid: string
  gym_id: number
  full_name: string
  date_of_birth: string       // ISO date string "YYYY-MM-DD"
  gender: Gender
  phone: string
  emergency_contact_name: string
  emergency_contact_phone: string
  photo_url: string | null
  registration_date: string   // ISO date string "YYYY-MM-DD"
  status: MemberStatus
  created_at: string
  updated_at: string
}

// ── Pagination envelope returned by Laravel's paginate() ─────────────────────
export interface PaginatedResponse<T> {
  data: T[]
  links: {
    first: string | null
    last: string | null
    prev: string | null
    next: string | null
  }
  meta: {
    current_page: number
    from: number | null
    last_page: number
    per_page: number
    to: number | null
    total: number
    path: string
  }
}

// ── API error shape from the Laravel error envelope ───────────────────────────
export interface ApiFieldError {
  field: string
  issue: string
}

export interface ApiError {
  code: string
  message: string
  fields?: ApiFieldError[]
}

// ── Request payloads ──────────────────────────────────────────────────────────
export interface StoreMemberPayload {
  full_name: string
  date_of_birth: string
  gender: Gender
  phone: string
  emergency_contact_name: string
  emergency_contact_phone: string
  registration_date?: string
  confirm_duplicate?: boolean
}

export interface UpdateMemberPayload {
  full_name?: string
  date_of_birth?: string
  gender?: Gender
  phone?: string
  emergency_contact_name?: string
  emergency_contact_phone?: string
  confirm_duplicate?: boolean
}
