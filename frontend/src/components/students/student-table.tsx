import Link from "next/link";
import { ChevronLeftIcon, ChevronRightIcon } from "lucide-react";
import { Badge } from "@/components/ui/badge";
import { Button } from "@/components/ui/button";
import { Card, CardContent, CardDescription, CardFooter, CardHeader, CardTitle } from "@/components/ui/card";
import { Empty, EmptyDescription, EmptyHeader, EmptyTitle } from "@/components/ui/empty";
import { Pagination, PaginationContent, PaginationItem, PaginationNext, PaginationPrevious } from "@/components/ui/pagination";
import { Table, TableBody, TableCaption, TableCell, TableHead, TableHeader, TableRow } from "@/components/ui/table";
import { formatEnrollmentDate, studentListUrl, type StudentFilters, type StudentList } from "@/lib/student-types";

export function StudentTable({ result, filters }: { result: StudentList; filters: StudentFilters }) {
  const { data, meta } = result;

  return (
    <Card>
      <CardHeader>
        <CardTitle role="heading" aria-level={3}>Student records</CardTitle>
        <CardDescription>{meta.total.toLocaleString("en")} {meta.total === 1 ? "student" : "students"} found</CardDescription>
      </CardHeader>
      <CardContent>
        {data.length === 0 ? (
          <Empty>
            <EmptyHeader>
              <EmptyTitle>No students found</EmptyTitle>
              <EmptyDescription>{filters.q || filters.status !== "active" ? "Try another search or status filter." : "Add your first student to get started."}</EmptyDescription>
            </EmptyHeader>
          </Empty>
        ) : (
          <Table>
            <TableCaption className="sr-only">Student records matching the current search and status filters.</TableCaption>
            <TableHeader><TableRow>
              <TableHead>Student</TableHead>
              <TableHead className="hidden lg:table-cell">Email</TableHead>
              <TableHead className="hidden md:table-cell">Enrollment date</TableHead>
              <TableHead>Status</TableHead>
              <TableHead><span className="sr-only">Actions</span></TableHead>
            </TableRow></TableHeader>
            <TableBody>
              {data.map(student => (
                <TableRow key={student.id}>
                  <TableCell>
                    <div className="flex max-w-44 flex-col gap-1 sm:max-w-72">
                      <span className="truncate font-medium" title={student.full_name}>{student.full_name}</span>
                      <span className="text-xs text-muted-foreground">{student.student_number}</span>
                    </div>
                  </TableCell>
                  <TableCell className="hidden lg:table-cell"><span className="block max-w-56 truncate">{student.email ?? "Not provided"}</span></TableCell>
                  <TableCell className="hidden md:table-cell">{formatEnrollmentDate(student.enrollment_date)}</TableCell>
                  <TableCell><Badge variant={student.status === "active" ? "secondary" : "outline"}>{student.status === "active" ? "Active" : "Archived"}</Badge></TableCell>
                  <TableCell>
                    <Button variant="ghost" size="sm" nativeButton={false}
                      render={<Link href={"/students/" + student.id} />} aria-label={"View " + student.full_name}>View</Button>
                  </TableCell>
                </TableRow>
              ))}
            </TableBody>
          </Table>
        )}
      </CardContent>
      <CardFooter className="flex flex-col items-start gap-3 sm:flex-row sm:items-center sm:justify-between">
        <p className="shrink-0 text-xs text-muted-foreground">Showing {meta.from ?? 0}–{meta.to ?? 0} of {meta.total}</p>
        <Pagination className="mx-0 w-auto" aria-label="Student pagination">
          <PaginationContent>
            <PaginationItem>
              {meta.current_page > 1 ? <PaginationPrevious href={studentListUrl(filters, meta.current_page - 1)} /> :
                <Button variant="ghost" disabled aria-label="Go to previous page"><ChevronLeftIcon data-icon="inline-start" /><span className="hidden sm:block">Previous</span></Button>}
            </PaginationItem>
            <PaginationItem><span className="px-2 text-xs text-muted-foreground">Page {meta.current_page} of {meta.last_page}</span></PaginationItem>
            <PaginationItem>
              {meta.current_page < meta.last_page ? <PaginationNext href={studentListUrl(filters, meta.current_page + 1)} /> :
                <Button variant="ghost" disabled aria-label="Go to next page"><span className="hidden sm:block">Next</span><ChevronRightIcon data-icon="inline-end" /></Button>}
            </PaginationItem>
          </PaginationContent>
        </Pagination>
      </CardFooter>
    </Card>
  );
}
