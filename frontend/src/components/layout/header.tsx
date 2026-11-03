"use client";

import { useState, useSyncExternalStore, useTransition } from "react";
import { BellIcon, Building2Icon, CheckIcon, ChevronDownIcon, GlobeIcon, LogOutIcon, MoonIcon, SettingsIcon, SunIcon } from "lucide-react";
import { logout } from "@/app/actions";
import { GlobalSearch } from "@/components/layout/global-search";
import { ActivityList } from "@/components/dashboard/activity-list";
import { useDisplay } from "@/components/layout/display-preferences";
import { useWorkspace } from "@/components/layout/workspace-context";
import { Alert, AlertDescription } from "@/components/ui/alert";
import { Avatar, AvatarFallback } from "@/components/ui/avatar";
import { Badge } from "@/components/ui/badge";
import { Button } from "@/components/ui/button";
import { Dialog, DialogContent, DialogDescription, DialogHeader, DialogTitle } from "@/components/ui/dialog";
import { DropdownMenu, DropdownMenuContent, DropdownMenuGroup, DropdownMenuItem, DropdownMenuLabel, DropdownMenuSeparator, DropdownMenuTrigger } from "@/components/ui/dropdown-menu";
import { Field, FieldDescription, FieldGroup, FieldLabel } from "@/components/ui/field";
import { Popover, PopoverContent, PopoverDescription, PopoverHeader, PopoverTitle, PopoverTrigger } from "@/components/ui/popover";
import { Select, SelectContent, SelectGroup, SelectItem, SelectTrigger, SelectValue } from "@/components/ui/select";
import { SidebarTrigger } from "@/components/ui/sidebar";
import { Spinner } from "@/components/ui/spinner";
import { initials } from "@/lib/workspace-navigation";

function subscribeActivity(listener: () => void) {
  window.addEventListener("storage", listener);
  window.addEventListener("ems:activity-seen", listener);
  return () => { window.removeEventListener("storage", listener); window.removeEventListener("ems:activity-seen", listener); };
}

export function Header() {
  const { user, chrome, campusId, setCampus, switching } = useWorkspace();
  const { t, locale, theme, setLocale, setTheme } = useDisplay();
  const [settings, setSettings] = useState(false);
  const [signingOut, startSignOut] = useTransition();
  const [logoutError, setLogoutError] = useState("");
  const canAudit = user.permissions.includes("audit.view");
  const newestActivity = chrome.activity[0]?.id ?? 0;
  const seenKey = "ems-activity-seen-" + user.id;
  const seen = useSyncExternalStore(subscribeActivity, () => {
    try { return window.localStorage.getItem(seenKey) ?? "0"; } catch { return String(newestActivity); }
  }, () => String(newestActivity));
  const unread = canAudit && newestActivity > Number(seen);
  const campusItems = [{ value: "", label: t("allCampuses") }, ...chrome.campuses.map(campus => ({ value: String(campus.id), label: campus.label }))];
  const roles = user.roles.map(role => t(role === "teacher" ? "teacherRole" : role));
  function markSeen(open: boolean) {
    if (!open || !canAudit) return;
    try { window.localStorage.setItem(seenKey, String(newestActivity)); } catch { return; }
    window.dispatchEvent(new Event("ems:activity-seen"));
  }
  function signOut() {
    setLogoutError("");
    startSignOut(async () => {
      const state = await logout();
      if (state.error) setLogoutError(state.error);
    });
  }

  return <>
    <header className="sticky top-0 z-20 border-b border-border/70 bg-card">
      <div className="flex min-h-19 items-center gap-3 px-4 sm:gap-5 sm:px-6">
        <SidebarTrigger aria-label="Toggle navigation" />
        <GlobalSearch />
        <div className="ml-auto hidden min-w-0 max-w-64 md:block">
          <Select value={campusId} items={campusItems} onValueChange={value => setCampus(value ?? "")} disabled={switching || !chrome.campuses.length}>
            <SelectTrigger className="h-10 w-full" aria-label={t("campusWorkspace")}>
              {switching ? <Spinner /> : <Building2Icon />}<SelectValue>{!chrome.campuses.length ? chrome.campusError ? t("campusUnavailable") : t("noCampuses") : undefined}</SelectValue>
            </SelectTrigger>
            <SelectContent><SelectGroup>{campusItems.map(item => <SelectItem key={item.value} value={item.value}>{item.label}</SelectItem>)}</SelectGroup></SelectContent>
          </Select>
        </div>
        <div className="flex shrink-0 items-center gap-1 sm:gap-2">
          <DropdownMenu><DropdownMenuTrigger render={<Button variant="ghost" size="sm" aria-label={t("language")} />}><GlobeIcon data-icon="inline-start" /><span className="hidden sm:inline">{locale === "ms" ? "BM" : "EN"}</span></DropdownMenuTrigger>
            <DropdownMenuContent align="end"><DropdownMenuGroup><DropdownMenuLabel>{t("language")}</DropdownMenuLabel><DropdownMenuItem onClick={() => setLocale("en")}>{t("english")}{locale === "en" ? <CheckIcon className="ml-auto" /> : null}</DropdownMenuItem><DropdownMenuItem onClick={() => setLocale("ms")}>{t("malay")}{locale === "ms" ? <CheckIcon className="ml-auto" /> : null}</DropdownMenuItem></DropdownMenuGroup></DropdownMenuContent>
          </DropdownMenu>
          <Button variant="ghost" size="icon-lg" aria-label={t(theme === "dark" ? "lightMode" : "darkMode")} onClick={() => setTheme(theme === "dark" ? "light" : "dark")}>{theme === "dark" ? <SunIcon /> : <MoonIcon />}</Button>
          <Popover onOpenChange={markSeen}><PopoverTrigger render={<Button variant="ghost" size="icon-lg" className="relative" aria-label={t("recentActivity")} />}>
            <BellIcon />{unread ? <Badge variant="rose" className="absolute right-1.5 top-1 size-2 min-w-0 p-0" aria-label="New activity" /> : null}
          </PopoverTrigger><PopoverContent align="end" className="w-[min(23rem,calc(100vw-2rem))] p-5">
            <PopoverHeader><PopoverTitle>{t("recentActivity")}</PopoverTitle><PopoverDescription>{t("activityDescription")}</PopoverDescription></PopoverHeader>
            {!canAudit ? <p className="py-4 text-sm text-muted-foreground">{t("activityRestricted")}</p> : chrome.activityError ? <p role="alert" className="py-4 text-sm text-muted-foreground">{t("activityUnavailable")}</p> : <ActivityList activity={chrome.activity.slice(0,4)} compact />}
          </PopoverContent></Popover>
          <DropdownMenu><DropdownMenuTrigger render={<Button variant="profile" size="profile" aria-label={t("profile")} />}>
            <Avatar size="lg"><AvatarFallback>{initials(user.name)}</AvatarFallback></Avatar>
            <span className="hidden min-w-0 flex-col items-start gap-0.5 lg:flex"><span className="max-w-40 truncate text-xs font-semibold">{user.name}</span><Badge variant="secondary" className="max-w-40 truncate" title={roles.join(" · ")}>{roles[0]}{roles.length > 1 ? " +" + (roles.length - 1) : ""}</Badge></span><ChevronDownIcon className="hidden lg:block" />
          </DropdownMenuTrigger><DropdownMenuContent align="end" className="w-72">
            <DropdownMenuGroup><DropdownMenuLabel><span className="block truncate">{user.name}</span><span className="block truncate text-xs font-normal">{user.email}</span></DropdownMenuLabel><DropdownMenuItem onClick={() => setSettings(true)}><SettingsIcon />{t("accountSettings")}</DropdownMenuItem></DropdownMenuGroup>
            <DropdownMenuSeparator /><DropdownMenuGroup><DropdownMenuItem disabled={signingOut} onClick={signOut}>{signingOut ? <Spinner /> : <LogOutIcon />}{t(signingOut ? "signingOut" : "signOut")}</DropdownMenuItem></DropdownMenuGroup>
          </DropdownMenuContent></DropdownMenu>
        </div>
      </div>
      <div className="px-4 pb-3 md:hidden"><Select value={campusId} items={campusItems} disabled={switching || !chrome.campuses.length} onValueChange={value => setCampus(value ?? "")}><SelectTrigger className="w-full" aria-label={t("campusWorkspace")}><Building2Icon /><SelectValue>{!chrome.campuses.length ? t("noCampuses") : undefined}</SelectValue></SelectTrigger><SelectContent><SelectGroup>{campusItems.map(item => <SelectItem key={item.value} value={item.value}>{item.label}</SelectItem>)}</SelectGroup></SelectContent></Select></div>
      {logoutError ? <Alert variant="destructive" role="alert"><AlertDescription>{logoutError}</AlertDescription></Alert> : null}
    </header>

    <Dialog open={settings} onOpenChange={setSettings}><DialogContent className="max-h-[90svh] overflow-y-auto sm:max-w-lg"><DialogHeader><DialogTitle>{t("accountSettings")}</DialogTitle><DialogDescription>{t("accountDescription")}</DialogDescription></DialogHeader>
      <div className="flex items-center gap-3"><Avatar size="lg"><AvatarFallback>{initials(user.name)}</AvatarFallback></Avatar><div className="min-w-0"><p className="font-semibold">{user.name}</p><p className="break-all text-sm text-muted-foreground">{user.email}</p></div></div>
      <dl className="flex flex-col gap-3"><div><dt className="text-xs text-muted-foreground">{t("roles")}</dt><dd className="mt-1 flex flex-wrap gap-1">{roles.map(role => <Badge key={role} variant="secondary">{role}</Badge>)}</dd></div><div><dt className="text-xs text-muted-foreground">{t("assignedCampuses")}</dt><dd className="mt-1 text-sm">{user.campus_names.join(", ") || (user.roles.some(role => ["admin","centre_admin"].includes(role)) ? t("allCampuses") : t("noCampuses"))}</dd></div></dl>
      <FieldGroup>
        <Field><FieldLabel htmlFor="display-language">{t("language")}</FieldLabel><Select value={locale} items={[{value:"en",label:"English"},{value:"ms",label:"Bahasa Melayu"}]} onValueChange={value => setLocale(value === "ms" ? "ms" : "en")}><SelectTrigger id="display-language" className="w-full"><SelectValue /></SelectTrigger><SelectContent><SelectGroup><SelectItem value="en">English</SelectItem><SelectItem value="ms">Bahasa Melayu</SelectItem></SelectGroup></SelectContent></Select><FieldDescription>{t("displayLanguageHint")}</FieldDescription></Field>
        <Field><FieldLabel htmlFor="display-theme">{t("appearance")}</FieldLabel><Select value={theme} items={[{value:"light",label:t("light")},{value:"dark",label:t("dark")}]} onValueChange={value => setTheme(value === "dark" ? "dark" : "light")}><SelectTrigger id="display-theme" className="w-full"><SelectValue /></SelectTrigger><SelectContent><SelectGroup><SelectItem value="light">{t("light")}</SelectItem><SelectItem value="dark">{t("dark")}</SelectItem></SelectGroup></SelectContent></Select></Field>
      </FieldGroup>
    </DialogContent></Dialog>
  </>;
}
