export type Locale = 'en' | 'sn' // English, Shona (common in Zimbabwe)

export const defaultLocale: Locale = 'en'

export const translations: Record<Locale, Record<string, string>> = {
  en: {
    'app.name': 'TorqueERP',
    'nav.home': 'Home',
    'nav.login': 'Login',
    'auth.login': 'Sign in',
    'auth.email': 'Email',
    'auth.password': 'Password',
    'common.loading': 'Loading...',
    'common.error': 'Something went wrong',
    'common.empty': 'No data',
    'common.retry': 'Try again',
    'common.accessDenied': 'Access denied',
  },
  sn: {
    'app.name': 'TorqueERP',
    'nav.home': 'Kumba',
    'nav.login': 'Pinda',
    'auth.login': 'Pinda',
    'auth.email': 'Email',
    'auth.password': 'Pasiwedhi',
    'common.loading': 'Kumirira...',
    'common.error': 'Pane chakanganisika',
    'common.empty': 'Hapana data',
    'common.retry': 'Edza zvakare',
    'common.accessDenied': 'Haurikubvumidzwa',
  },
}

export function t(locale: Locale, key: string): string {
  return translations[locale]?.[key] || translations[defaultLocale][key] || key
}
