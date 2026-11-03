"use server";

import { revalidatePath } from "next/cache";
import { unstable_rethrow } from "next/navigation";
import { authenticatedFetch, requireAdmin } from "@/lib/auth";
import type { Student, StudentFormState, StudentValues } from "@/lib/student-types";

async function sendStudentRequest(path: string, init: RequestInit): Promise<Response | null> {
  try {
    await requireAdmin();
    return await authenticatedFetch(path, init);
  } catch (error) {
    unstable_rethrow(error);
    return null;
  }
}

function refreshStudentPages(id?: number) {
  revalidatePath("/students");
  revalidatePath("/dashboard");
  if (id) revalidatePath("/students/" + id);
}

export async function saveStudent(id: number | null, _previous: StudentFormState, form: FormData): Promise<StudentFormState> {
  const values: StudentValues = {
    student_number: String(form.get("student_number") ?? ""),
    full_name: String(form.get("full_name") ?? ""),
    email: String(form.get("email") ?? ""),
    phone: String(form.get("phone") ?? ""),
    enrollment_date: String(form.get("enrollment_date") ?? ""),
  };
  if (id !== null && (!Number.isSafeInteger(id) || id <= 0)) return { error: "Invalid student record.", values };

  const response = await sendStudentRequest(id ? "/students/" + id : "/students", {
    method: id ? "PUT" : "POST",
    headers: { "Content-Type": "application/json" },
    body: JSON.stringify(values),
  });
  if (!response) return { error: "We couldn't connect to save this student. Please try again.", values };
  if (response.status === 422) {
    const payload: { errors: StudentFormState["errors"] } = await response.json();
    return { error: "Please check the highlighted fields.", errors: payload.errors, values };
  }
  if (response.status === 409) return { error: "Restore this student before editing their record.", values };
  if (!response.ok) return { error: "We couldn't save this student. Please try again.", values };

  const payload: { data: Student } = await response.json();
  refreshStudentPages(payload.data.id);
  return { success: true };
}

export async function changeStudentArchive(id: number, operation: "archive" | "restore"): Promise<StudentFormState> {
  if (!Number.isSafeInteger(id) || id <= 0 || !["archive", "restore"].includes(operation)) return { error: "Invalid student action." };
  const response = await sendStudentRequest("/students/" + id + "/" + operation, { method: "POST" });
  if (!response?.ok) return { error: "We couldn't " + operation + " this student. Please try again." };
  refreshStudentPages(id);
  return { success: true };
}
