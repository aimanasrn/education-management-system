import "server-only";

import { cache } from "react";
import { notFound, unstable_rethrow } from "next/navigation";
import { authenticatedFetch, type User } from "@/lib/auth";
import type { Activity, Campus, CampusChoice, CampusSummary, ClassSummary, Overview, WorkspaceChrome, WorkspaceDashboard } from "@/lib/workspace-types";

type Collection<T> = { data: T[]; meta: { total: number; current_page: number; last_page: number } };

async function optional<T>(path: string): Promise<T | null> {
  try {
    const response = await authenticatedFetch(path);
    if (!response.ok) return null;
    return await response.json() as T;
  } catch (error) {
    unstable_rethrow(error);
    return null;
  }
}

export const getWorkspaceChrome = cache(async (user: User): Promise<WorkspaceChrome> => {
  const canCampus = user.permissions.includes("campuses.view");
  const canAudit = user.permissions.includes("audit.view");
  const [overview, options, activity] = await Promise.all([
    optional<{ data: Overview }>("/dashboard"),
    canCampus ? optional<{ data: { campus_id: { value: number; label: string }[] } }>("/foundation/buildings/options") : null,
    canAudit ? optional<{ data: Activity[] }>("/system/audit") : null,
  ]);
  const campuses: CampusChoice[] = options?.data.campus_id.map(campus => ({ id: campus.value, label: campus.label })) ?? [];
  return { overview: overview?.data ?? null, campuses, activity: activity?.data ?? [], campusError: canCampus && !options, activityError: canAudit && !activity };
});

async function campusCounts(user: User, campus: Campus): Promise<CampusSummary> {
  const params = new URLSearchParams({ campus_id: String(campus.id), status: "active", per_page: "1" });
  const [rooms, classes] = await Promise.all([
    optional<Collection<unknown>>("/foundation/rooms?" + params),
    user.permissions.includes("classes.view") ? optional<Collection<unknown>>("/foundation/classes?" + params) : null,
  ]);
  return { id: campus.id, name: campus.name, code: campus.code, status: campus.status, rooms: rooms?.meta.total ?? null, classes: classes?.meta.total ?? null };
}

export async function getWorkspaceDashboard(user: User, campusId: string, page = 1): Promise<WorkspaceDashboard> {
  const canCampus = user.permissions.includes("campuses.view");
  const canClasses = user.permissions.includes("classes.view");
  const canAcademic = user.permissions.includes("academics.view");
  if (campusId && (!/^[1-9]\d{0,15}$/.test(campusId) || !canCampus)) notFound();
  const query = new URLSearchParams({ per_page: "6", status: "active", sort: "name" });
  if (campusId) query.set("campus_id", campusId);
  const [chrome, campusPayload, classes, years, programmes, campusActivity] = await Promise.all([
    getWorkspaceChrome(user),
    canCampus ? optional<Collection<Campus> | { data: Campus }>(campusId ? "/foundation/campuses/" + campusId : "/foundation/campuses?per_page=6&sort=name&page=" + page) : null,
    canClasses ? optional<Collection<ClassSummary>>("/foundation/classes?" + query) : null,
    canAcademic ? optional<Collection<{ starts_on: string; name: string }>>("/foundation/years?status=active&per_page=1") : null,
    canAcademic ? optional<Collection<unknown>>("/foundation/programmes?status=active&per_page=1") : null,
    campusId && user.permissions.includes("audit.view") ? optional<{ data: Activity[] }>("/system/audit?campus_id=" + campusId) : null,
  ]);
  if (campusId && campusPayload === null) {
    const response = await authenticatedFetch("/foundation/campuses/" + campusId);
    if (response.status === 404) notFound();
  }
  const selectedRecord = campusId && campusPayload && !Array.isArray(campusPayload.data) ? campusPayload.data : null;
  const selected = selectedRecord ? { id: selectedRecord.id, name: selectedRecord.name, code: selectedRecord.code, status: selectedRecord.status } : null;
  const campusList = campusPayload ? Array.isArray(campusPayload.data) ? campusPayload.data : [campusPayload.data] : [];
  const campuses = await Promise.all(campusList.map(campus => campusCounts(user, campus)));
  const selectedCounts = campuses[0];
  const metrics = (chrome.overview?.metrics ?? []).map(metric => {
    if (!selected) return metric;
    if (metric.key === "campuses") return { ...metric, value: selected.status === "active" ? 1 : 0 };
    if (metric.key === "rooms") return { ...metric, value: selectedCounts?.rooms ?? null };
    if (metric.key === "classes") return { ...metric, value: selectedCounts?.classes ?? null };
    return metric;
  });
  const meta = campusPayload && "meta" in campusPayload ? campusPayload.meta : null;
  return {
    metrics, asOf: chrome.overview?.as_of ?? null, selectedCampus: selected,
    campuses, campusPage: meta?.current_page ?? 1, campusLastPage: meta?.last_page ?? 1, campusTotal: meta?.total ?? campuses.length,
    classes: classes?.data ?? [], classesTotal: classes?.meta.total ?? null,
    year: years?.data[0]?.starts_on.slice(0,4) ?? null, programmes: programmes?.meta.total ?? null,
    activity: (campusId ? campusActivity?.data ?? [] : chrome.activity).slice(0,5),
    errors: { overview: !chrome.overview, campuses: canCampus && !campusPayload, classes: canClasses && !classes, activity: campusId && user.permissions.includes("audit.view") ? !campusActivity : chrome.activityError },
  };
}
