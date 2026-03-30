import { createI18n } from 'vue-i18n'

/** localStorage 中保存语言偏好的键名 */
const LOCALE_STORAGE_KEY = 'feedback-form-locale'

type MessageSchema = Record<string, unknown>
type Translator    = (key: string, values?: Record<string, unknown>) => string

/** 工单状态枚举值对应的 i18n 键 */
const STATUS_KEYS:   readonly string[] = ['pending', 'inProgress', 'resolved', 'closed']
/** 反馈类型枚举值对应的 i18n 键 */
const TYPE_KEYS:     readonly string[] = ['bug', 'improvement', 'suggestion', 'other']
/** 严重程度枚举值对应的 i18n 键 */
const SEVERITY_KEYS: readonly string[] = ['low', 'medium', 'high', 'critical']

/** 通过 import.meta.glob 自动导入 locales 目录下的所有语言文件 */
const localeModules = import.meta.glob<{ default: MessageSchema }>('./locales/*.ts', { eager: true })

const messages = Object.entries(localeModules).reduce<Record<LocaleCode, MessageSchema>>(
  (all, [path, module]) => {
    const locale = path.match(/\.\/locales\/(.+)\.ts$/)?.[1] as LocaleCode | undefined
    if (locale) all[locale] = module.default
    return all
  },
  {} as Record<LocaleCode, MessageSchema>,
)

/**
 * 检测初始语言：优先读取 localStorage，其次读取浏览器语言，默认 zh-CN
 *
 * @returns 初始语言代码
 */
function detectInitialLocale(): LocaleCode {
  if (typeof window === 'undefined') return 'zh-CN'
  const saved = window.localStorage.getItem(LOCALE_STORAGE_KEY)
  if (saved === 'zh-CN' || saved === 'en') return saved
  return window.navigator.language.toLowerCase().startsWith('zh') ? 'zh-CN' : 'en'
}

/** vue-i18n 实例，非 legacy 模式 */
export const i18n = createI18n({
  legacy:         false,
  locale:         detectInitialLocale(),
  fallbackLocale: 'zh-CN' as const,
  messages:       messages as Record<string, any>,
})

/**
 * 将语言偏好持久化到 localStorage
 *
 * @param locale - 要保存的语言代码
 */
export function persistLocale(locale: LocaleCode): void {
  if (typeof window !== 'undefined') {
    window.localStorage.setItem(LOCALE_STORAGE_KEY, locale)
  }
}

/**
 * 翻译工单状态枚举值为可读文案
 *
 * @param t - vue-i18n 翻译函数
 * @param value - 工单状态枚举值（0-3）
 */
export function translateStatus(t: Translator, value: TicketStatus): string {
  return t('status.' + (STATUS_KEYS[value] ?? 'pending'))
}

/**
 * 翻译反馈类型枚举值为可读文案
 *
 * @param t - vue-i18n 翻译函数
 * @param value - 反馈类型枚举值（0-3）
 */
export function translateType(t: Translator, value: FeedbackType): string {
  return t('type.' + (TYPE_KEYS[value] ?? 'other'))
}

/**
 * 翻译严重程度枚举值为可读文案
 *
 * @param t - vue-i18n 翻译函数
 * @param value - 严重程度枚举值（0-3）
 */
export function translateSeverity(t: Translator, value: Severity): string {
  return t('severity.' + (SEVERITY_KEYS[value] ?? 'medium'))
}

/**
 * 根据工单状态返回 Element Plus Tag 组件的 type
 *
 * @param status - 工单状态枚举值
 * @returns Tag 颜色类型
 */
export function getStatusTagType(status: TicketStatus): 'success' | 'warning' | 'info' | 'danger' | '' {
  switch (status) {
    case 2:  return 'success'
    case 1:  return 'warning'
    case 0:  return 'info'
    case 3:  return 'danger'
    default: return ''
  }
}

/**
 * 根据反馈类型返回 Element Plus Tag 组件的 type
 *
 * @param type - 反馈类型枚举值
 * @returns Tag 颜色类型
 */
export function getFeedbackTypeTagType(type: FeedbackType): 'danger' | 'warning' | 'success' | 'info' {
  switch (type) {
    case 0:  return 'danger'
    case 1:  return 'warning'
    case 2:  return 'success'
    default: return 'info'
  }
}
