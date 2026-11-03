"use client";

import { Building2Icon, DoorOpenIcon, GraduationCapIcon, UserRoundIcon, UsersIcon } from "lucide-react";
import { Badge } from "@/components/ui/badge";
import { Card, CardDescription, CardHeader, CardTitle } from "@/components/ui/card";
import { useDisplay } from "@/components/layout/display-preferences";
import type { Metric } from "@/lib/workspace-types";
import { cn } from "@/lib/utils";

const configurations = [
  { key: "students", label: "activeStudents", tone: "rose", icon: UsersIcon },
  { key: "staff", label: "activeStaff", tone: "amber", icon: UserRoundIcon },
  { key: "classes", label: "activeClasses", tone: "indigo", icon: GraduationCapIcon },
  { key: "rooms", label: "activeRooms", tone: "teal", icon: DoorOpenIcon },
  { key: "campuses", label: "activeCampuses", tone: "emerald", icon: Building2Icon },
] as const;

export function MetricCards({ metrics }: { metrics: Metric[] }) {
  const { t, locale } = useDisplay();
  const number = new Intl.NumberFormat(locale === "ms" ? "ms-MY" : "en-MY");
  const visible = configurations.filter(config => metrics.some(metric => metric.key === config.key));
  const columns = ["", "xl:grid-cols-1", "xl:grid-cols-2", "xl:grid-cols-3", "xl:grid-cols-4", "xl:grid-cols-5"][visible.length];
  return <section aria-label="Centre overview" className={cn("grid grid-cols-2 gap-4 lg:grid-cols-3", columns)}>{visible.map(config => {
    const metric = metrics.find(metric => metric.key === config.key)!;
    return <Card key={config.key} tone={config.tone} size="sm" className="last:odd:col-span-2 sm:last:odd:col-span-1" data-testid={"metric-" + config.key}>
      <CardHeader className="flex flex-col items-start gap-3 sm:flex-row sm:items-center">
        <Badge variant={config.tone} size="icon" aria-hidden="true"><config.icon /></Badge>
        <div className="flex min-w-0 flex-col gap-1"><CardDescription>{t(config.label)}</CardDescription><CardTitle size="metric">{metric.value === null ? "—" : number.format(metric.value)}</CardTitle></div>
      </CardHeader>
    </Card>;
  })}</section>;
}
