"use client";

import Link from "next/link";
import { FileTextIcon } from "lucide-react";
import { Badge } from "@/components/ui/badge";
import { Empty, EmptyDescription, EmptyHeader, EmptyTitle } from "@/components/ui/empty";
import { useDisplay } from "@/components/layout/display-preferences";
import { useWorkspace } from "@/components/layout/workspace-context";
import type { Activity } from "@/lib/workspace-types";

const modules: Record<string, string> = { bookings: "/campuses/bookings", admissions: "/students/admissions", enrolments: "/students/enrolments", student_links: "/students/links", students: "/students", campuses: "/campuses", buildings: "/campuses/buildings", rooms: "/campuses/rooms", years: "/academics/years", terms: "/academics/terms", programmes: "/academics/programmes", subjects: "/academics/subjects", classes: "/academics/classes", closures: "/campuses/calendar", users: "/system/users" };

export function ActivityList({ activity, compact = false }: { activity: Activity[]; compact?: boolean }) {
  const { t, locale } = useDisplay();
  const { href } = useWorkspace();
  const date = new Intl.DateTimeFormat(locale === "ms" ? "ms-MY" : "en-MY", { timeZone: "Asia/Kuala_Lumpur", day: "numeric", month: "short", hour: "numeric", minute: "2-digit" });
  if (!activity.length) return <Empty className="px-0 py-6"><EmptyHeader><EmptyTitle>{t("noActivity")}</EmptyTitle><EmptyDescription>{t("activityDescription")}</EmptyDescription></EmptyHeader></Empty>;
  return <ol className="flex flex-col">{activity.map((item, index) => {
    const moduleLabel = item.subject_type === "bookings" ? "facilityBookings" : item.subject_type === "students" ? "studentRecord" : item.subject_type === "users" ? "staffUsers" : item.subject_type === "campuses" ? "campus" : item.subject_type === "closures" ? "calendar" : item.subject_type;
    const label = item.action === "demo_seeded" ? "demoCreated" : item.action === "created" ? "created" : item.action === "access_updated" ? "accessUpdated" : "updatedAction";
    const path = item.subject_type === "bookings" ? "/campuses/bookings/" + item.subject_id : item.subject_type === "admissions" ? "/students/admissions/" + item.subject_id : item.subject_type === "students" ? "/students/" + item.subject_id : modules[item.subject_type] ?? "/system/audit";
    return <li key={item.id} className="relative flex gap-3 py-2">
      <div className="flex flex-col items-center"><Badge variant="secondary" size="icon-sm" aria-hidden="true"><FileTextIcon /></Badge>{index < activity.length - 1 ? <span className="mt-2 w-px flex-1 bg-border" /> : null}</div>
      <div className="flex min-w-0 flex-1 flex-col gap-1">
        <Link href={href(path)} className="text-[13px] font-semibold leading-snug hover:text-primary">{t(moduleLabel)} #{item.subject_id}</Link>
        <p className="text-xs leading-relaxed text-muted-foreground">{t(label)} · {item.actor}</p>
        {!compact ? <time dateTime={item.created_at} className="text-[11px] text-muted-foreground">{date.format(new Date(item.created_at))} · KL</time> : null}
      </div>
    </li>;
  })}</ol>;
}
