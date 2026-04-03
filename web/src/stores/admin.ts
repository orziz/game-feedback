import { computed, ref } from 'vue'
import { defineStore } from 'pinia'
import { i18n } from '@/i18n'
import { api, setApiTokenGetter } from '@/api/client'
import { getApiError, getErrorMessage } from '@/utils/errors'
import { message } from '@/ui/discrete'

const TOKEN_STORAGE_KEY = 'feedback-admin-token'

function createDefaultUpdateForm(): AdminUpdateForm {
  return { status: 0, severity: 1, adminNote: '' }
}

function readStoredToken(): string {
  return window.sessionStorage.getItem(TOKEN_STORAGE_KEY) || ''
}

function writeStoredToken(token: string): void {
  window.sessionStorage.setItem(TOKEN_STORAGE_KEY, token)
}

function clearStoredToken(): void {
  window.sessionStorage.removeItem(TOKEN_STORAGE_KEY)
}

export const useAdminStore = defineStore('admin', () => {
  const token = ref('')
  const loading = ref(false)
  const updating = ref(false)
  const currentUser = ref<AdminUser | null>(null)

  const statusFilter = ref<TicketStatus | null>(null)
  const typeFilter = ref<FeedbackType | null>(null)
  const severityFilter = ref<Severity | null>(null)
  const assignedFilter = ref<number | null>(null)
  const keyword = ref('')
  const page = ref(1)
  const pageSize = ref(20)
  const total = ref(0)

  const tickets = ref<TicketRecord[]>([])
  const selectedTicketNo = ref('')
  const selectedTicket = ref<TicketRecord | null>(null)
  const ticketOperations = ref<TicketOperation[]>([])
  const updateForm = ref<AdminUpdateForm>(createDefaultUpdateForm())

  const users = ref<AdminUser[]>([])
  const assignees = ref<AdminAssigneeUser[]>([])
  const usersLoading = ref(false)

  function setUsersLoading(value: boolean): void {
    usersLoading.value = value
  }

  function setUsers(value: AdminUser[]): void {
    users.value = value
  }

  function setAssignees(value: AdminAssigneeUser[]): void {
    assignees.value = value
  }

  function setTicketsLoading(value: boolean): void {
    loading.value = value
  }

  function setUpdating(value: boolean): void {
    updating.value = value
  }

  function setPage(value: number): void {
    page.value = value
  }

  function setPageSize(value: number): void {
    pageSize.value = value
  }

  function applyTicketListResponse(payload: { tickets: TicketRecord[]; total: number; page: number; pageSize: number }): void {
    const maxPage = Math.max(1, Math.ceil(payload.total / payload.pageSize))
    tickets.value = payload.tickets
    total.value = payload.total
    page.value = payload.page > maxPage ? maxPage : payload.page
  }

  function setSelectedTicket(ticketNo: string, ticket: TicketRecord | null): void {
    selectedTicketNo.value = ticketNo
    selectedTicket.value = ticket
  }

  function setTicketOperations(value: TicketOperation[]): void {
    ticketOperations.value = value
  }

  function populateUpdateFormFromTicket(ticket: TicketRecord): void {
    updateForm.value = {
      status: ticket.status,
      severity: ticket.type === 0 ? (ticket.severity ?? 1) : null,
      adminNote: ticket.admin_note || '',
      assignedTo: ticket.assigned_to || null,
    }
  }

  const isAuthenticated = computed(() => Boolean(token.value))
  const isSuperAdmin = computed(() => currentUser.value?.role === 'super_admin')

  setApiTokenGetter(() => token.value)

  async function restoreSession(): Promise<void> {
    const stored = readStoredToken()
    if (!stored) return

    token.value = stored
    try {
      const data = await api.admin.Auth.get.currentUser()
      currentUser.value = data.user
    } catch {
      token.value = ''
      currentUser.value = null
      clearStoredToken()
    }
  }

  async function login(username: string, password: string): Promise<void> {
    const { t } = i18n.global
    loading.value = true
    try {
      const data = await api.admin.Auth.post.login({ username, password })
      token.value = data.token
      currentUser.value = data.user
      writeStoredToken(data.token)
      message.success(t('messages.adminLoginSuccess'))
    } catch (error) {
      message.error(getErrorMessage(error, t('messages.adminLoginFailed')))
      throw error
    } finally {
      loading.value = false
    }
  }

  function logout(showMessage = true): void {
    const { t } = i18n.global
    token.value = ''
    currentUser.value = null
    tickets.value = []
    total.value = 0
    page.value = 1
    selectedTicketNo.value = ''
    selectedTicket.value = null
    statusFilter.value = null
    typeFilter.value = null
    severityFilter.value = null
    assignedFilter.value = null
    keyword.value = ''
    updateForm.value = createDefaultUpdateForm()
    users.value = []
    assignees.value = []
    clearStoredToken()
    if (showMessage) message.success(t('messages.adminLogoutSuccess'))
  }

  return {
    token, loading, updating, currentUser,
    statusFilter, typeFilter, severityFilter, assignedFilter, keyword,
    page, pageSize, total, tickets,
    selectedTicketNo, selectedTicket, ticketOperations, updateForm,
    isAuthenticated, isSuperAdmin,
    users, usersLoading,
    assignees,
    setUsersLoading, setUsers, setAssignees,
    setTicketsLoading, setUpdating,
    setPage, setPageSize,
    applyTicketListResponse,
    setSelectedTicket, setTicketOperations, populateUpdateFormFromTicket,
    restoreSession, login, logout,
  }
})
