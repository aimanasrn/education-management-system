"use client";

import Link from "next/link";
import { usePathname, useSearchParams } from "next/navigation";
import { CircleIcon } from "lucide-react";
import { Brand } from "@/components/brand";
import { SignOut } from "@/components/sign-out";
import { Accordion, AccordionItem, AccordionTrigger, AccordionContent } from "@/components/ui/accordion";
import { Badge } from "@/components/ui/badge";
import { Sidebar as SidebarFrame, SidebarContent, SidebarFooter, SidebarGroup, SidebarGroupContent, SidebarGroupLabel, SidebarHeader, SidebarMenu, SidebarMenuButton, SidebarMenuItem, SidebarTrigger, useSidebar } from "@/components/ui/sidebar";
import { useDisplay } from "@/components/layout/display-preferences";
import { useWorkspace } from "@/components/layout/workspace-context";
import { allowedNavigation, type NavigationItem } from "@/lib/workspace-navigation";

export function Sidebar() {
  const { user, chrome, href } = useWorkspace();
  const { t } = useDisplay();
  const { setOpenMobile } = useSidebar();
  const pathname = usePathname();
  const query = useSearchParams();
  const groups = allowedNavigation(user.permissions);
  const students = chrome.overview?.metrics.find(metric => metric.key === "students")?.value;
  function active(item: NavigationItem) {
    if (!item.href) return false;
    const [path, params] = item.href.split("?");
    if (path === "/students" && /^\/students\/\d+$/.test(pathname)) return true;
    if (["/students/admissions", "/students/records", "/campuses/bookings"].includes(path) && pathname.startsWith(path + "/")) return true;
    if (path !== pathname) return false;
    return (new URLSearchParams(params).get("view") ?? "") === (query.get("view") ?? "");
  }
  function itemRow(item: NavigationItem, nested = false) {
    const Icon = nested ? CircleIcon : item.icon;
    return <SidebarMenuItem key={item.key}><SidebarMenuButton render={item.href ? <Link href={href(item.href)} /> : undefined}
      className={nested ? "[&_svg]:size-2" : undefined} disabled={item.unavailable} isActive={active(item)} aria-current={active(item) ? "page" : undefined}
      onClick={() => { if (item.href) setOpenMobile(false); }}>
      <Icon /><span>{t(item.key)}</span>
      {item.key === "studentProfiles" && students !== undefined && students !== null ? <Badge variant="secondary" className="ml-auto">{students}</Badge> : null}
      {item.unavailable ? <Badge variant="planned" className="ml-auto">{t("comingSoon")}</Badge> : null}
    </SidebarMenuButton></SidebarMenuItem>;
  }
  return <SidebarFrame>
    <SidebarHeader className="px-5 py-5"><div className="flex items-center justify-between gap-2"><Brand stacked /><SidebarTrigger className="md:hidden" /></div></SidebarHeader>
    <SidebarContent className="px-2"><nav aria-label={t("navigation")}>{groups.map(group => <SidebarGroup key={group.key} className="py-3">
      <SidebarGroupLabel>{t(group.key)}</SidebarGroupLabel>
      <SidebarGroupContent><SidebarMenu>{group.items.map(item => item.children?.length ? <SidebarMenuItem key={item.key}>
        <Accordion multiple defaultValue={item.children.some(active) || item.key === "buildingsRooms" ? [item.key] : []}>
          <AccordionItem value={item.key} navigation>
            <AccordionTrigger navigation data-active={item.children.some(active)}><item.icon className="size-4 shrink-0" /><span className="min-w-0 flex-1">{t(item.key)}</span></AccordionTrigger>
            <AccordionContent navigation><SidebarMenu>{item.children.map(child => itemRow(child, true))}</SidebarMenu></AccordionContent>
          </AccordionItem>
        </Accordion>
      </SidebarMenuItem> : itemRow(item))}</SidebarMenu></SidebarGroupContent>
    </SidebarGroup>)}</nav></SidebarContent>
    <SidebarFooter className="px-5 py-4"><SignOut label={t("signOut")} pendingLabel={t("signingOut")} /></SidebarFooter>
  </SidebarFrame>;
}
