import Link from "next/link";
import { Suspense } from "react";
import { notFound } from "next/navigation";
import { AdminShell } from "@/components/admin-shell";
import { LoadingState } from "@/components/loading-state";
import { EnrolmentTable } from "@/components/learning/learning-list";
import { Alert, AlertDescription } from "@/components/ui/alert";
import { Badge } from "@/components/ui/badge";
import { Button } from "@/components/ui/button";
import {
  Card,
  CardHeader,
  CardTitle,
  CardDescription,
  CardContent,
} from "@/components/ui/card";
import { authenticatedFetch, requirePermission } from "@/lib/auth";
import {
  statusLabel,
  type Learner,
  type Enrolment,
} from "@/lib/learning-types";
async function Content({ params }: { params: Promise<{ id: string }> }) {
  const user = await requirePermission("learning.view");
  const { id } = await params;
  if (!/^[1-9]\d{0,15}$/.test(id)) notFound();
  const response = await authenticatedFetch("/learning/students/" + id);
  if (response.status === 404) notFound();
  if (!response.ok) throw new Error("Unable to load the learning record.");
  const {
    data: s,
    enrolments,
    enrolment_total,
  }: {
    data: Learner;
    enrolments: Enrolment[];
    enrolment_total: number;
  } = await response.json();
  return (
    <AdminShell user={user} title="Learning records">
      <div>
        <Button
          variant="ghost"
          render={<Link href="/students/records" />}
          nativeButton={false}
        >
          Back to learning records
        </Button>
      </div>
      <section className="flex flex-col gap-3">
        <h2 className="text-2xl font-semibold">{s.full_name}</h2>
        <div className="flex flex-wrap gap-2">
          <Badge variant="outline">{s.student_number}</Badge>
          <Badge variant="secondary">{statusLabel(s.status)}</Badge>
        </div>
      </section>
      <Card>
        <CardHeader>
          <CardTitle>Student information</CardTitle>
          <CardDescription>
            Contact details follow your relationship and permissions.
          </CardDescription>
        </CardHeader>
        <CardContent>
          {s.contacts_visible ? (
            <dl className="grid gap-5 sm:grid-cols-2">
              {[
                ["Email", s.email ?? "Not provided"],
                ["Phone", s.phone ?? "Not provided"],
                ["Profile enrolment date", s.enrollment_date],
              ].map(([label, value]) => (
                <div key={label}>
                  <dt className="text-xs text-muted-foreground">{label}</dt>
                  <dd className="mt-1 break-words text-sm">{value}</dd>
                </div>
              ))}
            </dl>
          ) : (
            <Alert>
              <AlertDescription>
                Personal contact details are restricted. Your assigned class
                records are shown below.
              </AlertDescription>
            </Alert>
          )}
          <div className="mt-5 flex flex-wrap gap-2">
            {user.permissions.includes("students.manage") ? (
              <Button
                variant="outline"
                render={<Link href={"/students/" + id} />}
                nativeButton={false}
              >
                Edit student profile
              </Button>
            ) : null}
            {user.permissions.includes("links.manage") ? (
              <Button
                variant="outline"
                render={
                  <Link
                    href={
                      "/students/links?q=" +
                      encodeURIComponent(s.student_number)
                    }
                  />
                }
                nativeButton={false}
              >
                Manage account links
              </Button>
            ) : null}
          </div>
        </CardContent>
      </Card>
      <Card>
        <CardHeader>
          <CardTitle>Enrolment history</CardTitle>
          <CardDescription>
            {enrolment_total} placements available to your account. Completed
            and transferred records are retained.
          </CardDescription>
        </CardHeader>
        <CardContent>
          {enrolments.length ? (
            <EnrolmentTable records={enrolments} />
          ) : (
            <p className="text-sm text-muted-foreground">
              No enrolments available.
            </p>
          )}
          <div className="mt-5">
            <Button
              variant="outline"
              render={<Link href={"/students/enrolments?student_id=" + id} />}
              nativeButton={false}
            >
              View all enrolments
            </Button>
          </div>
        </CardContent>
      </Card>
    </AdminShell>
  );
}
export default function Page(props: { params: Promise<{ id: string }> }) {
  return (
    <Suspense fallback={<LoadingState message="Loading learning record…" />}>
      <Content {...props} />
    </Suspense>
  );
}
