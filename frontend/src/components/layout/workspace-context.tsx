"use client";

import { createContext, useContext, useTransition, type ReactNode } from "react";
import { usePathname, useRouter, useSearchParams } from "next/navigation";
import type { ShellUser, WorkspaceChrome } from "@/lib/workspace-types";
import { workspaceHref } from "@/lib/workspace-navigation";

const Context = createContext<{ user: ShellUser; chrome: WorkspaceChrome; campusId: string; switching: boolean; setCampus: (value: string) => void; href: (path: string) => string } | null>(null);

export function WorkspaceProvider({ user, chrome, children }: { user: ShellUser; chrome: WorkspaceChrome; children: ReactNode }) {
  const query = useSearchParams();
  const pathname = usePathname();
  const router = useRouter();
  const [switching, startTransition] = useTransition();
  const campusId = query.get("campus_id") ?? "";
  function setCampus(value: string) {
    const scoped = ["/dashboard", "/dashboard/campuses", "/campuses/buildings", "/campuses/rooms", "/campuses/calendar", "/academics/classes", "/students/admissions", "/students/enrolments", "/campuses/bookings"].includes(pathname);
    const params = new URLSearchParams(scoped ? query.toString() : "");
    params.delete("page");
    if (value) params.set("campus_id", value); else params.delete("campus_id");
    const nextQuery = params.toString();
    startTransition(() => router.push((scoped ? pathname : "/dashboard") + (nextQuery ? "?" + nextQuery : ""), { scroll: false }));
  }
  return <Context.Provider value={{ user, chrome, campusId, switching, setCampus, href: path => workspaceHref(path, campusId) }}>{children}</Context.Provider>;
}

export function useWorkspace() {
  const value = useContext(Context);
  if (!value) throw new Error("Workspace provider is missing.");
  return value;
}
