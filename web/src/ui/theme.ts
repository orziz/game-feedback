import type { GlobalThemeOverrides } from 'naive-ui'

export const themeOverrides: GlobalThemeOverrides = {
  common: {
    primaryColor: '#0f766e',
    primaryColorHover: '#0d9488',
    primaryColorPressed: '#115e59',
    primaryColorSuppl: '#14b8a6',
    infoColor: '#2563eb',
    successColor: '#15803d',
    warningColor: '#d97706',
    errorColor: '#dc2626',
    borderRadius: '12px',
    borderRadiusSmall: '8px',
    fontFamily: '"Source Han Sans SC", "Noto Sans SC", "PingFang SC", "Microsoft YaHei", sans-serif',
    fontSize: '14px',
  },
  Button: {
    borderRadiusTiny: '8px',
    borderRadiusSmall: '10px',
    borderRadiusMedium: '12px',
    borderRadiusLarge: '14px',
    colorPrimary: '#0f766e',
    colorHoverPrimary: '#0d9488',
    colorPressedPrimary: '#115e59',
    textColorPrimary: '#ffffff',
  },
  Card: {
    borderRadius: '20px',
    paddingSmall: '14px 16px',
    paddingMedium: '16px 20px',
    titleFontSizeMedium: '15px',
    titleFontWeight: '700',
  },
  Input: {
    borderRadius: '10px',
  },
  Select: {
    peers: {
      InternalSelection: { borderRadius: '10px' },
      InternalSelectMenu: { borderRadius: '12px' },
    },
  },
  DataTable: {
    borderRadius: '16px',
    thPaddingSmall: '8px 12px',
    tdPaddingSmall: '8px 12px',
  },
  Tabs: {
    tabBorderRadius: '12px',
  },
  Drawer: {
    borderRadius: '0',
    resizableTriggerColorHover: '#0f766e',
  },
  Modal: {
    borderRadius: '20px',
  },
  Tag: {
    borderRadius: '999px',
  },
  Form: {
    labelFontSizeTopSmall: '12px',
    labelFontSizeTopMedium: '14px',
  },
  Skeleton: {
    color: 'rgba(15, 118, 110, 0.08)',
    colorEnd: 'rgba(15, 118, 110, 0.16)',
  },
  Dialog: {
    borderRadius: '20px',
    padding: '24px 28px',
    titleFontSize: '16px',
    titleFontWeight: '700',
  },
  Message: {
    borderRadius: '12px',
  },
}
