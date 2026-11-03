"use server";

import { unstable_rethrow } from "next/navigation";
import { authenticatedFetch, requireUser } from "@/lib/auth";
import { entityConfigs, type EntityKey } from "@/lib/foundation-config";
import { workspaceHref } from "@/lib/workspace-navigation";
import type { SearchMatch } from "@/lib/workspace-types";

export async function searchWorkspace(value: string, campusId: string): Promise<{ matches: SearchMatch[]; failed: boolean }> {
  if (typeof value !== "string" || typeof campusId !== "string") return { matches: [], failed: true };
  const query = value.trim().slice(0,100);
  if (query.length < 2) return { matches: [], failed: false };
  if (campusId && !/^[1-9]\d{0,15}$/.test(campusId)) return { matches: [], failed: true };
  const user = await requireUser();
  const resources: { key: EntityKey | "students" | "admissions" | "learningRecords"; path: string; permission: string }[] = [
    ...Object.entries(entityConfigs).map(([key, config]) => ({ key: key as EntityKey, path: key === "users" ? "/system/users" : "/foundation/" + key, permission: config.permission })),
    { key: "students", path: "/students", permission: "students.manage" },
    { key: "admissions", path: "/admissions", permission: "admissions.view" },
    ...(!user.permissions.includes("students.manage") ? [{key:"learningRecords" as const,path:"/learning/students",permission:"learning.view"}] : []),
  ];
  const responses = await Promise.all(resources.filter(resource => user.permissions.includes(resource.permission)).map(async resource => {
    const params = new URLSearchParams({ q: query, per_page: "3" });
    if (resource.key === "students") params.set("status", "all");
    if (campusId && ["rooms", "buildings", "closures", "classes"].includes(resource.key)) params.set("campus_id", campusId);
    try {
      const response = await authenticatedFetch(resource.path + "?" + params);
      if (!response.ok) return null;
      const payload: { data: { id: number; name?: string; full_name?: string; code?: string; student_number?: string; reference?:string }[] } = await response.json();
      return payload.data.slice(0,3).map(record => {
        const detail = ["students", "admissions", "learningRecords"].includes(resource.key);
        const path = resource.key === "students" ? "/students/" + record.id : resource.key === "admissions" ? "/students/admissions/" + record.id : resource.key === "learningRecords" ? "/students/records/" + record.id : entityConfigs[resource.key].path;
        const link = new URL(workspaceHref(path, campusId), "https://workspace.local");
        if (!detail) link.searchParams.set("q", record.code ?? record.name ?? query);
        return { id: resource.key + "-" + record.id, name: record.name ?? record.full_name ?? "", description: record.code ?? record.student_number ?? record.reference ?? "", href: link.pathname + link.search, module: resource.key };
      });
    } catch (error) {
      unstable_rethrow(error);
      return null;
    }
  }));
  return { matches: responses.flatMap(response => response ?? []), failed: responses.some(response => response === null) };
}
