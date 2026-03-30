import { computed, ref } from 'vue'
import { defineStore } from 'pinia'
import { ElMessage } from 'element-plus'
import { i18n } from '../i18n'
import { api, setApiTokenGetter } from '../api/client'
import { getApiError, getErrorMessage } from '../utils/errors'

const TOKEN_STORAGE_KEY = 'feedback-admin-token'
const bugType: FeedbackType = 0

function createDefaultUpdateForm(): AdminUpdateForm {
  return { status: 0, severity: 1, adminNote: '' }
}

function readStoredToken(): string {
  const sessionToken = window.sessionStorage.getItem(TOKEN_STORAGE_KEY)
  if (sessionToken) {
    return sessionToken
  }

  const legacyToken = window.localStorage.getItem(TOKEN_STORAGE_KEY)
  if (legacyToken) {
    window.localStorage.removeItem(TOKEN_STORAGE_KEY)
  }

  return ''
}

function writeStoredToken(token: string): void {
  window.sessionStorage.setItem(TOKEN_STORAGE_KEY, token)
  window.localStorage.removeItem(TOKEN_STORAGE_KEY)
}

function clearStoredToken(): void {
  window.sessionStorage.removeItem(TOKEN_STORAGE_KEY)
  window.localStorage.removeItem(TOKEN_STORAGE_KEY)
}

export const useAdminStore = defineStore('admin', () => {
  const token = ref('')
  const loading = ref(false)
  const updating = ref(false)
  const currentUser = ref<AdminUser | null>(null)

  const statusFilter = ref<TicketStatus | null>(null)
  const typeFilter = ref<FeedbackType | null>(null)
  const keyword = ref('')
  const page = ref(1)
  const pageSize = ref(10)
  const total = ref(0)

  const tickets = ref<TicketRecord[]>([])
  const selectedTicketNo = ref('')
  const selectedTicket = ref<TicketRecord | null>(null)
  const updateForm = ref<AdminUpdateForm>(createDefaultUpdateForm())

  const users = ref<AdminUser[]>([])
  const usersLoading = ref(false)

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
      await loadTickets(1)
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
      ElMessage.success(t('messages.adminLoginSuccess'))
      await loadTickets(1)
    } catch (error) {
      ElMessage.error(getErrorMessage(error, t('messages.adminLoginFailed')))
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
    keyword.value = ''
    updateForm.value = createDefaultUpdateForm()
    users.value = []
    clearStoredToken()
    if (showMessage) ElMessage.success(t('messages.adminLogoutSuccess'))
  }

  async function loadTickets(nextPage = page.value): Promise<void> {
    const { t } = i18n.global
    if (!token.value) return
    page.value = nextPage
    loading.value = true
    try {
      const data = await api.admin.Ticket.get.list({
        page: page.value,
        pageSize: pageSize.value,
        status: statusFilter.value !== null ? statusFilter.value : undefined,
        type: typeFilter.value !== null ? typeFilter.value : undefined,
        keyword: keyword.value.trim() || undefined,
      })
      tickets.value = data.tickets
      total.value = data.pagination?.total || 0
      const maxPage = Math.max(1, Math.ceil(total.value / pageSize.value))
      if (page.value > maxPage) page.value = maxPage
    } catch (error) {
      const apiError = getApiError(error)
      if (apiError?.code === 'UNAUTHORIZED') logout(false)
      ElMessage.error(getErrorMessage(error, t('messages.adminLoadFailed')))
    } finally {
      loading.value = false
    }
  }

  async function loadTicketDetail(ticketNo: string): Promise<void> {
    const { t } = i18n.global
    if (!token.value || !ticketNo) return
    loading.value = true
    try {
      const data = await api.admin.Ticket.get.detail({ ticketNo })
      selectedTicket.value = data.ticket
      selectedTicketNo.value = ticketNo
      updateForm.value.status = data.ticket.status
      updateForm.value.severity = data.ticket.type === bugType ? (data.ticket.severity ?? 1) : null
      updateForm.value.adminNote = data.ticket.admin_note || ''
    } catch (error) {
      ElMessage.error(getErrorMessage(error, t('messages.adminDetailFailed')))
    } finally {
      loading.value = false
    }
  }

  async function saveTicket(): Promise<void> {
    const { t } = i18n.global
    if (!selectedTicketNo.value) {
      ElMessage.warning(t('messages.adminSelectTicket'))
      return
    }
    updating.value = true
    try {
      await api.admin.Ticket.post.update({
        ticketNo: selectedTicketNo.value,
        status: updateForm.value.status,
        severity: selectedTicket.value?.type === bugType ? updateForm.value.severity : null,
        adminNote: updateForm.value.adminNote,
      })
      ElMessage.success(t('messages.adminUpdateSuccess'))
      await Promise.all([loadTickets(), loadTicketDetail(selectedTicketNo.value)])
    } catch (error) {
      ElMessage.error(getErrorMessage(error, t('messages.adminUpdateFailed')))
    } finally {
      updating.value = false
    }
  }

  async function loadUsers(): Promise<void> {
    const { t } = i18n.global
    if (!token.value) return
    usersLoading.value = true
    try {
      const data = await api.admin.User.get.list()
      users.value = data.users
    } catch (error) {
      ElMessage.error(getErrorMessage(error, t('messages.userLoadFailed')))
    } finally {
      usersLoading.value = false
    }
  }

  async function addUser(username: string, password: string, role: string): Promise<void> {
    const { t } = i18n.global
    try {
      await api.admin.User.post.create({ username, password, role })
      ElMessage.success(t('messages.userCreateSuccess'))
      await loadUsers()
    } catch (error) {
      ElMessage.error(getErrorMessage(error, t('messages.userCreateFailed')))
      throw error
    }
  }

  async function removeUser(id: number): Promise<void> {
    const { t } = i18n.global
    try {
      await api.admin.User.post.delete({ id })
      ElMessage.success(t('messages.userDeleteSuccess'))
      await loadUsers()
    } catch (error) {
      ElMessage.error(getErrorMessage(error, t('messages.userDeleteFailed')))
      throw error
    }
  }

  async function resetPassword(id: number, newPassword: string): Promise<void> {
    const { t } = i18n.global
    try {
      await api.admin.User.post.resetPassword({ id, password: newPassword })
      ElMessage.success(t('messages.userResetPasswordSuccess'))
    } catch (error) {
      ElMessage.error(getErrorMessage(error, t('messages.userResetPasswordFailed')))
      throw error
    }
  }

  async function changePage(nextPage: number): Promise<void> { await loadTickets(nextPage) }
  async function changePageSize(n: number): Promise<void> { pageSize.value = n; await loadTickets(1) }
  async function refresh(): Promise<void> { await loadTickets(1) }

  return {
    token, loading, updating, currentUser,
    statusFilter, typeFilter, keyword,
    page, pageSize, total, tickets,
    selectedTicket, updateForm,
    isAuthenticated, isSuperAdmin,
    users, usersLoading,
    restoreSession, login, logout,
    loadTickets, loadTicketDetail,
    saveTicket, changePage, changePageSize, refresh,
    loadUsers, addUser, removeUser, resetPassword,
  }
})
