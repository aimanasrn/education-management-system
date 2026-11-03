import "server-only";

import { notFound } from "next/navigation";
import { authenticatedFetch } from "@/lib/auth";
import type { Student, StudentFilters, StudentList } from "@/lib/student-types";

export function parseStudentFilters(params: Record<string, string | string[] | undefined>): StudentFilters {
  const q = typeof params.q === "string" ? params.q.trim().slice(0, 100) : "";
  const status = params.status === "archived" || params.status === "all" ? params.status : "active";
  const page = typeof params.page === "string" && /^\d+$/.test(params.page) ? Number(params.page) : 1;
  return { q, status, page: Number.isSafeInteger(page) && page > 0 && page <= 100000 ? page : 1 };
}

export async function listStudents(filters: StudentFilters): Promise<StudentList> {
  const params = new URLSearchParams({ q: filters.q, status: filters.status, page: String(filters.page) });
  const response = await authenticatedFetch("/students?" + params);
  if (!response.ok) throw new Error("Unable to load student records.");
  return response.json();
}

export async function getStudent(id: string): Promise<Student> {
  if (!/^[1-9]\d*$/.test(id) || !Number.isSafeInteger(Number(id))) notFound();
  const response = await authenticatedFetch("/students/" + id);
  if (response.status === 404) notFound();
  if (!response.ok) throw new Error("Unable to load the student profile.");
  const payload: { data: Student } = await response.json();
  return payload.data;
}

export function schoolToday() {
  return new Intl.DateTimeFormat("en-CA", {
    timeZone: "Asia/Kuala_Lumpur", year: "numeric", month: "2-digit", day: "2-digit",
  }).format(new Date());
}
