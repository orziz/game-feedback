import { computed, ref } from 'vue'
import { defineStore } from 'pinia'
import { ElMessage } from 'element-plus'
import { i18n } from '../i18n'
import { adminLogin, fetchAdminTicketDetail, fetchAdminTickets, updateAdminTicket } from '../services/feedbackService'
import { getApiError, getErrorMessage } from '../utils/errors'

const bugType: FeedbackType = 0

function createDefaultUpdateForm(): AdminUpdateForm {
  return { status: 0, severity: 1, adminNote: '' }
}

export const useAdminStore = defineStore('admin', () => {
  const token    = ref('')
  const loading  = ref(false)
  const updating = ref(false)

  const statusFilter = ref<TicketStatus | null>(null)
  const typeFilter   = ref<FeedbackType | null>(null)
  const keyword      = ref('')
  const page         = ref(1)
  const pageSize     = ref(10)
  const total        = ref(0)

  const tickets          = ref<TicketRecord[]>([])
  const selectedTicketNo = ref('')
  const selectedTicket   = ref<TicketRecord | null>(null)
  const updateForm       = ref<AdminUpdateForm>(createDefaultUpdateForm())

  const isAuthenticated = computed(() => Boolean(token.value))

  async function login(password: string): Promise<void> {
    const { t } = i18n.global
    loading.value = true
    try {
      const data = await adminLogin(password)
      token.value = data.token
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
    tickets.value = []
    total.value = 0
    page.value = 1
    selectedTicketNo.value = ''
    selectedTicket.value = null
    statusFilter.value = null
    typeFilter.value = null
    keyword.value = ''
    updateForm.value = createDefaultUpdateForm()
    if (showMessage) ElMessage.success(t('messages.adminLogoutSuccess'))
  }

  async function loadTickets(nextPage = page.value): Promise<void> {
    const { t } = i18n.global
    if (!token.value) return
    page.value = nextPage
    loading.value = true
    try {
      const data = await fetchAdminTickets(token.value, {
        page:     page.value,
        pageSize: pageSize.value,
        status:   statusFilter.value !== null ? statusFilter.value : undefined,
        type:     typeFilter.value   !== null ? typeFilter.value   : undefined,
        keyword:  keyword.value.trim() || undefined,
      })
      tickets.value = data.tickets
      total.value   = data.pagination?.total || 0
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
      const data = await fetchAdminTicketDetail(token.value, ticketNo)
      selectedTicket.value   = data.ticket
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
      await updateAdminTicket(token.value, {
        ticketNo:  selectedTicketNo.value,
        status:    updateForm.value.status,
        severity:  selectedTicket.value?.type === bugType ? updateForm.value.severity : null,
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

  async function changePage(nextPage: number): Promise<void> { await loadTickets(nextPage) }
  async function changePageSize(n: number): Promise<void> { pageSize.value = n; await loadTickets(1) }
  async function refresh(): Promise<void> { await loadTickets(1) }

  return {
    token, loading, updating,
    statusFilter, typeFilter, keyword,
    page, pageSize, total, tickets,
    selectedTicket, updateForm,
    isAuthenticated,
    login, logout, loadTickets, loadTicketDetail,
    saveTicket, changePage, changePageSize, refresh,
  }
})
