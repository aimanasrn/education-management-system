import Link from "next/link";
import { Suspense } from "react";
import { AdminShell } from "@/components/admin-shell";
import { LoadingState } from "@/components/loading-state";
import { StudentArchiveAction } from "@/components/students/student-archive-action";
import { StudentFormDialog } from "@/components/students/student-form-dialog";
import { Alert, AlertDescription, AlertTitle } from "@/components/ui/alert";
import { Badge } from "@/components/ui/badge";
import { Button } from "@/components/ui/button";
import { Card, CardContent, CardHeader, CardTitle } from "@/components/ui/card";
import { requireAdmin } from "@/lib/auth";
import { formatEnrollmentDate } from "@/lib/student-types";
import { getStudent } from "@/lib/students";

async function Profile({ params }: { params: Promise<{ id: string }> }) {
  const user = await requireAdmin();
  const { id } = await params;
  const student = await getStudent(id);
  const fields = [
    ["Student number", student.student_number],
    ["Full name", student.full_name],
    ["Email", student.email ?? "Not provided"],
    ["Phone", student.phone ?? "Not provided"],
    ["Enrollment date", formatEnrollmentDate(student.enrollment_date)],
    ["Status", student.status === "active" ? "Active" : "Archived"],
  ];

  return (
    <AdminShell user={user} title="Student profile">
      <div><Button variant="ghost" size="sm" nativeButton={false}
        render={<Link href={student.status === "archived" ? "/students?status=archived" : "/students"} />}>Back to students</Button></div>
      <section className="flex flex-wrap items-start justify-between gap-4" aria-labelledby="profile-heading">
        <div className="flex min-w-0 flex-col gap-3">
          <h2 id="profile-heading" className="text-2xl font-semibold tracking-tight break-words sm:text-3xl">{student.full_name}</h2>
          <div className="flex flex-wrap gap-2"><Badge variant="outline">{student.student_number}</Badge><Badge variant="secondary">{student.status === "active" ? "Active" : "Archived"}</Badge></div>
        </div>
        <div className="flex flex-wrap gap-2">
          <Button variant="outline" nativeButton={false} render={<Link href={"/students/records/" + id} />}>Enrolments and linked access</Button>
          {student.status === "active" ? <StudentFormDialog student={student} defaultDate={student.enrollment_date} /> : null}
          <StudentArchiveAction student={student} />
        </div>
      </section>
      {student.status === "archived" ? <Alert><AlertTitle>Archived record</AlertTitle><AlertDescription>Restore this student to make changes.</AlertDescription></Alert> : null}
      <Card>
        <CardHeader><CardTitle role="heading" aria-level={3}>Student details</CardTitle></CardHeader>
        <CardContent>
          <dl className="grid gap-6 sm:grid-cols-2">
            {fields.map(([label, value]) => <div key={label} className="flex min-w-0 flex-col gap-1">
              <dt className="text-sm text-muted-foreground">{label}</dt><dd className="text-sm break-words">{value}</dd>
            </div>)}
          </dl>
        </CardContent>
      </Card>
    </AdminShell>
  );
}

export default function StudentProfilePage({ params }: { params: Promise<{ id: string }> }) {
  return <Suspense fallback={<LoadingState message="Loading student profile…" />}><Profile params={params} /></Suspense>;
}
