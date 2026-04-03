<script setup lang="ts">
import { ref, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import { useMessage } from 'naive-ui'
import { api } from '@/api/client'
import { getErrorMessage } from '@/utils/errors'
import TicketProgress from './TicketProgress.vue'
import TicketMetaTag from '@/components/shared/TicketMetaTag.vue'

const { t } = useI18n()
const message = useMessage()
const keyword   = ref('')
const searching = ref(false)
const results   = ref<TicketSearchResponse['tickets']>([])
const total     = ref(0)
const expandedTicketNos = ref<string[]>([])
const hasSearched = ref(false)

watch(keyword, () => {
  hasSearched.value = false
})

function isTicketNoKeyword(value: string): boolean {
  return /^FB\d{8}[A-F0-9]{6}$/.test(value)
}

async function handleSearch(): Promise<void> {
  if (!keyword.value.trim()) {
    message.warning(t('messages.keywordRequired'))
    return
  }
  const trimmedKeyword = keyword.value.trim()
  hasSearched.value = true

  searching.value = true
  results.value   = []
  total.value     = 0
  expandedTicketNos.value = []
  try {
    const data = await api.feedback.Ticket.get.search({ keyword: trimmedKeyword })
    results.value = data.tickets
    total.value   = data.pagination?.total ?? results.value.length
    expandedTicketNos.value = isTicketNoKeyword(trimmedKeyword)
      ? results.value.map((ticket) => ticket.ticket_no)
      : []
  } catch (error) {
    message.error(getErrorMessage(error, t('messages.contentSearchFailed')))
  } finally {
    searching.value = false
  }
}

function statusToneClass(status: TicketStatus): string {
  if (status === 0) return 'ticket-state--pending'
  if (status === 1) return 'ticket-state--in-progress'
  if (status === 2) return 'ticket-state--resolved'
  return 'ticket-state--closed'
}

function isExpanded(ticketNo: string): boolean {
  return expandedTicketNos.value.includes(ticketNo)
}

function toggleExpanded(ticketNo: string): void {
  if (isExpanded(ticketNo)) {
    expandedTicketNos.value = expandedTicketNos.value.filter((value) => value !== ticketNo)
    return
  }

  expandedTicketNos.value = [...expandedTicketNos.value, ticketNo]
}
</script>

<template>
  <div class="solution-search">
    <div class="query-line">
      <n-input
        v-model:value="keyword"
        :placeholder="t('solutionSearch.placeholder')"
        @keyup.enter="handleSearch"
      />
      <n-button type="success" :loading="searching" @click="handleSearch">
        {{ t('solutionSearch.button') }}
      </n-button>
    </div>

    <p v-if="total > 0" class="result-count">
      {{ t('solutionSearch.resultCount', { count: total }) }}
    </p>

    <div class="solution-search__results">
      <n-empty
        v-if="!searching && hasSearched && results.length === 0"
        :description="t('solutionSearch.empty')"
      />

      <div v-if="results.length > 0" class="solution-search__list">
        <article
          v-for="item in results"
          :key="item.ticket_no"
          class="solution-search__card"
          :class="[statusToneClass(item.status), { 'is-expanded': isExpanded(item.ticket_no) }]"
        >
      <button
        type="button"
        class="solution-search__card-header"
        @click="toggleExpanded(item.ticket_no)"
      >
        <div class="solution-search__card-header-accent"></div>
        <div class="solution-search__card-main">
          <div class="solution-search__collapse-main">
            <div class="solution-search__collapse-title">{{ item.title }}</div>
            <div class="solution-search__collapse-subline">
              <span class="solution-search__collapse-ticket">{{ item.ticket_no }}</span>
              <span class="solution-search__collapse-time">{{ t('common.updatedAt') }} {{ item.updated_at }}</span>
            </div>
          </div>

          <div class="solution-search__card-side">
            <TicketMetaTag kind="status" :value="item.status" effect="dark" round class="solution-search__collapse-tag" />
            <span class="solution-search__card-arrow" :class="{ 'is-expanded': isExpanded(item.ticket_no) }"></span>
          </div>
        </div>
      </button>

        <div
          v-show="isExpanded(item.ticket_no)"
          class="solution-search__card-body"
        >
          <section class="solution-search__panel">
            <div class="solution-search__panel-top">
              <TicketProgress :status="item.status" :show-summary="false" />
            </div>

            <section class="ticket-detail-card__meta">
              <div class="ticket-field">
                <span class="ticket-field__label">{{ t('common.type') }}</span>
                <div class="ticket-field__value">
                  <TicketMetaTag kind="type" :value="item.type" />
                </div>
              </div>

              <div v-if="item.severity !== null" class="ticket-field">
                <span class="ticket-field__label">{{ t('common.severity') }}</span>
                <div class="ticket-field__value">
                  <TicketMetaTag kind="severity" :value="item.severity" />
                </div>
              </div>

              <div class="ticket-field">
                <span class="ticket-field__label">{{ t('common.createdAt') }}</span>
                <div class="ticket-field__value">{{ item.created_at }}</div>
              </div>
            </section>

            <section class="ticket-section">
              <div class="ticket-section__label">{{ t('solutionSearch.content') }}</div>
              <div class="ticket-section__body multiline-text">{{ item.details }}</div>
            </section>

            <section class="ticket-section ticket-section--note">
              <div class="ticket-section__label">{{ t('solutionSearch.handling') }}</div>
              <div class="ticket-section__body multiline-text">{{ item.admin_note || t('common.none') }}</div>
            </section>
          </section>
        </div>
        </article>
      </div>
    </div>
  </div>
</template>

<style scoped>
.solution-search {
  display: grid;
  grid-template-rows: auto auto minmax(0, 1fr);
  height: 100%;
  min-height: 0;
}

.query-line {
  display: grid;
  grid-template-columns: 1fr auto;
  gap: 10px;
  margin-bottom: 14px;
}

.result-count {
  margin: 0 0 10px;
  color: var(--ink-soft);
}

.solution-search__results {
  min-height: 0;
  overflow: auto;
  scrollbar-gutter: stable;
  padding-right: 4px;
}

.solution-search__list {
  margin-top: 12px;
  display: grid;
  gap: 14px;
}

.solution-search__card {
  position: relative;
  overflow: hidden;
  border-radius: 22px;
  border: 1px solid rgba(203, 213, 225, 0.96);
  background: linear-gradient(180deg, rgba(255, 255, 255, 0.98), rgba(247, 250, 252, 0.96));
  box-shadow: 0 18px 36px rgba(15, 23, 42, 0.07);
  transition: box-shadow 0.24s ease, transform 0.24s ease, border-color 0.24s ease;
}

.solution-search__card:hover {
  transform: translateY(-1px);
  box-shadow: 0 22px 44px rgba(15, 23, 42, 0.1);
}

.solution-search__card.is-expanded {
  box-shadow: 0 24px 48px rgba(15, 23, 42, 0.12);
}

.solution-search__card-header {
  display: block;
  width: 100%;
  padding: 0;
  border: 0;
  background: transparent;
  text-align: left;
  cursor: pointer;
}

.solution-search__card-main {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 14px;
  width: 100%;
  min-width: 0;
  padding: 18px 20px 18px 22px;
}

.solution-search__card-header-accent {
  position: absolute;
  inset: 0 auto 0 0;
  width: 6px;
  background: transparent;
}

.solution-search__collapse-main {
  min-width: 0;
}

.solution-search__collapse-title {
  min-width: 0;
  font-size: 15px;
  font-weight: 700;
  line-height: 1.5;
  color: var(--ink);
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
}

.solution-search__collapse-subline {
  display: flex;
  align-items: center;
  flex-wrap: wrap;
  gap: 8px 12px;
  margin-top: 6px;
  color: var(--ink-soft);
  font-size: 12px;
}

.solution-search__collapse-ticket {
  font-family: 'Cascadia Mono', 'JetBrains Mono', 'SFMono-Regular', Consolas, monospace;
}

.solution-search__collapse-time {
  white-space: nowrap;
}

.solution-search__collapse-tag {
  flex-shrink: 0;
}

.solution-search__card-side {
  display: flex;
  align-items: center;
  gap: 12px;
  flex-shrink: 0;
}

.solution-search__card-arrow {
  width: 11px;
  height: 11px;
  border-right: 2px solid #7c8b99;
  border-bottom: 2px solid #7c8b99;
  transform: rotate(45deg) translateY(-1px);
  transform-origin: center;
  transition: transform 0.22s ease, border-color 0.22s ease;
}

.solution-search__card-arrow.is-expanded {
  transform: rotate(225deg) translateY(-1px);
}

.solution-search__card-body {
  border-top: 1px solid rgba(226, 232, 240, 0.95);
  background: linear-gradient(180deg, rgba(252, 254, 255, 0.98), rgba(247, 250, 252, 0.98));
}

.solution-search__panel {
  padding: 20px 20px 22px;
}

.solution-search__panel-top {
  margin-bottom: 18px;
}

.ticket-detail-card__meta {
  display: grid;
  gap: 10px;
  margin: 0 0 18px;
}

.ticket-field,
.ticket-section {
  display: grid;
  grid-template-columns: 120px minmax(0, 1fr);
  gap: 10px 14px;
  align-items: start;
}

.ticket-field__label,
.ticket-section__label {
  color: var(--ink-soft);
  font-size: 13px;
  font-weight: 600;
}

.ticket-field__value,
.ticket-section__body {
  min-width: 0;
  line-height: 1.72;
}

.multiline-text {
  white-space: pre-wrap;
  word-break: break-word;
}

.ticket-state--pending {
  border-color: rgba(245, 158, 11, 0.35);
}

.ticket-state--pending .solution-search__card-header-accent {
  background: linear-gradient(180deg, #f59e0b, #fbbf24);
}

.ticket-state--in-progress {
  border-color: rgba(59, 130, 246, 0.35);
}

.ticket-state--in-progress .solution-search__card-header-accent {
  background: linear-gradient(180deg, #3b82f6, #60a5fa);
}

.ticket-state--resolved {
  border-color: rgba(16, 185, 129, 0.35);
}

.ticket-state--resolved .solution-search__card-header-accent {
  background: linear-gradient(180deg, #10b981, #34d399);
}

.ticket-state--closed {
  border-color: rgba(100, 116, 139, 0.35);
}

.ticket-state--closed .solution-search__card-header-accent {
  background: linear-gradient(180deg, #64748b, #94a3b8);
}

@media (max-width: 768px) {
  .query-line {
    grid-template-columns: 1fr;
  }

  .solution-search__card-main,
  .ticket-field,
  .ticket-section {
    grid-template-columns: 1fr;
  }

  .solution-search__card-main,
  .solution-search__card-side {
    flex-direction: column;
    align-items: flex-start;
  }

  .solution-search__card-main {
    padding: 16px 16px 16px 18px;
  }

  .solution-search__panel {
    padding: 18px 16px 20px;
  }

  .ticket-field,
  .ticket-section {
    gap: 8px;
  }
}
</style>
