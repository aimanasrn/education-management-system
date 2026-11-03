"use server";

import { revalidatePath } from "next/cache";
import { unstable_rethrow } from "next/navigation";
import { authenticatedFetch, requirePermission } from "@/lib/auth";
import { entityConfigs, type EntityKey, type SaveResult } from "@/lib/foundation-config";

export async function saveEntity(key: EntityKey, id: number | null, values: Record<string, unknown>): Promise<SaveResult> {
  if (!Object.hasOwn(entityConfigs, key) || (id !== null && (!Number.isSafeInteger(id) || id < 1))) return { error: "Invalid record." };
  const config = entityConfigs[key];
  let response: Response;
  try {
    await requirePermission(key === "users" ? "users.manage" : config.permission.replace(".view", ".manage"));
    const path = key === "users" ? "/system/users" : "/foundation/" + key;
    response = await authenticatedFetch(path + (id ? "/" + id : ""), { method: id ? "PUT" : "POST", headers: { "Content-Type": "application/json" }, body: JSON.stringify(values) });
  } catch (error) {
    unstable_rethrow(error);
    return { error: "We couldn't connect to save this record. Your changes are still here." };
  }
  if (response.status === 422) {
    const payload: { message?: string; errors?: Record<string, string[]> } = await response.json();
    return { error: payload.errors ? "Please check the highlighted fields." : payload.message ?? "Unable to save.", errors: payload.errors };
  }
  if (response.status === 404) return { error: "This record is no longer available to your account." };
  if (!response.ok) return { error: "We couldn't save this record. Please try again." };
  revalidatePath(config.path);
  revalidatePath("/dashboard");
  revalidatePath("/system/audit");
  return { success: true };
}
