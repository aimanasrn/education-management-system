import "server-only";

import { cookies } from "next/headers";
import { redirect } from "next/navigation";
import { cache } from "react";
import { laravelFetch } from "@/lib/api";

export const SESSION_COOKIE = "ems_session";

export type User = {
  id: number;
  name: string;
  email: string;
  role: string;
  roles: string[];
  role_labels: string[];
  permissions: string[];
  campus_ids: number[];
  campus_names: string[];
  is_active: boolean;
};

export async function sessionToken() {
  return (await cookies()).get(SESSION_COOKIE)?.value;
}

export const currentUser = cache(async (): Promise<User | null> => {
  const token = await sessionToken();

  if (!token) return null;

  const response = await laravelFetch("/user", {
    headers: { Authorization: `Bearer ${token}` },
  });

  if (response.status === 401) return null;
  if (!response.ok) throw new Error("Unable to verify your school account.");

  const payload: { data: User } = await response.json();
  return payload.data;
});

export async function requireUser() {
  const user = await currentUser();
  if (!user) redirect("/login");
  return user;
}

export async function requireAdmin() {
  const user = await requireUser();
  if (!user.permissions.includes("students.manage")) redirect("/access-denied");
  return user;
}

export async function requirePermission(permission: string) {
  const user = await requireUser();
  if (!user.permissions.includes(permission)) redirect("/access-denied");
  return user;
}

export async function authenticatedFetch(path: string, init: RequestInit = {}) {
  const response = await laravelFetch(path, {
    ...init,
    headers: { ...init.headers, Authorization: `Bearer ${await sessionToken()}` },
  });
  if (response.status === 401) redirect("/login");
  if (response.status === 403) redirect("/access-denied");
  return response;
}

export async function adminOverview() {
  const user = await requireAdmin();

  const response = await laravelFetch("/admin/dashboard", {
    headers: { Authorization: `Bearer ${await sessionToken()}` },
  });

  if (response.status === 401) redirect("/login");
  if (response.status === 403) redirect("/access-denied");
  if (!response.ok) throw new Error("Unable to load the school overview.");

  const payload: {
    data: { administrators: number; teachers: number; students: number; student_records: number };
  } = await response.json();

  return { user, counts: payload.data };
}
