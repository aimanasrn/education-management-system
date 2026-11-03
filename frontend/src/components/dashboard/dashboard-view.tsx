"use client";

import Link from "next/link";
import { ArrowRightIcon, BookOpenIcon, CalendarDaysIcon, GraduationCapIcon, InfoIcon, RefreshCwIcon } from "lucide-react";
import { ActivityList } from "@/components/dashboard/activity-list";
import { ActiveClasses } from "@/components/dashboard/active-classes";
import { CampusComparison } from "@/components/dashboard/campus-comparison";
import { MetricCards } from "@/components/dashboard/metric-cards";
import { useDisplay } from "@/components/layout/display-preferences";
import { useWorkspace } from "@/components/layout/workspace-context";
import { Alert, AlertDescription } from "@/components/ui/alert";
import { Badge } from "@/components/ui/badge";
import { Button } from "@/components/ui/button";
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from "@/components/ui/card";
import { Empty, EmptyDescription, EmptyHeader, EmptyTitle } from "@/components/ui/empty";
import { Pagination, PaginationContent, PaginationItem, PaginationNext, PaginationPrevious } from "@/components/ui/pagination";
import { useDashboardRefresh } from "@/hooks/use-dashboard-refresh";
import type { WorkspaceDashboard } from "@/lib/workspace-types";
import { cn } from "@/lib/utils";

export function DashboardView({ data, monitor = false }: { data: WorkspaceDashboard; monitor?: boolean }) {
  const { t, locale } = useDisplay();
  const { user, href, campusId } = useWorkspace();
  const { pending, refresh } = useDashboardRefresh();
  const canCampus = user.permissions.includes("campuses.view");
  const canClasses = user.permissions.includes("classes.view");
  const canAudit = user.permissions.includes("audit.view");
  const language = locale === "ms" ? "ms-MY" : "en-MY";
  const updated = data.asOf ? new Intl.DateTimeFormat(language, { timeZone: "Asia/Kuala_Lumpur", day: "numeric", month: "short", hour: "numeric", minute: "2-digit" }).format(new Date(data.asOf)) : t("unavailable");
  const day = data.asOf ? new Intl.DateTimeFormat(language, { timeZone: "Asia/Kuala_Lumpur", weekday: "long", day: "numeric", month: "long", year: "numeric" }).format(new Date(data.asOf)) : "";
  function campusPage(page: number) {
    const params = new URLSearchParams();
    if (campusId) params.set("campus_id", campusId);
    if (page > 1) params.set("page", String(page));
    return "/dashboard/campuses" + (params.size ? "?" + params : "");
  }
  return <>
    <section className="flex flex-col justify-between gap-4 sm:flex-row sm:items-center">
      <div className="flex min-w-0 flex-col gap-1.5"><h2 className="text-2xl font-bold tracking-tight sm:text-[28px]">{t(monitor ? "campusMonitor" : "dashboardOverview")}</h2><p className="text-sm text-muted-foreground">{data.selectedCampus?.name ?? t(monitor ? "monitorDescription" : "dashboardSubtitle")}</p></div>
      <div className="flex shrink-0 items-center justify-between gap-4 sm:justify-end">
        <div className="text-[11px] leading-relaxed text-muted-foreground" role="status"><p>{t("updated")} {updated} · KL</p><p>{t("autoRefresh")}</p></div>
        <Button variant="outline" size="lg" onClick={refresh} disabled={pending}><RefreshCwIcon data-icon="inline-start" className={cn(pending && "animate-spin")} />{t(pending ? "refreshing" : "refresh")}</Button>
      </div>
    </section>

    {data.errors.overview || data.errors.campuses ? <Alert variant="destructive" role="alert"><AlertDescription>{t("dataUnavailable")}</AlertDescription></Alert> : null}
    {data.metrics.length ? <MetricCards metrics={data.metrics} /> : !data.errors.overview ? <Empty><EmptyHeader><EmptyTitle>{t("accountReady")}</EmptyTitle><EmptyDescription>{t("learningPending")}</EmptyDescription><EmptyDescription>{t("noCampusAccess")}</EmptyDescription></EmptyHeader>{user.permissions.includes("learning.view")?<Button render={<Link href="/students/records" />} nativeButton={false}>{t("learningRecords")}</Button>:null}</Empty> : null}

    {canCampus ? <div className={cn("grid gap-5", !monitor && "xl:grid-cols-12")}>
      {!monitor ? <Card tone="indigo" className="xl:col-span-4">
        <CardHeader className="flex flex-row items-start gap-4">
          <Badge variant="indigo" size="icon" aria-hidden="true"><GraduationCapIcon /></Badge>
          <div className="min-w-0"><CardDescription>{t("welcomeBack")}</CardDescription><CardTitle size="welcome" className="mt-1 break-words">{user.name}</CardTitle><p className="mt-2 text-xs text-muted-foreground">{day}</p></div>
        </CardHeader>
        <CardContent className="flex flex-1 flex-col justify-between gap-5">
          <div className="grid grid-cols-2 gap-3">
            {data.year !== null ? <Card size="sm" className="data-[size=sm]:[--card-spacing:--spacing(3)]"><CardHeader className="flex flex-row items-center gap-2"><Badge variant="secondary" size="icon-sm"><CalendarDaysIcon /></Badge><div><CardDescription className="text-xs">{t("academicYear")}</CardDescription><CardTitle>{data.year}</CardTitle></div></CardHeader></Card> : null}
            {data.programmes !== null ? <Card size="sm" className="data-[size=sm]:[--card-spacing:--spacing(3)]"><CardHeader className="flex flex-row items-center gap-2"><Badge variant="secondary" size="icon-sm"><BookOpenIcon /></Badge><div><CardDescription className="text-xs">{t("programmes")}</CardDescription><CardTitle>{data.programmes}</CardTitle></div></CardHeader></Card> : null}
          </div>
          <div><Button size="lg" render={<Link href={href(canClasses ? "/academics/classes" : "/campuses")} />} nativeButton={false}>{t(canClasses ? "viewClasses" : "viewCampuses")}<ArrowRightIcon data-icon="inline-end" /></Button></div>
        </CardContent>
      </Card> : null}
      <div className={cn(!monitor && "xl:col-span-8")}><CampusComparison campuses={data.campuses} total={data.campusTotal} /></div>
    </div> : null}

    {monitor && data.campusLastPage > 1 ? <Pagination><PaginationContent>{data.campusPage > 1 ? <PaginationItem><PaginationPrevious text={t("previous")} href={campusPage(data.campusPage - 1)} /></PaginationItem> : null}<PaginationItem><span className="px-3 text-xs text-muted-foreground">{data.campusPage} / {data.campusLastPage}</span></PaginationItem>{data.campusPage < data.campusLastPage ? <PaginationItem><PaginationNext text={t("next")} href={campusPage(data.campusPage + 1)} /></PaginationItem> : null}</PaginationContent></Pagination> : null}

    {canClasses || canAudit ? <div className={cn("grid grid-cols-1 gap-5", canClasses && canAudit && "xl:grid-cols-12")}>
      {canClasses ? <div className={cn("min-w-0", canAudit && "xl:col-span-8")}><ActiveClasses classes={data.classes} total={data.classesTotal} failed={data.errors.classes} /></div> : null}
      {canAudit ? <Card className={cn(canClasses && "xl:col-span-4")}><CardHeader className="flex flex-row items-start justify-between gap-3"><div><CardTitle role="heading" aria-level={3}>{t("recentActivity")}</CardTitle><CardDescription className="mt-1">{t("activityDescription")}</CardDescription></div><Button variant="outline" size="sm" render={<Link href="/system/audit" />} nativeButton={false}>{t("viewAll")}</Button></CardHeader><CardContent>{data.errors.activity ? <p role="alert" className="text-sm text-muted-foreground">{t("activityUnavailable")}</p> : <ActivityList activity={data.activity} />}</CardContent></Card> : null}
    </div> : null}
    {data.metrics.length ? <Alert><InfoIcon /><AlertDescription>{t("scopeNote")}</AlertDescription></Alert> : null}
  </>;
}
