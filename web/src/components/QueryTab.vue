<script setup lang="ts">
import { ref } from 'vue'
import { useI18n } from 'vue-i18n'
import { ElMessage } from 'element-plus'
import { fetchTicket } from '../services/feedbackService'
import { getStatusTagType, getFeedbackTypeTagType } from '../i18n'
import { getErrorMessage } from '../utils/errors'
import { useAppStore } from '../stores/app'

const { t } = useI18n()
const appStore = useAppStore()
const ticketNo    = ref('')
const querying    = ref(false)
const queryResult = ref<TicketRecord | null>(null)

async function handleQuery(): Promise<void> {
  if (!ticketNo.value.trim()) {
    ElMessage.warning(t('messages.ticketRequired'))
    return
  }
  querying.value = true
  queryResult.value = null
  try {
    const data = await fetchTicket(ticketNo.value)
    queryResult.value = data.ticket
  } catch (error) {
    ElMessage.error(getErrorMessage(error, t('messages.queryFailed')))
  } finally {
    querying.value = false
  }
}

function severityClass(severity: Severity): string {
  if (severity === 0) return 'severity-badge--low'
  if (severity === 1) return 'severity-badge--medium'
  if (severity === 2) return 'severity-badge--high'
  return 'severity-badge--critical'
}
</script>

<template>
  <div class="query-line">
    <el-input
      v-model="ticketNo"
      :placeholder="t('query.placeholder')"
      @keyup.enter="handleQuery"
    />
    <el-button type="primary" :loading="querying" @click="handleQuery">
      {{ t('query.button') }}
    </el-button>
  </div>

  <el-empty v-if="!queryResult" :description="t('query.empty')" class="query-empty" />

  <el-card v-if="queryResult" class="ticket-card query-result-card">
    <template #header>
      <div class="ticket-head query-ticket-head">
        <span class="ticket-no">{{ t('common.ticketNo') }}: {{ queryResult.ticket_no }}</span>
        <el-tag :type="getStatusTagType(queryResult.status)" effect="dark" round>
          {{ appStore.getStatusLabel(queryResult.status) }}
        </el-tag>
      </div>
    </template>
    <p>
      <strong>{{ t('common.type') }}:</strong>
      <el-tag :type="getFeedbackTypeTagType(queryResult.type)" effect="light" size="small">
        {{ appStore.getTypeLabel(queryResult.type) }}
      </el-tag>
    </p>
    <p v-if="queryResult.severity !== null">
      <strong>{{ t('common.severity') }}:</strong>
      <span class="severity-badge" :class="severityClass(queryResult.severity)">
        {{ appStore.getSeverityLabel(queryResult.severity) }}
      </span>
    </p>
    <p><strong>{{ t('common.title') }}:</strong> {{ queryResult.title }}</p>
    <p><strong>{{ t('common.details') }}:</strong> <span class="multiline-text">{{ queryResult.details }}</span></p>
    <p><strong>{{ t('common.contact') }}:</strong> {{ queryResult.contact }}</p>
    <p><strong>{{ t('query.adminNote') }}:</strong> <span class="multiline-text">{{ queryResult.admin_note || t('common.none') }}</span></p>
    <p><strong>{{ t('common.updatedAt') }}:</strong> {{ queryResult.updated_at }}</p>
  </el-card>
</template>

<style scoped>
.multiline-text {
  white-space: pre-wrap;
  word-break: break-word;
}

.severity-badge {
  display: inline-flex;
  align-items: center;
  margin-left: 6px;
  padding: 1px 8px;
  border-radius: 999px;
  border: 1px solid transparent;
  font-size: 12px;
  font-weight: 700;
  line-height: 1.7;
}

.severity-badge--low {
  color: #166534;
  border-color: #86efac;
  background: #f0fdf4;
}

.severity-badge--medium {
  color: #a16207;
  border-color: #fcd34d;
  background: #fffbeb;
}

.severity-badge--high {
  color: #c2410c;
  border-color: #fdba74;
  background: #fff7ed;
}

.severity-badge--critical {
  color: #b91c1c;
  border-color: #fca5a5;
  background: #fef2f2;
  box-shadow: inset 0 0 0 1px rgba(185, 28, 28, 0.1);
}
</style>
