import { Suspense } from "react";
import { redirect } from "next/navigation";
import { AdminShell } from "@/components/admin-shell";
import { LoadingState } from "@/components/loading-state";
import { StudentFiltersForm } from "@/components/students/student-filters";
import { StudentFormDialog } from "@/components/students/student-form-dialog";
import { StudentTable } from "@/components/students/student-table";
import { requireAdmin } from "@/lib/auth";
import { studentListUrl } from "@/lib/student-types";
import { listStudents, parseStudentFilters, schoolToday } from "@/lib/students";

type SearchParams = Promise<Record<string, string | string[] | undefined>>;

async function Students({ searchParams }: { searchParams: SearchParams }) {
  const user = await requireAdmin();
  const filters = parseStudentFilters(await searchParams);
  const result = await listStudents(filters);
  if (filters.page > result.meta.last_page) redirect(studentListUrl(filters, result.meta.last_page));

  return (
    <AdminShell user={user} title="Students">
      <section className="flex flex-wrap items-start justify-between gap-4" aria-labelledby="students-heading">
        <div className="flex flex-col gap-2">
          <h2 id="students-heading" className="text-2xl font-semibold tracking-tight sm:text-3xl">Student management</h2>
          <p className="text-sm text-muted-foreground">Manage student profiles and enrollment details.</p>
        </div>
        <StudentFormDialog defaultDate={schoolToday()} />
      </section>
      <StudentFiltersForm filters={filters} />
      <StudentTable result={{ data: result.data, meta: result.meta }} filters={filters} />
    </AdminShell>
  );
}

export default function StudentsPage({ searchParams }: { searchParams: SearchParams }) {
  return <Suspense fallback={<LoadingState message="Loading students…" />}><Students searchParams={searchParams} /></Suspense>;
}
