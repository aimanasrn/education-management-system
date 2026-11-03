"use client";

import Link from "next/link";
import type { ReactNode } from "react";
import { Header } from "@/components/layout/header";
import { Sidebar } from "@/components/layout/sidebar";
import { useDisplay } from "@/components/layout/display-preferences";
import { WorkspaceProvider } from "@/components/layout/workspace-context";
import { Breadcrumb, BreadcrumbItem, BreadcrumbLink, BreadcrumbList, BreadcrumbPage, BreadcrumbSeparator } from "@/components/ui/breadcrumb";
import { SidebarInset, SidebarProvider } from "@/components/ui/sidebar";
import type { ShellUser, WorkspaceChrome } from "@/lib/workspace-types";
import { pageTitleKeys } from "@/lib/workspace-copy";

export function WorkspaceFrame({ user, chrome, title, children }: { user: ShellUser; chrome: WorkspaceChrome; title: string; children: ReactNode }) {
  const { t } = useDisplay();
  const pageTitle = t(pageTitleKeys[title] ?? title);
  return <WorkspaceProvider user={user} chrome={chrome}><SidebarProvider>
    <Sidebar /><SidebarInset className="min-w-0 bg-background">
      <Header />
      <div className="flex min-w-0 flex-1 flex-col gap-6 p-4 sm:p-6">
        <h1 className="sr-only">{pageTitle}</h1>
        {!["Overview", "Dashboard Overview", "Campus Live Monitor"].includes(title) ? <Breadcrumb><BreadcrumbList><BreadcrumbItem><BreadcrumbLink render={<Link href="/dashboard" />}>{t("workspace")}</BreadcrumbLink></BreadcrumbItem><BreadcrumbSeparator /><BreadcrumbItem><BreadcrumbPage>{pageTitle}</BreadcrumbPage></BreadcrumbItem></BreadcrumbList></Breadcrumb> : null}
        {children}
      </div>
    </SidebarInset>
  </SidebarProvider></WorkspaceProvider>;
}
