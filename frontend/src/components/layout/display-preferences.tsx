"use client";

import { createContext, useContext, useEffect, useSyncExternalStore, type ReactNode } from "react";
import { english, malay } from "@/lib/workspace-copy";

type Locale = "en" | "ms";
type Theme = "light" | "dark";
const storageKey = "ems-display-preferences";
const changedEvent = "ems:display-preferences";

function snapshot() {
  try { return window.localStorage.getItem(storageKey) ?? "en|light"; }
  catch { return "en|light"; }
}
function subscribe(listener: () => void) {
  window.addEventListener("storage", listener);
  window.addEventListener(changedEvent, listener);
  return () => { window.removeEventListener("storage", listener); window.removeEventListener(changedEvent, listener); };
}
const DisplayContext = createContext<{ locale: Locale; theme: Theme; t: (key: string) => string; setLocale: (locale: Locale) => void; setTheme: (theme: Theme) => void } | null>(null);

export function DisplayPreferences({ children }: { children: ReactNode }) {
  const value = useSyncExternalStore(subscribe, snapshot, () => "en|light");
  const [storedLocale, storedTheme] = value.split("|");
  const locale: Locale = storedLocale === "ms" ? "ms" : "en";
  const theme: Theme = storedTheme === "dark" ? "dark" : "light";
  function commit(nextLocale: Locale, nextTheme: Theme) {
    try { window.localStorage.setItem(storageKey, nextLocale + "|" + nextTheme); } catch { return; }
    window.dispatchEvent(new Event(changedEvent));
  }
  useEffect(() => {
    document.documentElement.classList.toggle("dark", theme === "dark");
    document.documentElement.lang = locale;
  }, [locale, theme]);
  const dictionary: Record<string, string> = locale === "ms" ? malay : english;
  return <DisplayContext.Provider value={{ locale, theme, t: key => dictionary[key] ?? key, setLocale: next => commit(next, theme), setTheme: next => commit(locale, next) }}>{children}</DisplayContext.Provider>;
}

export function useDisplay() {
  const value = useContext(DisplayContext);
  if (!value) throw new Error("Display preferences provider is missing.");
  return value;
}
