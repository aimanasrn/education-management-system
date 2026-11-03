"use client";

import { useState, useTransition, type TransitionStartFunction } from "react";
import { PlusIcon } from "lucide-react";
import { saveEntity } from "@/app/foundation-actions";
import { RecordFields } from "@/components/record-fields";
import { Alert, AlertDescription } from "@/components/ui/alert";
import { AlertDialog, AlertDialogContent, AlertDialogHeader, AlertDialogTitle, AlertDialogDescription, AlertDialogFooter, AlertDialogCancel, AlertDialogAction } from "@/components/ui/alert-dialog";
import { Badge } from "@/components/ui/badge";
import { Button } from "@/components/ui/button";
import { Card, CardContent, CardHeader, CardTitle, CardDescription } from "@/components/ui/card";
import { Dialog, DialogContent, DialogHeader, DialogTitle, DialogDescription, DialogFooter } from "@/components/ui/dialog";
import { Empty, EmptyHeader, EmptyTitle, EmptyDescription } from "@/components/ui/empty";
import { ListFilters, type ListFilter } from "@/components/list-filters";
import { Pagination, PaginationContent, PaginationItem, PaginationLink, PaginationPrevious, PaginationNext } from "@/components/ui/pagination";
import { Spinner } from "@/components/ui/spinner";
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from "@/components/ui/table";
import { entityConfigs, entityPresentation, displayValue, entityUrl, fieldLabel, initialValues, type EntityKey, type EntityRecord, type EntityList, type Option, type SaveResult } from "@/lib/foundation-config";

export function EntityManager({ entity, result, options, filters, canCreate, canEdit, systemAdmin }: { entity: EntityKey; result: EntityList; options: Record<string, Option[]>; filters: Record<string, string>; canCreate: boolean; canEdit: boolean; systemAdmin: boolean }) {
  const config = entityPresentation(entity, filters.view);
  const [editing, setEditing] = useState<EntityRecord | null | undefined>();
  const [notice, setNotice] = useState("");
  const [detailsId, setDetailsId] = useState<number | null>(null);
  const details = result.data.find(record => record.id === detailsId) ?? null;
  const [pending, startTransition] = useTransition();
  const open = (record: EntityRecord | null) => { setNotice(""); setEditing(record); };
  const { meta } = result;
  const listFilters: ListFilter[] = entity === "users" ? [] : [
    { name: "status", label: "Status", defaultValue: "all", options: [{ value: "all", label: "All statuses" }, { value: "active", label: "Active" }, { value: "inactive", label: "Inactive" }] },
    ...(options.campus_id ? [{ name: "campus_id", label: "Campus", defaultValue: "", options: [{ value: "", label: "All available campuses" }, ...options.campus_id.map(option => ({ value: String(option.value), label: option.label }))] }] : []),
    { name: "sort", label: "Sort", defaultValue: "name", options: [{ value: "name", label: "Name A–Z" }, { value: "-name", label: "Name Z–A" }, { value: "newest", label: "Newest first" }] },
  ];
  return <>
    <section className="flex flex-wrap items-start justify-between gap-4">
      <div className="flex flex-col gap-2"><h2 className="text-2xl font-semibold tracking-tight sm:text-3xl">{config.title}</h2><p className="max-w-2xl text-sm text-muted-foreground">{config.description}</p></div>
      {canCreate ? <Button disabled={pending} onClick={() => open(null)}><PlusIcon data-icon="inline-start" />Add {config.singular}</Button> : null}
    </section>
    {notice ? <Alert role="status"><AlertDescription>{notice}</AlertDescription></Alert> : null}
    <Card>
      <CardHeader><CardTitle>Find records</CardTitle><CardDescription>Search within the records available to your account.</CardDescription></CardHeader>
      <CardContent><ListFilters path={config.path} filters={filters} selects={listFilters} placeholder={entity === "users" ? "Name or email" : "Name or code"} /></CardContent>
    </Card>
    <Card>
      <CardHeader><CardTitle>{meta.total.toLocaleString("en")} {meta.total === 1 ? "record" : "records"}</CardTitle><CardDescription>{meta.from ? "Showing " + meta.from + "–" + meta.to : "No matches"} · Page {meta.current_page} of {meta.last_page}</CardDescription></CardHeader>
      <CardContent>
        {result.data.length ? <Table><TableHeader><TableRow>{config.columns.map(column => <TableHead key={column}>{fieldLabel(entity, column)}</TableHead>)}<TableHead>Actions</TableHead></TableRow></TableHeader>
          <TableBody>{result.data.map(record => <TableRow key={record.id}>
            {config.columns.map(column => <TableCell key={column} className="max-w-60 whitespace-normal break-words">{column === "status" || column === "is_active" ? <Badge variant={record[column] === "active" || record[column] === true ? "secondary" : "outline"}>{displayValue(record, column)}</Badge> : displayValue(record, column)}</TableCell>)}
            <TableCell><div className="flex flex-wrap gap-2"><Button variant="outline" size="sm" disabled={pending} aria-label={"View " + record.name} onClick={() => setDetailsId(record.id)}>View</Button>{canEdit ? entity !== "users" || systemAdmin || !(record.roles as string[]).some(role => ["admin", "centre_admin"].includes(role)) ? <Button variant="outline" size="sm" disabled={pending} aria-label={"Edit " + record.name} onClick={() => open(record)}>Edit</Button> : <span className="text-xs text-muted-foreground">System administrator only</span> : null}</div></TableCell>
          </TableRow>)}</TableBody></Table> : <Empty><EmptyHeader><EmptyTitle>No records found</EmptyTitle><EmptyDescription>{canCreate ? "Add a record to get started, or adjust the filters." : "No records are assigned to your current access."}</EmptyDescription></EmptyHeader></Empty>}
        {meta.last_page > 1 ? <Pagination className="mt-6"><PaginationContent>
          {meta.current_page > 1 ? <PaginationItem><PaginationPrevious href={entityUrl(entity, filters, meta.current_page - 1)} /></PaginationItem> : null}
          <PaginationItem><PaginationLink isActive href={entityUrl(entity, filters, meta.current_page)}>{meta.current_page}</PaginationLink></PaginationItem>
          {meta.current_page < meta.last_page ? <PaginationItem><PaginationNext href={entityUrl(entity, filters, meta.current_page + 1)} /></PaginationItem> : null}
        </PaginationContent></Pagination> : null}
      </CardContent>
    </Card>
    {editing !== undefined ? <EntityEditor key={editing?.id ?? "new"} entity={entity} record={editing} options={options} pending={pending} startTransition={startTransition} onClose={() => setEditing(undefined)} onSuccess={() => { setEditing(undefined); setNotice("Record saved."); }} /> : null}
    <Dialog open={details !== null} onOpenChange={open => { if (!open) setDetailsId(null); }}><DialogContent className="max-h-[90svh] overflow-y-auto sm:max-w-xl"><DialogHeader><DialogTitle>{details?.name}</DialogTitle><DialogDescription>{config.title} · Record #{details?.id}</DialogDescription></DialogHeader>{details ? <dl className="flex flex-col gap-4">{config.fields.filter(field => field.key !== "password").map(field => <div key={field.key} className="flex flex-col gap-1"><dt className="text-sm font-medium">{field.label}</dt><dd className="text-sm whitespace-pre-wrap break-words text-muted-foreground">{displayValue(details, field.key)}</dd></div>)}</dl> : null}</DialogContent></Dialog>
  </>;
}

function EntityEditor({ entity, record, options, pending, startTransition, onClose, onSuccess }: { entity: EntityKey; record: EntityRecord | null; options: Record<string, Option[]>; pending: boolean; startTransition: TransitionStartFunction; onClose: () => void; onSuccess: () => void }) {
  const config = entityConfigs[entity];
  const [values, setValues] = useState(() => initialValues(entity, record ?? undefined));
  const [result, setResult] = useState<SaveResult>({});
  const [confirm, setConfirm] = useState(false);
  const submit = () => startTransition(async () => {
    setConfirm(false);
    const payload = { ...values };
    if (typeof payload.facilities === "string") payload.facilities = payload.facilities.split(",");
    if (Array.isArray(payload.facilities)) payload.facilities = payload.facilities.map(item => String(item).trim()).filter(Boolean);
    const response = await saveEntity(entity, record?.id ?? null, payload);
    setResult(response);
    if (response.success) onSuccess();
  });
  return <Dialog open onOpenChange={open => { if (!open && !pending) onClose(); }}>
    <DialogContent className="max-h-[90svh] overflow-y-auto sm:max-w-xl">
      <DialogHeader><DialogTitle>{record ? "Edit " : "Add "}{config.singular}</DialogTitle><DialogDescription>{entity === "users" ? "Roles and campuses control access. Changes are recorded in the audit log." : "Save details below. Deactivate records to preserve their history."}</DialogDescription></DialogHeader>
      <form className="flex flex-col gap-6" onSubmit={event => { event.preventDefault(); if (entity === "users" || (record?.status === "active" && values.status === "inactive")) setConfirm(true); else submit(); }} noValidate>
        {result.error ? <Alert variant="destructive" role="alert"><AlertDescription>{result.error}</AlertDescription></Alert> : null}
        <fieldset disabled={pending} className="min-w-0"><RecordFields fields={config.fields} values={values} options={options} errors={result.errors} editing={!!record} onChange={(key, value) => setValues(previous => {
          const next = { ...previous, [key]: value };
          if (key === "campus_id" && !record) { if ("building_id" in next) next.building_id = null; if ("room_id" in next) next.room_id = null; if ("teacher_ids" in next) next.teacher_ids = []; }
          if (key === "academic_year_id" && !record) next.academic_term_id = null;
          if (key === "programme_id" && !record && "subject_id" in next) next.subject_id = null;
          return next;
        })} /></fieldset>
        <DialogFooter><Button variant="outline" type="button" disabled={pending} onClick={onClose}>Cancel</Button><Button type="submit" disabled={pending}>{pending ? <Spinner data-icon="inline-start" /> : null}Save {config.singular}</Button></DialogFooter>
      </form>
      <AlertDialog open={confirm} onOpenChange={setConfirm}><AlertDialogContent><AlertDialogHeader><AlertDialogTitle>{entity === "users" ? "Save account access?" : "Deactivate this record?"}</AlertDialogTitle><AlertDialogDescription>{entity === "users" ? "Confirm the selected roles, campuses and account status. Access changes end this user’s existing sessions." : "This record will be marked inactive and its history retained."}</AlertDialogDescription></AlertDialogHeader><AlertDialogFooter><AlertDialogCancel>Go back</AlertDialogCancel><AlertDialogAction onClick={submit}>Confirm and save</AlertDialogAction></AlertDialogFooter></AlertDialogContent></AlertDialog>
    </DialogContent>
  </Dialog>;
}
