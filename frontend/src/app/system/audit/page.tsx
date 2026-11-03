import { Suspense } from "react";
import { AdminShell } from "@/components/admin-shell";
import { LoadingState } from "@/components/loading-state";
import { Card, CardContent, CardHeader, CardTitle, CardDescription } from "@/components/ui/card";
import { Empty, EmptyHeader, EmptyTitle, EmptyDescription } from "@/components/ui/empty";
import { Pagination, PaginationContent, PaginationItem, PaginationLink, PaginationPrevious, PaginationNext } from "@/components/ui/pagination";
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from "@/components/ui/table";
import { authenticatedFetch, requirePermission } from "@/lib/auth";
import type { FoundationSearchParams } from "@/components/foundation-page";

type Audit = { id: number; actor: string; subject_type: string; subject_id: number; action: string; changed_fields: string[]; created_at: string };
async function AuditContent({ searchParams }: { searchParams: FoundationSearchParams }) {
  const user = await requirePermission("audit.view");
  const params = await searchParams;
  const page = typeof params.page === "string" && /^[1-9]\d*$/.test(params.page) && Number(params.page) <= 100000 ? Number(params.page) : 1;
  const response = await authenticatedFetch("/system/audit?page=" + page);
  if (!response.ok) throw new Error("Unable to load the audit log.");
  const result: { data: Audit[]; current_page: number; last_page: number; total: number } = await response.json();
  const date = new Intl.DateTimeFormat("en-MY", { timeZone: "Asia/Kuala_Lumpur", dateStyle: "medium", timeStyle: "short" });
  return <AdminShell user={user} title="Audit log"><section className="flex flex-col gap-2"><h2 className="text-2xl font-semibold tracking-tight sm:text-3xl">Audit log</h2><p className="text-sm text-muted-foreground">Foundation, admissions, enrolment and account changes. Times shown in Kuala Lumpur.</p></section><Card><CardHeader><CardTitle>{result.total.toLocaleString("en")} events</CardTitle><CardDescription>Changed field names are recorded. Passwords, tokens and field values are excluded.</CardDescription></CardHeader><CardContent>
    {result.data.length ? <Table><TableHeader><TableRow>{["Time", "Actor", "Record", "Action", "Changed fields"].map(label => <TableHead key={label}>{label}</TableHead>)}</TableRow></TableHeader><TableBody>{result.data.map(log => <TableRow key={log.id}><TableCell className="whitespace-normal">{date.format(new Date(log.created_at))}</TableCell><TableCell>{log.actor}</TableCell><TableCell>{log.subject_type} #{log.subject_id}</TableCell><TableCell>{log.action.replaceAll("_", " ")}</TableCell><TableCell className="max-w-72 whitespace-normal">{log.changed_fields.join(", ")}</TableCell></TableRow>)}</TableBody></Table> : <Empty><EmptyHeader><EmptyTitle>No changes recorded</EmptyTitle><EmptyDescription>New foundation, admissions, enrolment and account changes will appear here.</EmptyDescription></EmptyHeader></Empty>}
    {result.last_page > 1 ? <Pagination className="mt-6"><PaginationContent>{page > 1 ? <PaginationItem><PaginationPrevious href={"/system/audit?page=" + (page - 1)} /></PaginationItem> : null}<PaginationItem><PaginationLink isActive href={"/system/audit?page=" + page}>{page}</PaginationLink></PaginationItem>{page < result.last_page ? <PaginationItem><PaginationNext href={"/system/audit?page=" + (page + 1)} /></PaginationItem> : null}</PaginationContent></Pagination> : null}
  </CardContent></Card></AdminShell>;
}
export default function AuditPage(props: { searchParams: FoundationSearchParams }) {
  return <Suspense fallback={<LoadingState message="Loading audit events…" />}><AuditContent {...props} /></Suspense>;
}
