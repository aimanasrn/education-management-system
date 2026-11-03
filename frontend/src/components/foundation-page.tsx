import { Suspense } from "react";
import { redirect } from "next/navigation";
import { AdminShell } from "@/components/admin-shell";
import { EntityManager } from "@/components/entity-manager";
import { LoadingState } from "@/components/loading-state";
import { authenticatedFetch, requirePermission } from "@/lib/auth";
import { entityConfigs, entityPresentation, entityUrl, type EntityKey, type EntityList, type Option } from "@/lib/foundation-config";

export type FoundationSearchParams = Promise<Record<string, string | string[] | undefined>>;
async function FoundationContent({ entity, searchParams }: { entity: EntityKey; searchParams: FoundationSearchParams }) {
  const user = await requirePermission(entityConfigs[entity].permission);
  const params = await searchParams;
  const view = entity === "rooms" && params.view === "facilities" ? "facilities" : entity === "classes" && params.view === "teachers" ? "teachers" : undefined;
  const config = entityPresentation(entity, view);
  const filters: Record<string, string> = { q: typeof params.q === "string" ? params.q.slice(0,100) : "", status: ["active", "inactive"].includes(String(params.status)) ? String(params.status) : "all", sort: ["-name", "newest"].includes(String(params.sort)) ? String(params.sort) : "name" };
  if (typeof params.campus_id === "string" && /^[1-9]\d*$/.test(params.campus_id)) filters.campus_id = params.campus_id;
  const page = typeof params.page === "string" && /^[1-9]\d*$/.test(params.page) && Number(params.page) <= 100000 ? params.page : "1";
  const endpoint = entity === "users" ? "/system/users" : "/foundation/" + entity;
  const [response, optionsResponse] = await Promise.all([authenticatedFetch(endpoint + "?" + new URLSearchParams(entity === "users" ? { q: filters.q, page } : { ...filters, page })), authenticatedFetch(endpoint + "/options")]);
  if (!response.ok || !optionsResponse.ok) throw new Error("Unable to load " + config.title.toLowerCase() + ".");
  const result: EntityList = await response.json();
  const optionPayload: { data: Record<string, Option[]> } = await optionsResponse.json();
  const options = entity === "users" ? { roles: optionPayload.data.roles, campus_ids: optionPayload.data.campuses } : optionPayload.data;
  if (view) filters.view = view;
  if (Number(page) > result.meta.last_page) redirect(entityUrl(entity, filters, result.meta.last_page));
  return <AdminShell user={user} title={config.title}><EntityManager entity={entity} result={result} options={options} filters={filters} canCreate={entity === "users" || result.access?.create === true} canEdit={entity === "users" || result.access?.edit === true} systemAdmin={user.roles.includes("admin")} /></AdminShell>;
}

export function FoundationPage(props: { entity: EntityKey; searchParams: FoundationSearchParams }) {
  return <Suspense fallback={<LoadingState message="Loading records…" />}><FoundationContent {...props} /></Suspense>;
}
