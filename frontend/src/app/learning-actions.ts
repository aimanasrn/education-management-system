"use server";
import { revalidatePath } from "next/cache";
import { unstable_rethrow } from "next/navigation";
import { authenticatedFetch, requireUser } from "@/lib/auth";
import type { LearningOperation, LearningResult } from "@/lib/learning-types";

async function result(response: Response): Promise<LearningResult> {
  const body = await response.json().catch(() => ({}));
  if (!response.ok)
    return {
      error: body.message ?? "The request could not be completed. Try again.",
      errors: body.errors,
    };
  for (const path of [
    "/students/admissions",
    "/students/enrolments",
    "/students/records",
    "/students/links",
    "/students",
    "/dashboard",
    "/system/audit",
    "/campuses/bookings",
  ])
    revalidatePath(path);
  revalidatePath("/students/admissions/[id]", "page");
  revalidatePath("/students/records/[id]", "page");
  revalidatePath("/campuses/bookings/[id]", "page");
  return { success: true, id: body.data?.id };
}
export async function learningMutation(
  operation: LearningOperation,
  id: number | null,
  input: Record<string, unknown>,
): Promise<LearningResult> {
  const user = await requireUser();
  if (id !== null && (!Number.isSafeInteger(id) || id < 1))
    return { error: "Invalid record." };
  const permission =
    operation === "booking-save" || operation === "booking-transition"
      ? "bookings.request"
      : operation === "enrol" || operation === "enrolment-change"
        ? "enrolments.manage"
        : operation === "link"
          ? "links.manage"
          : "admissions.view";
  if (!user.permissions.includes(permission))
    return { error: "You do not have access to this action." };
  let path: string;
  let method = "POST";
  const data = { ...input };
  switch (operation) {
    case "booking-save":
      path = "/bookings" + (id ? "/" + id : "");
      method = id ? "PUT" : "POST";
      data.kind = data.booking_type;
      delete data.booking_type;
      break;
    case "booking-transition":
      if (!id) return { error: "Invalid booking." };
      path = "/bookings/" + id + "/transition";
      break;
    case "admission-save":
      path = "/admissions" + (id ? "/" + id : "");
      method = id ? "PUT" : "POST";
      break;
    case "admission-transition":
      if (!id) return { error: "Invalid application." };
      path = "/admissions/" + id + "/transition";
      break;
    case "document-verify":
      if (
        !id ||
        !Number.isSafeInteger(data.document_id) ||
        Number(data.document_id) < 1
      )
        return { error: "Invalid document." };
      path = "/admissions/" + id + "/documents/" + data.document_id + "/verify";
      delete data.document_id;
      break;
    case "enrol":
      path = "/learning/enrolments";
      break;
    case "enrolment-change":
      if (!id) return { error: "Invalid enrolment." };
      path = "/learning/enrolments/" + id + "/transition";
      data.action = data.change_type;
      delete data.change_type;
      break;
    case "link":
      path = "/learning/links";
      data.kind = data.link_type;
      delete data.link_type;
      break;
    default:
      return { error: "Unknown action." };
  }
  try {
    return await result(
      await authenticatedFetch(path, {
        method,
        headers: { "Content-Type": "application/json" },
        body: JSON.stringify(data),
      }),
    );
  } catch (error) {
    unstable_rethrow(error);
    return {
      error:
        "The server is unavailable. Your entered values have been kept. Try again.",
    };
  }
}
export async function uploadAdmissionDocument(
  id: number,
  form: FormData,
): Promise<LearningResult> {
  await requireUser();
  if (!Number.isSafeInteger(id) || id < 1)
    return { error: "Invalid application." };
  const file = form.get("file");
  if (!(file instanceof File) || file.size === 0 || file.size > 5 * 1024 * 1024)
    return { error: "Choose a PDF, JPEG or PNG file up to 5 MB." };
  const data = new FormData();
  data.set("file", file);
  try {
    return await result(
      await authenticatedFetch("/admissions/" + id + "/documents", {
        method: "POST",
        body: data,
      }),
    );
  } catch (error) {
    unstable_rethrow(error);
    return { error: "The upload could not be completed. Try again." };
  }
}
