import { createContext, useContext, useState, ReactNode } from "react"
import { Locale, defaultLocale, t as translate } from "@/i18n"

interface LocaleContextType {
  locale: Locale
  setLocale: (locale: Locale) => void
  t: (key: string) => string
}

const LocaleContext = createContext<LocaleContextType | undefined>(undefined)

export function LocaleProvider({ children }: { children: ReactNode }) {
  const [locale, setLocale] = useState<Locale>(() => {
    if (typeof window !== "undefined") {
      return (localStorage.getItem("locale") as Locale) || defaultLocale
    }
    return defaultLocale
  })

  const setLocaleWithStorage = (l: Locale) => {
    setLocale(l)
    if (typeof window !== "undefined") {
      localStorage.setItem("locale", l)
    }
  }

  const t = (key: string) => translate(locale, key)

  return (
    <LocaleContext.Provider value={{ locale, setLocale: setLocaleWithStorage, t }}>
      {children}
    </LocaleContext.Provider>
  )
}

export function useLocale() {
  const context = useContext(LocaleContext)
  if (context === undefined) {
    throw new Error("useLocale must be used within a LocaleProvider")
  }
  return context
}
