import { createDiscreteApi } from 'naive-ui'
import { themeOverrides } from './theme'

export const { message, dialog } = createDiscreteApi(['message', 'dialog'], {
  configProviderProps: { themeOverrides },
})
