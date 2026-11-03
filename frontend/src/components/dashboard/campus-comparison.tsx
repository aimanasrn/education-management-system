"use client";

import Link from "next/link";
import { Badge } from "@/components/ui/badge";
import { Button } from "@/components/ui/button";
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from "@/components/ui/card";
import { Empty, EmptyHeader, EmptyTitle } from "@/components/ui/empty";
import { Progress } from "@/components/ui/progress";
import { useDisplay } from "@/components/layout/display-preferences";
import { useWorkspace } from "@/components/layout/workspace-context";
import type { CampusSummary } from "@/lib/workspace-types";

export function CampusComparison({ campuses, total }: { campuses: CampusSummary[]; total: number }) {
  const { t } = useDisplay();
  const { user, href } = useWorkspace();
  const canClasses = user.permissions.includes("classes.view");
  const max = Math.max(1, ...campuses.flatMap(campus => [campus.rooms ?? 0, campus.classes ?? 0]));
  return <Card className="h-full">
    <CardHeader className="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
      <div><CardTitle role="heading" aria-level={3}>{t("campusOverview")}</CardTitle><CardDescription className="mt-1">{t("campusOverviewDescription")}</CardDescription></div>
      <div className="flex shrink-0 flex-wrap gap-3 text-[11px] text-muted-foreground">{canClasses ? <span className="flex items-center gap-1.5"><Badge variant="indigo" className="size-2 p-0" aria-hidden="true" />{t("activeClasses")}</span> : null}<span className="flex items-center gap-1.5"><Badge variant="teal" className="size-2 p-0" aria-hidden="true" />{t("activeRooms")}</span></div>
    </CardHeader>
    <CardContent>
      {!campuses.length ? <Empty><EmptyHeader><EmptyTitle>{t("noCampusRecords")}</EmptyTitle></EmptyHeader></Empty> : <ul className="flex flex-col">{campuses.map(campus => <li key={campus.id} className="grid gap-3 border-b border-border/70 py-4 first:pt-1 last:border-0 last:pb-0 sm:grid-cols-[minmax(0,1fr)_minmax(0,1.2fr)] sm:items-center">
        <div className="flex min-w-0 flex-col gap-1"><span className="text-[13px] font-medium">{campus.name}</span><div className="flex items-center gap-2"><span className="text-[11px] text-muted-foreground">{campus.code}</span>{campus.status === "inactive" ? <Badge variant="outline">{t("inactive")}</Badge> : null}</div></div>
        <div className="flex flex-col gap-2">{(canClasses ? [["classes", campus.classes, "indigo"], ["rooms", campus.rooms, "teal"]] : [["rooms", campus.rooms, "teal"]]).map(([key, value, tone]) => <div key={String(key)} className="flex items-center gap-3">
          {typeof value === "number" ? <><Progress value={value} max={max} tone={tone as "indigo" | "teal"} className="flex-1" aria-label={campus.name + ": " + value + " " + t(key === "classes" ? "activeClasses" : "activeRooms")} /><span className="w-6 text-right text-xs tabular-nums text-muted-foreground">{value}</span></> : <span className="text-xs text-muted-foreground">{t("unavailable")}</span>}
        </div>)}</div>
      </li>)}</ul>}
      {total > campuses.length ? <div className="mt-4 flex items-center justify-between gap-2"><p className="text-xs text-muted-foreground">{t("showing")} {campuses.length} {t("of")} {total} {t("campusCount")}</p><Button render={<Link href={href("/dashboard/campuses")} />} nativeButton={false} variant="link" size="sm">{t("viewAll")}</Button></div> : null}
    </CardContent>
  </Card>;
}
