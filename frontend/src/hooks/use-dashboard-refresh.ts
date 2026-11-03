"use client";

import { useCallback, useEffect, useTransition } from "react";
import { useRouter } from "next/navigation";

export function useDashboardRefresh() {
  const router = useRouter();
  const [pending, startTransition] = useTransition();
  const refresh = useCallback(() => startTransition(() => router.refresh()), [router]);
  useEffect(() => {
    const update = () => {
      const active = document.activeElement;
      const editing = active instanceof HTMLElement && (["INPUT", "TEXTAREA"].includes(active.tagName) || active.isContentEditable);
      if (document.visibilityState === "visible" && !editing && !document.querySelector('[role="dialog"],[role="alertdialog"],[role="menu"],[role="listbox"]')) refresh();
    };
    const interval = window.setInterval(update, 30000);
    document.addEventListener("visibilitychange", update);
    return () => { clearInterval(interval); document.removeEventListener("visibilitychange", update); };
  }, [refresh]);
  return { pending, refresh };
}
