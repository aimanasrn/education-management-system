"use client";
import Link from "next/link";
import { LearningForm } from "@/components/learning/form-dialog";
import { ListFilters, type ListFilter } from "@/components/list-filters";
import { useWorkspace } from "@/components/layout/workspace-context";
import { Badge } from "@/components/ui/badge";
import { Button } from "@/components/ui/button";
import {
  Card,
  CardHeader,
  CardTitle,
  CardDescription,
  CardContent,
} from "@/components/ui/card";
import {
  Empty,
  EmptyHeader,
  EmptyTitle,
  EmptyDescription,
} from "@/components/ui/empty";
import {
  Pagination,
  PaginationContent,
  PaginationItem,
  PaginationNext,
  PaginationPrevious,
} from "@/components/ui/pagination";
import {
  Table,
  TableHeader,
  TableHead,
  TableRow,
  TableBody,
  TableCell,
} from "@/components/ui/table";
import {
  learningModules,
  statusLabel,
  type LearningResource,
  type LearningList as List,
  type LearningOptions,
  type Application,
  type Learner,
  type Enrolment,
} from "@/lib/learning-types";
import type { FieldSpec } from "@/lib/foundation-config";
export const applicationFields: FieldSpec[] = [
  { key: "full_name", label: "Student full name" },
  { key: "email", label: "Contact email", type: "email", optional: true },
  { key: "phone", label: "Contact phone", type: "tel", optional: true },
  { key: "campus_id", label: "Campus", type: "select" },
  { key: "programme_id", label: "Programme", type: "select" },
  { key: "requested_start", label: "Requested start", type: "date" },
];
export const enrolmentFields: FieldSpec[] = [
  { key: "student_id", label: "Student", type: "select" },
  { key: "school_class_id", label: "Class", type: "select" },
  { key: "starts_on", label: "First day", type: "date" },
  { key: "ends_on", label: "Last day", type: "date" },
];
const linkFields: FieldSpec[] = [
  { key: "student_id", label: "Student", type: "select" },
  { key: "link_type", label: "Link type", type: "select" },
  { key: "user_id", label: "Login account", type: "select" },
  { key: "relationship", label: "Guardian relationship" },
  { key: "emergency_contact", label: "Emergency contact", type: "boolean" },
  { key: "status", label: "Access status", type: "select" },
];
export function LearningTable({
  resource,
  result,
  options,
  filters,
  today,
}: {
  resource: LearningResource;
  result: List;
  options: LearningOptions;
  filters: Record<string, string>;
  today: string;
}) {
  const { user, chrome } = useWorkspace();
  const config = learningModules[resource];
  const manager = user.permissions.includes("enrolments.manage");
  const selects: ListFilter[] =
    resource === "admissions" || resource === "enrolments"
      ? [
          {
            name: "status",
            label: "Status",
            defaultValue: "all",
            options: [
              "all",
              ...(resource === "admissions"
                ? [
                    "draft",
                    "submitted",
                    "under_review",
                    "approved",
                    "rejected",
                    "withdrawn",
                  ]
                : ["active", "completed", "withdrawn", "transferred"]),
            ].map((value) => ({ value, label: statusLabel(value) })),
          },
          ...(chrome.campuses.length
            ? [
                {
                  name: "campus_id",
                  label: "Campus",
                  defaultValue: "",
                  options: [
                    { value: "", label: "All available campuses" },
                    ...chrome.campuses.map((c) => ({
                      value: String(c.id),
                      label: c.label,
                    })),
                  ],
                },
              ]
            : []),
        ]
      : [];
  const page = (n: number) =>
    config.path + "?" + new URLSearchParams({ ...filters, page: String(n) });
  const linkedOptions = {
    ...options,
    link_type: [
      { value: "guardian", label: "Guardian / child" },
      { value: "student", label: "Student self access" },
    ],
  };
  return (
    <>
      <section className="flex flex-wrap items-start justify-between gap-4">
        <div className="flex flex-col gap-2">
          <h2 className="text-2xl font-semibold tracking-tight">
            {config.title}
          </h2>
          <p className="max-w-2xl text-sm text-muted-foreground">
            {config.description}
          </p>
        </div>
        {resource === "admissions" ? (
          <LearningForm
            title="New application"
            description="Save a draft, attach supporting documents, then submit it for review."
            operation="admission-save"
            initial={{
              full_name: "",
              email: "",
              phone: "",
              campus_id: "",
              programme_id: "",
              requested_start: today,
            }}
            fields={applicationFields}
            options={options}
            redirectTo="/students/admissions/"
          />
        ) : resource === "enrolments" && manager ? (
          <LearningForm
            title="Enrol student"
            description="Choose a class and dates within its term. Capacity and overlapping programme placements are checked before saving."
            operation="enrol"
            initial={{
              student_id: filters.student_id ?? "",
              school_class_id: "",
              starts_on: today,
              ends_on: today,
            }}
            fields={enrolmentFields}
            options={options}
          />
        ) : resource === "links" ? (
          <LinkForm options={linkedOptions} />
        ) : null}
      </section>
      <Card>
        <CardHeader>
          <CardTitle>Find records</CardTitle>
          <CardDescription>
            Results follow your current account access.
          </CardDescription>
        </CardHeader>
        <CardContent>
          <ListFilters
            path={config.path}
            filters={filters}
            selects={selects}
            placeholder={
              resource === "admissions"
                ? "Student name or application reference"
                : "Student name, number" +
                  (resource === "enrolments" ? " or class" : "")
            }
          />
        </CardContent>
      </Card>
      <Card>
        <CardHeader>
          <CardTitle>{result.meta.total} records</CardTitle>
          <CardDescription>
            Page {result.meta.current_page} of {result.meta.last_page}
          </CardDescription>
        </CardHeader>
        <CardContent>
          {result.data.length ? (
            resource === "admissions" ? (
              <Table>
                <TableHeader>
                  <TableRow>
                    {[
                      "Applicant",
                      "Campus / programme",
                      "Requested start",
                      "Status",
                      "Actions",
                    ].map((t) => (
                      <TableHead key={t}>{t}</TableHead>
                    ))}
                  </TableRow>
                </TableHeader>
                <TableBody>
                  {(result.data as Application[]).map((a) => (
                    <TableRow key={a.id}>
                      <TableCell className="whitespace-normal">
                        <p>{a.full_name}</p>
                        <p className="text-xs text-muted-foreground">
                          {a.reference}
                        </p>
                      </TableCell>
                      <TableCell className="whitespace-normal">
                        <p>{a.campus}</p>
                        <p className="text-xs text-muted-foreground">
                          {a.programme}
                        </p>
                      </TableCell>
                      <TableCell>{a.requested_start}</TableCell>
                      <TableCell>
                        <Badge variant="secondary">
                          {statusLabel(a.status)}
                        </Badge>
                      </TableCell>
                      <TableCell>
                        <Button
                          variant="outline"
                          size="sm"
                          render={
                            <Link href={"/students/admissions/" + a.id} />
                          }
                          nativeButton={false}
                        >
                          Open application
                        </Button>
                      </TableCell>
                    </TableRow>
                  ))}
                </TableBody>
              </Table>
            ) : resource === "enrolments" ? (
              <EnrolmentTable
                records={result.data as Enrolment[]}
                options={options}
                manager={manager}
              />
            ) : (
              <Table>
                <TableHeader>
                  <TableRow>
                    {[
                      "Student",
                      ...(resource === "links"
                        ? ["Self account", "Guardian access"]
                        : ["Status"]),
                      "Actions",
                    ].map((t) => (
                      <TableHead key={t}>{t}</TableHead>
                    ))}
                  </TableRow>
                </TableHeader>
                <TableBody>
                  {(result.data as Learner[]).map((s) => (
                    <TableRow key={s.id}>
                      <TableCell className="whitespace-normal">
                        <p>{s.full_name}</p>
                        <p className="text-xs text-muted-foreground">
                          {s.student_number}
                        </p>
                      </TableCell>
                      {resource === "links" ? (
                        <>
                          <TableCell className="whitespace-normal">
                            {s.account?.name ?? "Not linked"}
                          </TableCell>
                          <TableCell className="whitespace-normal">
                            {s.guardians.map((g) => (
                              <p key={g.user_id}>
                                {g.name} · {g.relationship} ·{" "}
                                {statusLabel(g.status)}
                                {g.emergency_contact
                                  ? " · Emergency contact"
                                  : ""}
                              </p>
                            ))}
                            {!s.guardians.length ? "Not linked" : null}
                          </TableCell>
                        </>
                      ) : (
                        <TableCell>
                          <Badge variant="outline">
                            {statusLabel(s.status)}
                          </Badge>
                        </TableCell>
                      )}
                      <TableCell>
                        <Button
                          variant="outline"
                          size="sm"
                          render={<Link href={"/students/records/" + s.id} />}
                          nativeButton={false}
                        >
                          View record
                        </Button>
                      </TableCell>
                    </TableRow>
                  ))}
                </TableBody>
              </Table>
            )
          ) : (
            <Empty>
              <EmptyHeader>
                <EmptyTitle>No records found</EmptyTitle>
                <EmptyDescription>
                  Adjust the filters or ask your administrator to set up the
                  relevant application, enrolment or account link.
                </EmptyDescription>
              </EmptyHeader>
            </Empty>
          )}
          {result.meta.last_page > 1 ? (
            <Pagination className="mt-5">
              <PaginationContent>
                {result.meta.current_page > 1 ? (
                  <PaginationItem>
                    <PaginationPrevious
                      href={page(result.meta.current_page - 1)}
                    />
                  </PaginationItem>
                ) : null}
                {result.meta.current_page < result.meta.last_page ? (
                  <PaginationItem>
                    <PaginationNext href={page(result.meta.current_page + 1)} />
                  </PaginationItem>
                ) : null}
              </PaginationContent>
            </Pagination>
          ) : null}
        </CardContent>
      </Card>
    </>
  );
}
function LinkForm({ options }: { options: LearningOptions }) {
  // Link type selects its matching role-specific accounts inside the form.
  return (
    <LearningForm
      title="Link account"
      description="Confirm the person's identity and authority before granting access to a student. Only one active self link is allowed. To revoke access, save the same pair as inactive. Revoked self links retain their history."
      operation="link"
      initial={{
        student_id: "",
        link_type: "guardian",
        user_id: "",
        relationship: "Parent",
        emergency_contact: false,
        status: "active",
      }}
      fields={linkFields}
      options={options}
    />
  );
}
export function EnrolmentTable({
  records,
  options = {},
  manager = false,
}: {
  records: Enrolment[];
  options?: LearningOptions;
  manager?: boolean;
}) {
  const changeFields: FieldSpec[] = [
    { key: "change_type", label: "Action", type: "select" },
    { key: "effective_on", label: "Effective date", type: "date" },
    { key: "school_class_id", label: "Destination class", type: "select" },
    { key: "ends_on", label: "Destination last day", type: "date" },
    { key: "reason", label: "Reason", type: "textarea" },
  ];
  return (
    <Table>
      <TableHeader>
        <TableRow>
          {[
            "Student",
            "Class / campus",
            "Programme / term",
            "Period",
            "Status",
            "History",
            ...(manager ? ["Actions"] : []),
          ].map((t) => (
            <TableHead key={t}>{t}</TableHead>
          ))}
        </TableRow>
      </TableHeader>
      <TableBody>
        {records.map((e) => (
          <TableRow key={e.id}>
            <TableCell className="whitespace-normal">
              <Link
                href={"/students/records/" + e.student_id}
                className="font-medium text-primary hover:underline"
              >
                {e.student}
              </Link>
              <p className="text-xs text-muted-foreground">
                {e.student_number}
              </p>
            </TableCell>
            <TableCell className="whitespace-normal">
              {e.class}
              <p className="text-xs text-muted-foreground">{e.campus}</p>
            </TableCell>
            <TableCell className="whitespace-normal">
              {e.programme}
              <p className="text-xs text-muted-foreground">{e.term}</p>
            </TableCell>
            <TableCell>
              {e.starts_on}
              <br />
              {e.ends_on}
            </TableCell>
            <TableCell>
              <Badge variant="secondary">{statusLabel(e.status)}</Badge>
            </TableCell>
            <TableCell className="max-w-60 whitespace-normal">
              {e.previous_enrolment_id
                ? "Continues #" + e.previous_enrolment_id
                : "Original placement"}
              {e.end_reason ? (
                <p className="text-xs text-muted-foreground">{e.end_reason}</p>
              ) : null}
            </TableCell>
            {manager ? (
              <TableCell>
                {e.status === "active" ? (
                  <LearningForm
                    title="Change enrolment"
                    description="Transfers keep the previous placement through the day before the effective date. Completed or withdrawn placements include the effective date. Changes require a reason."
                    operation="enrolment-change"
                    id={e.id}
                    initial={{
                      change_type: "transfer",
                      effective_on: e.starts_on,
                      school_class_id: "",
                      ends_on: e.ends_on,
                      reason: "",
                    }}
                    fields={changeFields}
                    options={{
                      ...options,
                      change_type: [
                        {
                          value: "transfer",
                          label: "Transfer to another class",
                        },
                        { value: "complete", label: "Complete enrolment" },
                        { value: "withdraw", label: "Withdraw from enrolment" },
                      ],
                    }}
                  />
                ) : null}
              </TableCell>
            ) : null}
          </TableRow>
        ))}
      </TableBody>
    </Table>
  );
}
