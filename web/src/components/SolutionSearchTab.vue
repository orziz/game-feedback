<script setup lang="ts">
import { ref } from 'vue'
import { useI18n } from 'vue-i18n'
import { ElMessage } from 'element-plus'
import { searchTickets } from '../services/feedbackService'
import { getStatusTagType, getFeedbackTypeTagType } from '../i18n'
import { getErrorMessage } from '../utils/errors'
import { useAppStore } from '../stores/app'

const { t } = useI18n()
const appStore = useAppStore()
const keyword   = ref('')
const searching = ref(false)
const results   = ref<TicketRecord[]>([])
const total     = ref(0)

async function handleSearch(): Promise<void> {
  if (!keyword.value.trim()) {
    ElMessage.warning(t('messages.keywordRequired'))
    return
  }
  searching.value = true
  results.value   = []
  total.value     = 0
  try {
    const data  = await searchTickets(keyword.value)
    results.value = data.tickets
    total.value   = data.pagination?.total ?? results.value.length
  } catch (error) {
    ElMessage.error(getErrorMessage(error, t('messages.contentSearchFailed')))
  } finally {
    searching.value = false
  }
}
</script>

<template>
  <div class="query-line">
    <el-input
      v-model="keyword"
      :placeholder="t('solutionSearch.placeholder')"
      @keyup.enter="handleSearch"
    />
    <el-button type="success" :loading="searching" @click="handleSearch">
      {{ t('solutionSearch.button') }}
    </el-button>
  </div>

  <p v-if="total > 0" class="result-count">
    {{ t('solutionSearch.resultCount', { count: total }) }}
  </p>

  <el-empty
    v-if="!searching && keyword && results.length === 0"
    :description="t('solutionSearch.empty')"
  />

  <el-card v-for="item in results" :key="item.ticket_no" class="ticket-card solution-card">
    <template #header>
      <div class="ticket-head solution-card-head">
        <span class="solution-title">{{ item.title }}</span>
        <el-tag :type="getStatusTagType(item.status)" effect="dark" size="small">
          {{ appStore.getStatusLabel(item.status) }}
        </el-tag>
      </div>
    </template>
    <p><strong>{{ t('common.ticketNo') }}:</strong> {{ item.ticket_no }}</p>
    <p>
      <strong>{{ t('common.type') }}:</strong>
      <el-tag :type="getFeedbackTypeTagType(item.type)" effect="light" size="small">
        {{ appStore.getTypeLabel(item.type) }}
      </el-tag>
    </p>
    <p><strong>{{ t('solutionSearch.content') }}:</strong> <span class="multiline-text">{{ item.details }}</span></p>
    <p><strong>{{ t('solutionSearch.handling') }}:</strong> <span class="multiline-text">{{ item.admin_note || t('common.none') }}</span></p>
    <p><strong>{{ t('common.updatedAt') }}:</strong> {{ item.updated_at }}</p>
  </el-card>
</template>

<style scoped>
.multiline-text {
  white-space: pre-wrap;
  word-break: break-word;
}
</style>
