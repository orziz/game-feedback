import { createI18n } from 'vue-i18n'

const LOCALE_STORAGE_KEY = 'feedback-form-locale'

type MessageSchema = Record<string, unknown>
type Translator    = (key: string, values?: Record<string, unknown>) => string

const STATUS_KEYS:   readonly string[] = ['pending', 'inProgress', 'resolved', 'closed']
const TYPE_KEYS:     readonly string[] = ['bug', 'improvement', 'suggestion', 'other']
const SEVERITY_KEYS: readonly string[] = ['low', 'medium', 'high', 'critical']

const localeModules = import.meta.glob<{ default: MessageSchema }>('./locales/*.ts', { eager: true })

const messages = Object.entries(localeModules).reduce<Record<LocaleCode, MessageSchema>>(
  (all, [path, module]) => {
    const locale = path.match(/\.\/locales\/(.+)\.ts$/)?.[1] as LocaleCode | undefined
    if (locale) all[locale] = module.default
    return all
  },
  {} as Record<LocaleCode, MessageSchema>,
)

function detectInitialLocale(): LocaleCode {
  if (typeof window === 'undefined') return 'zh-CN'
  const saved = window.localStorage.getItem(LOCALE_STORAGE_KEY)
  if (saved === 'zh-CN' || saved === 'en') return saved
  return window.navigator.language.toLowerCase().startsWith('zh') ? 'zh-CN' : 'en'
}

export const i18n = createI18n({
  legacy:         false,
  locale:         detectInitialLocale(),
  fallbackLocale: 'zh-CN' as const,
  messages:       messages as Record<string, any>,
})

export function persistLocale(locale: LocaleCode): void {
  if (typeof window !== 'undefined') {
    window.localStorage.setItem(LOCALE_STORAGE_KEY, locale)
  }
}

export function translateStatus(t: Translator, value: TicketStatus): string {
  return t('status.' + (STATUS_KEYS[value] ?? 'pending'))
}

export function translateType(t: Translator, value: FeedbackType): string {
  return t('type.' + (TYPE_KEYS[value] ?? 'other'))
}

export function translateSeverity(t: Translator, value: Severity): string {
  return t('severity.' + (SEVERITY_KEYS[value] ?? 'medium'))
}

export function getStatusTagType(status: TicketStatus): 'success' | 'warning' | 'info' | 'danger' | '' {
  switch (status) {
    case 2:  return 'success'
    case 1:  return 'warning'
    case 0:  return 'info'
    case 3:  return 'danger'
    default: return ''
  }
}

export function getFeedbackTypeTagType(type: FeedbackType): 'danger' | 'warning' | 'success' | 'info' {
  switch (type) {
    case 0:  return 'danger'
    case 1:  return 'warning'
    case 2:  return 'success'
    default: return 'info'
  }
}
