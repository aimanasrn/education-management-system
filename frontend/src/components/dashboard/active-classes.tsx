"use client";

import Link from "next/link";
import { Avatar, AvatarFallback } from "@/components/ui/avatar";
import { Badge } from "@/components/ui/badge";
import { Button } from "@/components/ui/button";
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from "@/components/ui/card";
import { Empty, EmptyHeader, EmptyTitle } from "@/components/ui/empty";
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from "@/components/ui/table";
import { useDisplay } from "@/components/layout/display-preferences";
import { useWorkspace } from "@/components/layout/workspace-context";
import { initials } from "@/lib/workspace-navigation";
import type { ClassSummary } from "@/lib/workspace-types";

export function ActiveClasses({ classes, total, failed }: { classes: ClassSummary[]; total: number | null; failed: boolean }) {
  const { t } = useDisplay();
  const { href } = useWorkspace();
  function classHref(record: ClassSummary) {
    const path = new URL(href("/academics/classes"), "https://workspace.local");
    path.searchParams.set("q", record.code); path.searchParams.set("status", "active");
    return path.pathname + path.search;
  }
  return <Card className="h-full">
    <CardHeader className="flex flex-row items-start justify-between gap-3"><div><CardTitle role="heading" aria-level={3}>{t("activeClasses")}</CardTitle><CardDescription className="mt-1">{t("classDescription")}</CardDescription></div><Button variant="outline" size="sm" render={<Link href={href("/academics/classes")} />} nativeButton={false}>{t("viewAll")}</Button></CardHeader>
    <CardContent>
      {failed ? <p role="alert" className="py-6 text-sm text-muted-foreground">{t("dataUnavailable")}</p> : !classes.length ? <Empty><EmptyHeader><EmptyTitle>{t("noClasses")}</EmptyTitle></EmptyHeader></Empty> : <Table className="min-w-[620px] table-fixed text-xs">
        <TableHeader><TableRow>{["class", "programme", "teacher", "campus", "capacity", "status"].map((key, index) => <TableHead key={key} className={["w-[25%]", "w-[16%]", "w-[22%]", "w-[17%]", "w-[10%]", "w-[10%]"][index]}>{t(key)}</TableHead>)}</TableRow></TableHeader>
        <TableBody>{classes.map(record => {
          const teacher = record.labels.teachers?.split(", ")[0];
          const additional = Math.max(0, record.teacher_ids.length - 1);
          return <TableRow key={record.id}>
            <TableCell><Button variant="link" size="sm" render={<Link href={classHref(record)} />} nativeButton={false} className="h-auto max-w-full justify-start p-0 text-xs"><span className="truncate" title={record.name}>{record.name}</span></Button><p className="text-[11px] text-muted-foreground">{record.code}</p></TableCell>
            <TableCell><span className="block max-w-24 truncate" title={record.labels.programme ?? ""}>{record.labels.programme}</span></TableCell>
            <TableCell><div className="flex min-w-0 items-center gap-1.5" title={record.labels.teachers ?? ""}>{teacher ? <Avatar size="sm"><AvatarFallback>{initials(teacher)}</AvatarFallback></Avatar> : null}<span className="block min-w-0 flex-1 truncate">{teacher || t("unassigned")}</span>{additional ? <span className="text-[10px] text-muted-foreground">+{additional}</span> : null}</div></TableCell>
            <TableCell><span className="block max-w-28 truncate" title={record.labels.campus ?? ""}>{record.labels.campus}</span></TableCell>
            <TableCell className="text-center tabular-nums">{record.capacity}</TableCell>
            <TableCell><Badge variant="success">{t("active")}</Badge></TableCell>
          </TableRow>;
        })}</TableBody>
      </Table>}
      {total !== null && classes.length ? <p className="mt-4 text-xs text-muted-foreground">{t("showing")} {classes.length} {t("of")} {total} {t("activeClasses").toLowerCase()}</p> : null}
    </CardContent>
  </Card>;
}
